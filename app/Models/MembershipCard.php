<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipCard extends Model
{
    protected $fillable = ['activation_code_hash', 'activation_code_last4', 'booklet_template_id', 'card_batch_id', 'status', 'activated_by_user_id', 'activated_at', 'expires_at', 'created_by'];

    protected $hidden = ['activation_code_hash'];

    protected $casts = ['activated_at' => 'datetime', 'expires_at' => 'datetime'];

    public function template()
    {
        return $this->belongsTo(BookletTemplate::class, 'booklet_template_id');
    }

    public function booklet()
    {
        return $this->hasOne(Booklet::class);
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    public function batch()
    {
        return $this->belongsTo(CardBatch::class, 'card_batch_id');
    }
}
