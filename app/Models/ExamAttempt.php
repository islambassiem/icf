<?php

namespace App\Models;

use Database\Factories\ExamAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
