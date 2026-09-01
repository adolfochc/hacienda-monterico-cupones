<?php

namespace App\Actions;

use App\Models\Booklet;
use App\Models\EmailVerificationCode;
use App\Models\MembershipCard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivateMembership
{
    public function execute(EmailVerificationCode $verification): User
    {
        return DB::transaction(function () use ($verification) {
            $pending = EmailVerificationCode::lockForUpdate()->findOrFail($verification->id);
            if (! $pending->verified_at || $pending->consumed_at) {
                throw ValidationException::withMessages(['code' => 'La verificación ya no está disponible.']);
            }
            $data = $pending->payload;
            $card = MembershipCard::with('template.items')->lockForUpdate()->findOrFail($data['card_id']);
            if ($card->status !== 'available' || ($card->expires_at && $card->expires_at->isPast())) {
                throw ValidationException::withMessages(['activation_code' => 'La tarjeta ya fue utilizada o no está disponible.']);
            }
            if (User::where('email', $pending->email)->exists()) {
                throw ValidationException::withMessages(['email' => 'El correo ya está registrado.']);
            }
            $user = User::create(['name' => $data['name'], 'email' => $pending->email, 'phone' => $data['phone'], 'password' => Hash::make($data['password']), 'role' => 'member', 'status' => 'active', 'must_change_password' => false, 'email_verified_at' => now(), 'invitation_used_at' => now()]);
            $user->update(['member_code' => 'HMR-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT)]);
            $booklet = Booklet::create(['membership_card_id' => $card->id, 'user_id' => $user->id, 'booklet_template_id' => $card->booklet_template_id, 'status' => 'active', 'activated_at' => now()]);
            $position = 0;
            foreach ($card->template->items as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    $user->couponAssignments()->create(['coupon_id' => $item->coupon_id, 'booklet_id' => $booklet->id, 'public_id' => (string) Str::uuid(), 'position' => ++$position, 'status' => 'available', 'assigned_at' => now()]);
                }
            }
            $card->update(['status' => 'activated', 'activated_by_user_id' => $user->id, 'activated_at' => now()]);
            $pending->update(['consumed_at' => now()]);

            return $user;
        });
    }
}
