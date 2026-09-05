<?php

use App\Models\Coupon;
use App\Models\CouponAssignment;
use App\Models\User;
use App\Services\CouponQrToken;
use App\Services\CouponRedemptionCode;
use Database\Seeders\ClientCampaignSeeder;

function benefitForMember(array $dates = []): CouponAssignment
{
    $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
    $coupon = Coupon::create($dates + ['name' => 'Beneficio', 'valid_from' => today(), 'valid_until' => today()->addMonth(), 'is_active' => true]);
    return CouponAssignment::create(['user_id' => $member->id, 'coupon_id' => $coupon->id, 'assigned_at' => now()]);
}

test('staff validates numeric code and can redeem it only once', function () {
    $assignment = benefitForMember();
    $staff = User::factory()->create(['role' => 'staff', 'status' => 'active']);
    $code = $this->actingAs($assignment->member)->postJson(route('member.coupons.qr', $assignment))->assertOk()->json('code');
    $this->actingAs($staff)->postJson(route('coupons.qr.validate'), ['code' => substr($code, 0, 5).' '.substr($code, 5)])->assertOk();
    $this->post(route('coupons.qr.redeem'), ['code' => $code])->assertSessionHasNoErrors();
    expect($assignment->fresh()->redemption_method)->toBe('code');
    expect($assignment->fresh()->redeemed_by)->toBe($staff->id);
    $this->post(route('coupons.qr.redeem'), ['code' => $code])->assertSessionHasErrors('coupon');
});

test('staff cannot manage members cards or promotions and members cannot scan', function () {
    $staff = User::factory()->create(['role' => 'staff', 'status' => 'active']);
    $this->actingAs($staff)->get('/')->assertRedirect('/canjes');
    $this->get('/canjes')->assertOk();
    foreach (['/socios', '/tarjetas', '/cupones'] as $url) $this->get($url)->assertForbidden();
    $this->post('/cupones', [])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'member', 'status' => 'active']))->get('/canjes')->assertForbidden();
    $staff->update(['status' => 'blocked']);
    $this->actingAs($staff)->postJson(route('coupons.qr.validate'), ['code' => '1234567890'])->assertForbidden();
});

test('numeric codes expire and cannot be guessed or used for blocked members', function () {
    $assignment = benefitForMember();
    $code = CouponRedemptionCode::issue($assignment);
    $this->actingAs(User::factory()->create(['role' => 'staff', 'status' => 'active']));
    $this->postJson(route('coupons.qr.validate'), ['code' => '0000000000'])->assertUnprocessable();
    $assignment->member->update(['status' => 'blocked']);
    $this->postJson(route('coupons.qr.validate'), ['code' => $code])->assertUnprocessable();
    $this->postJson(route('coupons.qr.redeem'), ['code' => $code])->assertUnprocessable();
    $assignment->member->update(['status' => 'active']);
    $this->travel(6)->minutes();
    $this->postJson(route('coupons.qr.validate'), ['code' => $code])->assertUnprocessable();
});

test('compact qr references are short opaque and expire after five minutes', function () {
    $assignment = benefitForMember();
    $token = CouponQrToken::issue($assignment);
    expect(strlen($token))->toBe(45);
    expect(CouponQrToken::resolve($token)->id)->toBe($assignment->id);
    $this->actingAs(User::factory()->create(['role' => 'staff', 'status' => 'active']));
    $this->postJson(route('coupons.qr.validate'), ['token' => $token.'x'])->assertUnprocessable();
    $this->travel(6)->minutes();
    $this->postJson(route('coupons.qr.validate'), ['token' => $token])->assertUnprocessable();
});

test('future and expired benefits cannot be issued validated or redeemed', function () {
    foreach ([['valid_from' => today()->addDay()], ['valid_from' => today()->subMonth(), 'valid_until' => today()->subDay()]] as $dates) {
        $assignment = benefitForMember($dates);
        $this->actingAs($assignment->member)->postJson(route('member.coupons.qr', $assignment))->assertUnprocessable();
        $token = CouponQrToken::issue($assignment);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $this->postJson(route('coupons.qr.validate'), ['token' => $token])->assertUnprocessable();
        $this->postJson(route('coupons.qr.redeem'), ['token' => $token])->assertUnprocessable();
    }
});

test('bulk assignment includes only active members and preserves redemptions', function () {
    $assignment = benefitForMember();
    $assignment->update(['status' => 'redeemed']);
    $second = User::factory()->create(['role' => 'member', 'status' => 'active']);
    User::factory()->create(['role' => 'member', 'status' => 'blocked']);
    User::factory()->create(['role' => 'staff', 'status' => 'active']);
    $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
    for ($i=0; $i<2; $i++) $this->post(route('coupons.assign', $assignment->coupon), ['all_members' => true])->assertSessionHasNoErrors();
    expect(CouponAssignment::count())->toBe(2);
    expect($assignment->fresh()->status)->toBe('redeemed');
    expect($second->couponAssignments()->count())->toBe(1);
});

test('client campaign contains seven benefits and loading it twice preserves history', function () {
    $existing = benefitForMember();
    $this->seed(ClientCampaignSeeder::class);
    $this->seed(ClientCampaignSeeder::class);
    expect(Coupon::count())->toBe(8);
    expect($existing->fresh())->not->toBeNull();
    expect(Coupon::whereDate('valid_from', '2026-11-16')->whereDate('valid_until', '2026-12-26')->count())->toBe(7);
});

test('a campaign redemption contributes one entry and unrelated redemptions do not', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-20 12:00:00'));
    $this->seed(ClientCampaignSeeder::class);
    $member = User::factory()->create(['role' => 'member', 'status' => 'active']);
    $coupon = Coupon::where('name', '20% de descuento')->firstOrFail();
    $assignment = CouponAssignment::create(['user_id' => $member->id, 'coupon_id' => $coupon->id, 'assigned_at' => now()]);
    $code = CouponRedemptionCode::issue($assignment);
    $this->actingAs(User::factory()->create(['role' => 'staff', 'status' => 'active']))
        ->post(route('coupons.qr.redeem'), ['code' => $code])->assertSessionHasNoErrors();
    $this->post(route('coupons.qr.redeem'), ['code' => $code])->assertSessionHasErrors('coupon');
    $other = Coupon::create(['name' => 'Otro beneficio', 'valid_from' => today(), 'valid_until' => today()->addMonth(), 'is_active' => true]);
    CouponAssignment::create(['user_id' => $member->id, 'coupon_id' => $other->id, 'assigned_at' => now(), 'status' => 'redeemed', 'redeemed_at' => now()]);
    $this->actingAs($member)->get('/')->assertInertia(fn ($page) => $page->component('member/Dashboard')->where('campaign.entries', 1));
});

test('staff provisioning hashes the password and requires a first password change', function () {
    $this->artisan('hmr:staff')->expectsQuestion('Nombre', 'Mozo de prueba')
        ->expectsQuestion('Correo', 'MOZO@example.com')
        ->expectsQuestion('Contraseña temporal (mínimo 12 caracteres)', 'Temporary-2026!')
        ->expectsOutput('Cuenta creada. Ingresa por /login y cambia la contraseña; luego se abrirá /canjes.')
        ->assertSuccessful();
    $user = User::where('email', 'mozo@example.com')->firstOrFail();
    expect($user->role)->toBe('staff');
    expect($user->must_change_password)->toBeTrue();
    expect(\Illuminate\Support\Facades\Hash::check('Temporary-2026!', $user->password))->toBeTrue();
});
