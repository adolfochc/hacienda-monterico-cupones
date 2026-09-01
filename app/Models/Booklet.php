<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booklet extends Model
{
    protected $fillable = ['membership_card_id', 'user_id', 'booklet_template_id', 'status', 'activated_at'];

    protected $casts = ['activated_at' => 'datetime'];

    public function member()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function card()
    {
        return $this->belongsTo(MembershipCard::class, 'membership_card_id');
    }

    public function assignments()
    {
        return $this->hasMany(CouponAssignment::class);
    }
}
