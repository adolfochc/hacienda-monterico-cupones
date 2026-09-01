<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookletTemplateItem extends Model
{
    protected $fillable = ['booklet_template_id', 'coupon_id', 'quantity', 'position'];

    public function template()
    {
        return $this->belongsTo(BookletTemplate::class, 'booklet_template_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}
