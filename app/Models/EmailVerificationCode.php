<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerificationCode extends Model
{
    protected $fillable = ['registration_token', 'email', 'code_hash', 'payload', 'expires_at', 'attempts', 'resend_count', 'last_sent_at', 'verified_at', 'consumed_at'];

    protected $hidden = ['code_hash', 'payload'];

    protected $casts = ['payload' => 'encrypted:array', 'expires_at' => 'datetime', 'last_sent_at' => 'datetime', 'verified_at' => 'datetime', 'consumed_at' => 'datetime'];
}
