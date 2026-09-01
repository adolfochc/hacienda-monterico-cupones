<?php

use App\Models\Coupon;
use App\Models\MembershipCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('only administrators can open card management', function () {
    $member = User::factory()->create(['role' => 'member', 'status' => 'active', 'email_verified_at' => now()]);
    $this->actingAs($member)->get(route('cards.index'))->assertForbidden();
});

test('administrator generates a batch without storing plaintext activation codes', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
    $coupon = Coupon::create(['name' => 'Almuerzo', 'valid_from' => today(), 'valid_until' => today()->addMonth(), 'is_active' => true]);
    $response = $this->actingAs($admin)->post(route('card-batches.store'), ['name' => 'Lote septiembre', 'quantity' => 3, 'coupon_quantities' => [$coupon->id => 2]]);
    $response->assertOk()->assertDownload();
    expect(MembershipCard::count())->toBe(3)->and(MembershipCard::where('status', 'available')->count())->toBe(3);
    $card = MembershipCard::first();
    expect($card->getAttributes())->not->toHaveKey('activation_code')->and(strlen($card->activation_code_hash))->toBe(64);
});
