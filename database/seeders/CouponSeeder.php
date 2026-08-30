<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            ['name' => 'Llévate un postre', 'description' => 'Por la compra de una parrilla'],
            ['name' => '2x1 en Tinto de Verano', 'description' => 'Válido de lunes a jueves'],
            ['name' => 'Copa de vino gratis', 'description' => 'Por la compra de un corte premium'],
            ['name' => '20% de descuento', 'description' => 'En Pollo y Medio'],
            ['name' => 'Bandejeable', 'description' => 'Te invitamos la bebida'],
        ];

        foreach ($coupons as $coupon) {
            Coupon::updateOrCreate(['name' => $coupon['name']], $coupon + [
                'valid_from' => now()->startOfMonth(),
                'valid_until' => now()->addMonths(3)->endOfMonth(),
                'is_active' => true,
            ]);
        }
    }
}
