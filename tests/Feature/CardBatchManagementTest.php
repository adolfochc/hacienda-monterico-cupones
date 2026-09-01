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
    expect($card->getAttributes())->not->toHaveKey('activation_code')->and(strlen($card->activation_code_hash))->toBe(64)
        ->and($card->activation_code_encrypted)->not->toBeNull();

    $this->actingAs($admin)->get(route('card-batches.export', $card->batch))->assertOk()->assertDownload();
});

test('legacy unused batch rotates unrecoverable codes before exporting again', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
    $coupon = Coupon::create(['name' => 'Postre', 'valid_from' => today(), 'valid_until' => today()->addMonth(), 'is_active' => true]);
    $template = App\Models\BookletTemplate::create(['name' => 'Legado', 'is_active' => true]);
    $template->items()->create(['coupon_id' => $coupon->id, 'quantity' => 1]);
    $batch = App\Models\CardBatch::create(['name' => 'Lote legado', 'booklet_template_id' => $template->id, 'quantity' => 1, 'status' => 'active', 'created_by' => $admin->id]);
    $card = MembershipCard::create(['activation_code_hash' => app(App\Services\ActivationCodeService::class)->hash('CODIGO-ANTERIOR'), 'activation_code_last4' => 'RIOR', 'booklet_template_id' => $template->id, 'card_batch_id' => $batch->id, 'status' => 'available']);

    $oldHash = $card->activation_code_hash;
    $this->actingAs($admin)->get(route('card-batches.export', $batch))->assertOk()->assertDownload();
    expect($card->fresh()->activation_code_encrypted)->not->toBeNull()->and($card->fresh()->activation_code_hash)->not->toBe($oldHash);
});
