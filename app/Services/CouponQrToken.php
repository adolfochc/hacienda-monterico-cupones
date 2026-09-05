<?php

namespace App\Services;

use App\Models\CouponAssignment;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponQrToken
{
    public static function issue(CouponAssignment $assignment): string
    {
        $payload = [
            'version' => 1,
            'assignment_id' => $assignment->id,
            'member_id' => $assignment->user_id,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $token = 'HMR1:'.Str::random(40);
            if (Cache::add('coupon-qr:'.hash('sha256', $token), $payload, now()->addMinutes(5))) {
                return $token;
            }
        }
        throw ValidationException::withMessages(['token' => 'No se pudo preparar el QR. Intenta nuevamente.']);
    }

    public static function resolve(string $token): CouponAssignment
    {
        try {
            // Short opaque references remain readable at mobile sizes. Legacy encrypted
            // tokens are accepted until their original five-minute lifetime expires.
            $payload = str_starts_with($token, 'HMR1:')
                ? Cache::get('coupon-qr:'.hash('sha256', $token), [])
                : json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages(['token' => 'El código QR es inválido o fue alterado.']);
        }

        if (($payload['version'] ?? null) !== 1 || ($payload['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['token' => 'El código QR venció. Solicita al socio que lo abra nuevamente.']);
        }

        $assignment = CouponAssignment::with(['member:id,name,member_code,status', 'coupon:id,name,description,valid_from,valid_until,is_active'])
            ->find($payload['assignment_id'] ?? 0);

        if (!$assignment || $assignment->user_id !== ($payload['member_id'] ?? null)) {
            throw ValidationException::withMessages(['token' => 'El código QR no corresponde a una asignación válida.']);
        }

        return $assignment;
    }
}
