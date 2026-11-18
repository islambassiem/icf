<?php

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Livewire\Livewire;
use Tests\Support\Gateways;
use Tests\Support\Portal;

beforeEach(function (): void {
    $this->user = Portal::user();
    $this->plan = Portal::plan(['name_en' => 'Quarterly', 'name_ar' => 'ربع سنوية', 'price' => 249, 'duration_days' => 90]);
});

describe('who can open it', function (): void {
    it('sends guests to the login page', function (): void {
        $this->get(route('subscription.checkout', $this->plan))->assertRedirect(route('login'));
    });

    it('sends users with an unverified email to the verification page', function (): void {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        if (! $user instanceof MustVerifyEmail) {
            $this->markTestSkipped('MustVerifyEmail is not enabled');
        }

        $this->actingAs($user)->get(route('subscription.checkout', $this->plan))
            ->assertRedirect(route('verification.notice'));
    });

    it('does not sell a plan that is switched off', function (): void {
        $off = Portal::plan(['name_en' => 'Old', 'is_active' => false]);

        $this->actingAs($this->user)->get(route('subscription.checkout', $off))->assertNotFound();
    });

    it('answers 404 for a plan that does not exist', function (): void {
        $this->actingAs($this->user)->get(route('subscription.checkout', 9999))->assertNotFound();
    });
});

describe('what it shows', function (): void {
    it('summarises the order', function (): void {
        $this->actingAs($this->user)->get(route('subscription.checkout', $this->plan))
            ->assertOk()
            ->assertSee('Review your order')
            ->assertSee('Quarterly')
            ->assertSee('249')
            ->assertSee('SAR')
            ->assertSee('90 days')
            ->assertSee('Test mode');
    });

    it('is shown in Arabic with the Arabic plan name', function (): void {
        $this->actingAs($this->user)->withSession(['locale' => 'ar'])->get(route('subscription.checkout', $this->plan))
            ->assertOk()
            ->assertSee('ربع سنوية')
            ->assertSee('راجع طلبك');
    });

    it('says the new period starts after the current one when a plan is running', function (): void {
        Portal::subscribe($this->user, ['ends_at' => now()->addDays(10)]);

        $this->actingAs($this->user)->get(route('subscription.checkout', $this->plan))
            ->assertSee(__('Your current plan is still running, so this period starts right after it ends.'));
    })->todo();

    it('says the plan starts today when nothing is running', function (): void {
        $this->actingAs($this->user)->get(route('subscription.checkout', $this->plan))
            ->assertSee('Today')
            ->assertDontSee(__('Your current plan is still running, so this period starts right after it ends.'));
    });
});

describe('happy path: paying', function (): void {
    it('sends the customer to the provider and creates a pending payment', function (): void {
        Livewire::actingAs($this->user)->test('pages::subscription.checkout', ['plan' => $this->plan])
            ->set('acceptTerms', true)
            ->call('pay')
            ->assertHasNoErrors()
            ->assertRedirectContains('fake-gateway');

        $payment = Payment::query()->firstOrFail();

        expect($payment->statusKey())->toBe('pending')->and((float) $payment->amount)->toBe(249.0)
            ->and($payment->subscription->user_id)->toBe($this->user->id);
    });

    it('uses the same pending payment if the customer comes back and pays again', function (): void {
        $page = Livewire::actingAs($this->user)->test('pages::subscription.checkout', ['plan' => $this->plan]);

        $page->set('acceptTerms', true)->call('pay');
        $page->call('pay');

        expect(Payment::query()->count())->toBe(1);
    });
});

describe('sad path', function (): void {
    it('will not continue until the terms are accepted', function (): void {
        Livewire::actingAs($this->user)->test('pages::subscription.checkout', ['plan' => $this->plan])
            ->call('pay')
            ->assertHasErrors(['acceptTerms'])
            ->assertNoRedirect();

        expect(Payment::query()->count())->toBe(0);
    });

    it('shows a friendly error, and stays on the page, when the provider is down', function (): void {
        app()->bind(PaymentGateway::class, fn (): PaymentGateway => Gateways::down());

        Livewire::actingAs($this->user)->test('pages::subscription.checkout', ['plan' => $this->plan])
            ->set('acceptTerms', true)
            ->call('pay')
            ->assertNoRedirect()
            ->assertSee('We could not start the payment');

        expect($this->user->activeSubscription()->first())->toBeNull();
    });
});
