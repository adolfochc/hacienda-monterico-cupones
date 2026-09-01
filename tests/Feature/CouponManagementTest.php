<?php

use App\Models\Coupon;
use App\Models\CouponAssignment;
use App\Models\User;
use App\Services\CouponQrToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

function adminUser(): User
{
    return User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
}

test('administrator cannot create members manually', function () {
    $this->actingAs(adminUser())->post('/socios', [
        'name' => 'Socio manual', 'email' => 'manual@example.com',
    ])->assertStatus(405);
    expect(User::where('email', 'manual@example.com')->exists())->toBeFalse();
});

test('a coupon assignment can only be redeemed once', function () {
    $admin = adminUser();
    $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'email_verified_at' => now()]);
    $coupon = Coupon::create(['name' => 'Copa gratis', 'valid_from' => today(), 'valid_until' => today()->addMonth(), 'is_active' => true]);
    $assignment = CouponAssignment::create(['coupon_id' => $coupon->id, 'user_id' => $member->id, 'assigned_at' => now()]);

    $this->actingAs($admin)->post(route('coupons.redeem', $assignment))->assertSessionHasNoErrors();
    expect($assignment->fresh()->status)->toBe('redeemed');
    $this->actingAs($admin)->post(route('coupons.redeem', $assignment))->assertSessionHasErrors('coupon');
});

test('member must change a temporary password before opening dashboard', function () {
    $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'must_change_password' => true, 'email_verified_at' => now()]);
    $this->actingAs($member)->get('/')->assertRedirect(route('password.first.edit'));
    $this->actingAs($member)->put(route('password.first.update'), ['password' => 'NuevaClave123!', 'password_confirmation' => 'NuevaClave123!'])->assertRedirect('/');
    expect($member->fresh()->must_change_password)->toBeFalse();
});

test('encrypted qr can be validated and redeemed only once', function () {
    $admin = adminUser();
    $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'email_verified_at' => now()]);
    $coupon = Coupon::create(['name' => 'QR seguro', 'valid_from' => today(), 'valid_until' => today()->addMonth(), 'is_active' => true]);
    $assignment = CouponAssignment::create(['coupon_id' => $coupon->id, 'user_id' => $member->id, 'assigned_at' => now()]);
    $token = CouponQrToken::issue($assignment);

    $this->actingAs($admin)->postJson(route('coupons.qr.validate'), ['token' => $token])->assertOk()->assertJsonPath('member.member_code', $member->member_code);
    $this->actingAs($admin)->post(route('coupons.qr.redeem'), ['token' => $token])->assertSessionHasNoErrors();
    expect($assignment->fresh()->status)->toBe('redeemed');
    $this->actingAs($admin)->postJson(route('coupons.qr.validate'), ['token' => $token])->assertUnprocessable();
});

test('altered and expired qr tokens are rejected', function () {
    $admin = adminUser();
    $this->actingAs($admin)->postJson(route('coupons.qr.validate'), ['token' => 'token-manipulado'])->assertUnprocessable();
    $expired = Crypt::encryptString(json_encode(['version' => 1, 'assignment_id' => 1, 'member_id' => 1, 'expires_at' => now()->subMinute()->timestamp]));
    $this->actingAs($admin)->postJson(route('coupons.qr.validate'), ['token' => $expired])->assertUnprocessable();
});

test('member can renew only their own qr token', function () {
    $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'email_verified_at' => now()]);
    $other = User::factory()->create(['role' => 'member', 'status' => 'active', 'email_verified_at' => now()]);
    $coupon = Coupon::create(['name' => 'QR renovable', 'valid_from' => today(), 'valid_until' => today()->addMonth(), 'is_active' => true]);
    $assignment = CouponAssignment::create(['coupon_id' => $coupon->id, 'user_id' => $member->id, 'assigned_at' => now()]);

    $first = $this->actingAs($member)->postJson(route('member.coupons.qr', $assignment))->assertOk()->json('token');
    $second = $this->actingAs($member)->postJson(route('member.coupons.qr', $assignment))->assertOk()->json('token');
    expect($first)->not->toBe($second);
    $this->actingAs($other)->postJson(route('member.coupons.qr', $assignment))->assertForbidden();
});
