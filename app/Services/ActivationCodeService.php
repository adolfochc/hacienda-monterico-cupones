<?php

namespace App\Services;

use App\Models\MembershipCard;
use Illuminate\Support\Str;

class ActivationCodeService
{
    public function normalize(string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public function hash(string $code): string
    {
        return hash_hmac('sha256', $this->normalize($code), config('app.key'));
    }

    public function find(string $code): ?MembershipCard
    {
        return MembershipCard::where('activation_code_hash', $this->hash($code))->first();
    }

    public function generate(): string
    {
        return 'HMR-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
    }
}
