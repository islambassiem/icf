<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int $question_id
 * @property string $statement_en
 * @property string $statement_ar
 * @property string $comment_en
 * @property string $comment_ar
 * @property bool $is_best
 * @property bool|null $is_worst
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Question $question
 * @method static \Database\Factories\AnswerOptionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerOption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerOption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerOption query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerOption whereCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerOption whereCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerOption whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerOption whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerOption whereIsBest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerOption whereIsWorst($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerOption whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerOption whereStatementAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerOption whereStatementEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerOption whereUpdatedAt($value)
 */
	class AnswerOption extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $subscription_id
 * @property int $exam_type_id
 * @property string $status
 * @property \Carbon\CarbonImmutable $started_at
 * @property \Carbon\CarbonImmutable|null $part_1_ends_at
 * @property \Carbon\CarbonImmutable|null $break_started_at
 * @property \Carbon\CarbonImmutable|null $part_2_started_at
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property \Carbon\CarbonImmutable|null $completed_at
 * @property int|null $score
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExamAttemptQuestion> $attemptQuestions
 * @property-read int|null $attempt_questions_count
 * @property-read \App\Models\ExamType $examType
 * @property-read \App\Models\Subscription $subscription
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\ExamAttemptFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereBreakStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereExamTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt wherePart1EndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt wherePart2StartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttempt whereUserId($value)
 */
	class ExamAttempt extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $attempt_id
 * @property int $question_id
 * @property int $part_number
 * @property int $position
 * @property array<array-key, mixed> $question_snapshot
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\ExamAttempt $attempt
 * @property-read \App\Models\Question $question
 * @property-read \App\Models\ExamResponse|null $response
 * @method static \Database\Factories\ExamAttemptQuestionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptQuestion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptQuestion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptQuestion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptQuestion whereAttemptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptQuestion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptQuestion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptQuestion wherePartNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptQuestion wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptQuestion whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptQuestion whereQuestionSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamAttemptQuestion whereUpdatedAt($value)
 */
	class ExamAttemptQuestion extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $exam_attempt_question_id
 * @property int $best_answer_option_id
 * @property int|null $worst_answer_option_id
 * @property \Carbon\CarbonImmutable|null $answered_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\ExamAttemptQuestion $attemptQuestion
 * @property-read \App\Models\AnswerOption $bestAnswerOption
 * @property-read \App\Models\AnswerOption|null $worstAnswerOption
 * @method static \Database\Factories\ExamResponseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamResponse newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamResponse newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamResponse query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamResponse whereAnsweredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamResponse whereBestAnswerOptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamResponse whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamResponse whereExamAttemptQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamResponse whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamResponse whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamResponse whereWorstAnswerOptionId($value)
 */
	class ExamResponse extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property string|null $description
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @method static \Database\Factories\ExamTypeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamType whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamType whereNameAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamType whereNameEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamType whereUpdatedAt($value)
 */
	class ExamType extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $subscription_id
 * @property string|null $provider
 * @property string|null $provider_payment_id
 * @property string|null $provider_checkout_id
 * @property numeric $amount
 * @property \App\Enums\CurrencyEnum $currency
 * @property \App\Enums\PaymentStatusEnum $status
 * @property \Carbon\CarbonImmutable $paid_at
 * @property string|null $failure_reason
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Subscription $subscription
 * @method static \Database\Factories\PaymentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereFailureReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereProviderCheckoutId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereProviderPaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUpdatedAt($value)
 */
	class Payment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property string|null $description
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @method static \Database\Factories\ProfileTypeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileType whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileType whereNameAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileType whereNameEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProfileType whereUpdatedAt($value)
 */
	class ProfileType extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $statement_en
 * @property string $statement_ar
 * @property int $exam_type_id
 * @property int $question_area_id
 * @property int $profile_type_id
 * @property string $status
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AnswerOption> $answerOptions
 * @property-read int|null $answer_options_count
 * @property-read \App\Models\ExamType $examType
 * @property-read \App\Models\ProfileType $profileType
 * @property-read \App\Models\QuestionArea $questionArea
 * @method static \Database\Factories\QuestionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereExamTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereProfileTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereQuestionAreaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereStatementAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereStatementEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereUpdatedAt($value)
 */
	class Question extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property string|null $description
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @method static \Database\Factories\QuestionAreaFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionArea newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionArea newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionArea query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionArea whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionArea whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionArea whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionArea whereNameAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionArea whereNameEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionArea whereUpdatedAt($value)
 */
	class QuestionArea extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $subscription_plan_id
 * @property string $status
 * @property \Carbon\CarbonImmutable $starts_at
 * @property \Carbon\CarbonImmutable $ends_at
 * @property numeric $amount_paid
 * @property \App\Enums\CurrencyEnum $currency
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\SubscriptionPlan $subscriptionPlan
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\SubscriptionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereStartsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereSubscriptionPlanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereUserId($value)
 */
	class Subscription extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property int $duration_days
 * @property numeric $price
 * @property \App\Enums\CurrencyEnum $currency
 * @property int $is_active
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @method static \Database\Factories\SubscriptionPlanFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan whereDurationDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan whereNameAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan whereNameEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionPlan whereUpdatedAt($value)
 */
	class SubscriptionPlan extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property string|null $two_factor_confirmed_at
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, Passkey> $passkeys
 * @property-read int|null $passkeys_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, Permission> $teams
 * @property-read int|null $teams_count
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User permission($permissions, bool $without = false)
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static Builder<static>|User team($teams, bool $without = false)
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereName($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static Builder<static>|User whereTwoFactorSecret($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @method static Builder<static>|User withoutPermission($permissions)
 * @method static Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static Builder<static>|User withoutTeam($teams)
 * @mixin Model
 */
	class User extends \Eloquent implements \Illuminate\Contracts\Auth\MustVerifyEmail, \Laravel\Fortify\Contracts\PasskeyUser, \Laravel\Passkeys\Contracts\PasskeyUser {}
}

