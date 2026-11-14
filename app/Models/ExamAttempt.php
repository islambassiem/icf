<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\ExamAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property ?Carbon $started_at
 * @property ?Carbon $part_1_ends_at
 * @property ?Carbon $break_started_at
 * @property ?Carbon $part_2_started_at
 * @property ?Carbon $expires_at
 * @property ?CarbonInterface $completed_at
 * @property ?int $score
 */
#[Fillable([
    'user_id',
    'subscription_id',
    'exam_type_id',
    'status',
    'started_at',
    'part_1_ends_at',
    'break_started_at',
    'part_2_started_at',
    'expires_at',
    'completed_at',
    'score',
])]
class ExamAttempt extends Model
{
    /** @use HasFactory<ExamAttemptFactory> */
    use HasFactory;

    // Used when CREATING attempts. The dashboard never reads `status`: it derives the state
    // from the timestamps (completed_at / expires_at), so any wording in that column is safe.
    public const STATUS_IN_PROGRESS = 'In progress';

    public const STATUS_COMPLETED = 'Completed';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'part_1_ends_at' => 'datetime',
            'break_started_at' => 'datetime',
            'part_2_started_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<ExamType, $this>
     */
    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    /**
     * @return HasMany<ExamAttemptQuestion, $this>
     */
    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(ExamAttemptQuestion::class, 'attempt_id');
    }

    /**
     * @return HasManyThrough<ExamResponse, ExamAttemptQuestion, $this>
     */
    public function responses(): HasManyThrough
    {
        return $this->hasManyThrough(ExamResponse::class, ExamAttemptQuestion::class, 'attempt_id', 'exam_attempt_question_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function completed(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * Not finished and not past its overall deadline.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function inProgress(Builder $query): Builder
    {
        return $query->whereNull('completed_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** completed | expired | in_progress */
    public function state(): string
    {
        if ($this->completed_at) {
            return 'completed';
        }

        return $this->expires_at?->isPast() ? 'expired' : 'in_progress';
    }

    /** Stored `score` as a 0-100 percentage. Set PORTAL_SCORE_MAX if `score` is raw points. */
    public function scorePercent(): ?float
    {
        if ($this->score === null) {
            return null;
        }

        return round($this->score / max(1, (float) config('portal.score_max')) * 100, 1);
    }

    /** 1 or 2 (the break counts as part 1 until part 2 starts). */
    public function currentPart(): int
    {
        return $this->part_2_started_at ? 2 : 1;
    }

    public function onBreak(): bool
    {
        return $this->break_started_at !== null && $this->part_2_started_at === null;
    }

    /** When the part (or break) the user is in right now ends. */
    public function currentDeadline(): ?CarbonInterface
    {
        if ($this->part_2_started_at) {
            return $this->expires_at;
        }

        if ($this->break_started_at) {
            return $this->break_started_at->copy()->addMinutes((int) config('portal.exam.break_minutes'));
        }

        return $this->part_1_ends_at;
    }

    /**
     * Results by profile type and by question area, from the questions frozen into this attempt.
     * Scoring: the best answer and the worst answer are worth one point each; unanswered questions score 0.
     *
     * @return array{
     *  profile: list<array{id: int, name: string, percent: int, count: int}>,
     *  area: list<array{id: int, name: string, percent: int, count: int}>
     * }
     */
    public function breakdown(): array
    {
        $rows = $this->attemptQuestions()->with('response')->get()->map(
            fn (ExamAttemptQuestion $aq): array => [
                'profile' => $aq->profileTypeId(),
                'area' => $aq->questionAreaId(),
                'points' => $aq->points(),
            ]
        );

        return [
            'profile' => $this->groupResults($rows, 'profile', 'profile_types'),
            'area' => $this->groupResults($rows, 'area', 'question_areas'),
        ];
    }

    /**
     * @param  Collection<int, array{profile: int, area: int, points: int}>  $rows
     * @param  'profile'|'area'  $key
     * @return list<array{id: int, name: string, percent: int, count: int}>
     */
    private function groupResults(Collection $rows, string $key, string $table): array
    {
        $names = DB::table($table)->get(['id', 'name_en', 'name_ar'])->keyBy('id');
        $arabic = app()->getLocale() === 'ar';
        $groups = [];

        foreach ($rows->groupBy($key) as $id => $items) {
            $row = $names->get($id);
            $name = '-';

            if ($row !== null) {
                $en = (string) $row->name_en;
                $ar = (string) $row->name_ar;
                $name = $arabic ? ($ar !== '' ? $ar : $en) : ($en !== '' ? $en : $ar);
            }

            $groups[] = [
                'id' => (int) $id,
                'name' => $name,
                'percent' => (int) round($items->sum('points') / (2 * $items->count()) * 100),
                'count' => $items->count(),
            ];
        }

        usort($groups, fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return $groups;
    }
}
