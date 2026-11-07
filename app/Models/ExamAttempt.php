<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\ExamAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

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
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * Not finished and not past its overall deadline.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInProgress(Builder $query): Builder
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

        return $this->expires_at && Carbon::parse($this->expires_at)->isPast() ? 'expired' : 'in_progress';
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
        return $this->break_started_at && ! $this->part_2_started_at;
    }

    /** When the part (or break) the user is in right now ends. */
    public function currentDeadline(): ?CarbonInterface
    {
        if ($this->part_2_started_at) {
            return $this->expires_at ? Carbon::parse($this->expires_at) : null;
        }

        if ($this->break_started_at) {
            return Carbon::parse($this->break_started_at)->addMinutes((int) config('portal.exam.break_minutes', 0));
        }

        return Carbon::parse($this->part_1_ends_at);
    }

    /**
     * Results by profile type and by question area, from this attempt's responses.
     * Scoring assumption: the best answer and the worst answer are worth one point each.
     * @return array{profile: array<int, array{id: int, name: mixed, percent: int, count: int}>, area: array<int, array{id: int, name: mixed, percent: int, count: int}>}
     */
    public function breakdown(): array
    {
        $rows = DB::table('exam_responses as r')
            ->join('exam_attempt_questions as aq', 'aq.id', '=', 'r.exam_attempt_question_id')
            ->join('questions as q', 'q.id', '=', 'aq.question_id')
            ->join('answer_options as b', 'b.id', '=', 'r.best_answer_option_id')
            ->leftJoin('answer_options as w', 'w.id', '=', 'r.worst_answer_option_id')
            ->where('aq.attempt_id', $this->id)
            ->get(['q.profile_type_id', 'q.question_area_id', 'b.is_best as best_ok', 'w.is_worst as worst_ok']);

        $group = function (string $column, string $table) use ($rows): array {
            $names = DB::table($table)->get(['id', 'name_en', 'name_ar'])->keyBy('id');
            $ar = app()->getLocale() === 'ar';

            return $rows->groupBy($column)->map(function ($items, $id) use ($names, $ar) {
                $row = $names[$id] ?? null;
                $name = $row ? ($ar ? ($row->name_ar ?: $row->name_en) : ($row->name_en ?: $row->name_ar)) : '-';
                $points = $items->sum(fn ($r) => (int) $r->best_ok + (int) $r->worst_ok);

                return ['id' => (int) $id, 'name' => $name, 'percent' => (int) round($points / (2 * $items->count()) * 100), 'count' => $items->count()];
            })->sortBy('id')->values()->all();
        };

        return [
            'profile' => $group('profile_type_id', 'profile_types'),
            'area' => $group('question_area_id', 'question_areas'),
        ];
    }
}
