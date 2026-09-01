<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardBatch extends Model
{
    protected $fillable = ['name', 'booklet_template_id', 'quantity', 'status', 'created_by'];

    public function template()
    {
        return $this->belongsTo(BookletTemplate::class, 'booklet_template_id');
    }

    public function cards()
    {
        return $this->hasMany(MembershipCard::class);
    }
}
