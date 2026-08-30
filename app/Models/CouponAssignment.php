<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponAssignment extends Model
{
    protected $table = 'coupon_user';
    protected $fillable = ['coupon_id', 'user_id', 'status', 'assigned_at', 'redeemed_at', 'redeemed_by', 'redemption_note'];
    protected $casts = ['assigned_at' => 'datetime', 'redeemed_at' => 'datetime'];

    public function coupon() { return $this->belongsTo(Coupon::class); }
    public function member() { return $this->belongsTo(User::class, 'user_id'); }
    public function redeemer() { return $this->belongsTo(User::class, 'redeemed_by'); }
}
