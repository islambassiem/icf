<?php

namespace App\Models;

use Database\Factories\ExamAttemptQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

#[Fillable([
    'attempt_id',
    'question_id',
    'part_number',
    'position',
    'question_snapshot',
])]
class ExamAttemptQuestion extends Model
{
    /** @use HasFactory<ExamAttemptQuestionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'part_number' => 'integer',
            'position' => 'integer',
            'question_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return HasOne<ExamResponse, $this>
     */
    public function response(): HasOne
    {
        return $this->hasOne(ExamResponse::class);
    }

    /**
     * The frozen copy of the question (text, options, right answers). Never send it to the browser.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $raw = $this->getAttribute('question_snapshot');

        /** @var array<string, mixed> $data */
        $data = is_array($raw) ? $raw : [];

        return $data;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function snapshotOptions(): Collection
    {
        $options = $this->snapshot()['options'] ?? [];

        /** @var list<array<string, mixed>> $list */
        $list = is_array($options) ? array_values($options) : [];

        return new Collection($list);
    }

    public function bestOptionId(): ?int
    {
        return $this->flaggedOptionId('is_best');
    }

    public function worstOptionId(): ?int
    {
        return $this->flaggedOptionId('is_worst');
    }

    public function profileTypeId(): int
    {
        return (int) ($this->snapshot()['profile_type_id'] ?? 0);
    }

    public function questionAreaId(): int
    {
        return (int) ($this->snapshot()['question_area_id'] ?? 0);
    }

    /** Points earned: 1 for the right best answer plus 1 for the right worst answer (0 if unanswered). */
    public function points(): int
    {
        $response = $this->response;

        if ($response === null) {
            return 0;
        }

        $best = $this->bestOptionId();
        $worst = $this->worstOptionId();
        $yourWorst = $response->worst_answer_option_id;

        return (int) ($best !== null && (int) $response->best_answer_option_id === $best)
            + (int) ($worst !== null && $yourWorst !== null && (int) $yourWorst === $worst);
    }

    private function flaggedOptionId(string $flag): ?int
    {
        foreach ($this->snapshotOptions() as $option) {
            if (! empty($option[$flag])) {
                $id = (int) ($option['id'] ?? 0);

                return $id > 0 ? $id : null;
            }
        }

        return null;
    }
}
