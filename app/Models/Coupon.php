<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'terms', 'valid_from', 'valid_until', 'is_active'];

    protected $casts = ['valid_from' => 'date', 'valid_until' => 'date', 'is_active' => 'boolean'];

    public function assignments()
    {
        return $this->hasMany(CouponAssignment::class);
    }

    public function members()
    {
        return $this->belongsToMany(User::class)->withPivot(['id', 'status', 'assigned_at', 'redeemed_at']);
    }
}
