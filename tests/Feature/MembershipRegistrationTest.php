<?php

use App\Actions\ActivateMembership;
use App\Models\BookletTemplate;
use App\Models\Coupon;
use App\Models\EmailVerificationCode;
use App\Models\MembershipCard;
use App\Models\User;
use App\Notifications\RegistrationVerificationCode;
use App\Services\ActivationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function registrationCard(string $plain = 'HMR-TEST-0001'): array
{
    $coupon = Coupon::create(['name' => 'Beneficio familiar', 'valid_from' => today(), 'valid_until' => today()->addMonth(), 'is_active' => true]);
    $template = BookletTemplate::create(['name' => 'Familiar', 'version' => 1, 'is_active' => true]);
    $template->items()->create(['coupon_id' => $coupon->id, 'quantity' => 3, 'position' => 1]);
    $card = MembershipCard::create(['activation_code_hash' => app(ActivationCodeService::class)->hash($plain), 'activation_code_last4' => '0001', 'booklet_template_id' => $template->id, 'status' => 'available']);

    return [$card, $plain];
}

test('registration requires a valid unused membership card', function () {
    $this->post(route('membership.register.store'), ['name' => 'Familia Uno', 'phone' => '999999999', 'email' => 'familia@example.com', 'password' => 'ClaveSegura123!', 'password_confirmation' => 'ClaveSegura123!', 'activation_code' => 'NO-EXISTE'])->assertSessionHasErrors('activation_code');
    expect(User::where('email', 'familia@example.com')->exists())->toBeFalse();
});

test('registration sends otp and activation creates one booklet atomically', function () {
    $this->withoutExceptionHandling();
    Notification::fake();
    [$card,$plain] = registrationCard();
    $this->post(route('membership.register.store'), ['name' => 'Familia Uno', 'phone' => '999999999', 'email' => 'familia@example.com', 'password' => 'ClaveSegura123!', 'password_confirmation' => 'ClaveSegura123!', 'activation_code' => $plain])->assertRedirect();
    $pending = EmailVerificationCode::firstOrFail();
    expect($pending->expires_at->diffInSeconds($pending->created_at))->toBeLessThanOrEqual(300.0);
    $code = null;
    Notification::assertSentOnDemand(RegistrationVerificationCode::class, function ($notification) use (&$code) {
        $code = $notification->code;

        return true;
    });
    $this->post(route('membership.verify'), ['registration_token' => $pending->registration_token, 'code' => $code])->assertRedirect('/');
    $member = User::where('email', 'familia@example.com')->firstOrFail();
    expect($member->email_verified_at)->not->toBeNull()->and($member->booklet)->not->toBeNull()->and($member->couponAssignments)->toHaveCount(3)
        ->and($card->fresh()->status)->toBe('activated')->and($card->fresh()->activated_by_user_id)->toBe($member->id);
});

test('the same card cannot be activated twice', function () {
    [$card] = registrationCard();
    $first = EmailVerificationCode::create(['registration_token' => (string) Str::uuid(), 'email' => 'uno@example.com', 'code_hash' => Hash::make('123456'), 'payload' => ['name' => 'Uno', 'phone' => '999999999', 'password' => 'ClaveSegura123!', 'card_id' => $card->id], 'expires_at' => now()->addMinutes(10), 'last_sent_at' => now(), 'verified_at' => now()]);
    app(ActivateMembership::class)->execute($first);
    $second = EmailVerificationCode::create(['registration_token' => (string) Str::uuid(), 'email' => 'dos@example.com', 'code_hash' => Hash::make('123456'), 'payload' => ['name' => 'Dos', 'phone' => '988888888', 'password' => 'ClaveSegura123!', 'card_id' => $card->id], 'expires_at' => now()->addMinutes(10), 'last_sent_at' => now(), 'verified_at' => now()]);
    expect(fn () => app(ActivateMembership::class)->execute($second))->toThrow(Illuminate\Validation\ValidationException::class);
    expect(User::where('email', 'dos@example.com')->exists())->toBeFalse();
});
