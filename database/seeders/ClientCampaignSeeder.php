<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class ClientCampaignSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('membership_campaign.coupons') as $coupon) {
            // Keep historical campaigns and their redemptions unchanged.
            $exists = Coupon::where('name', $coupon['name'])
                ->whereDate('valid_from', config('membership_campaign.starts_on'))
                ->whereDate('valid_until', config('membership_campaign.ends_on'))->exists();
            if ($exists) continue;
            Coupon::create($coupon + [
                'valid_from' => config('membership_campaign.starts_on'),
                'valid_until' => config('membership_campaign.ends_on'),
                'is_active' => true,
            ]);
        }
    }
}
