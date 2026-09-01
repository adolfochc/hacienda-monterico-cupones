<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookletTemplate extends Model
{
    protected $fillable = ['name', 'description', 'version', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function items()
    {
        return $this->hasMany(BookletTemplateItem::class)->orderBy('position');
    }

    public function cards()
    {
        return $this->hasMany(MembershipCard::class);
    }
}
