<?php

namespace App\Services;

use App\Models\CouponAssignment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CouponRedemptionCode
{
    public static function issue(CouponAssignment $assignment): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = (string) random_int(1000000000, 9999999999);
            if (Cache::add('coupon-code:'.hash('sha256', $code), $assignment->id, now()->addMinutes(5))) {
                return $code;
            }
        }
        throw ValidationException::withMessages(['code' => 'No se pudo preparar el código. Intenta nuevamente.']);
    }

    public static function resolve(string $code): CouponAssignment
    {
        $id = Cache::get('coupon-code:'.hash('sha256', preg_replace('/[\s-]/', '', $code)));
        $assignment = $id ? CouponAssignment::with(['member', 'coupon'])->find($id) : null;
        if (!$assignment) {
            throw ValidationException::withMessages(['token' => 'El código no es válido o venció. Pide al socio que genere uno nuevo.']);
        }

        return $assignment;
    }
}
