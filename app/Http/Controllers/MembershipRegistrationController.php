<?php

namespace App\Http\Controllers;

use App\Actions\ActivateMembership;
use App\Models\EmailVerificationCode;
use App\Notifications\RegistrationVerificationCode;
use App\Services\ActivationCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MembershipRegistrationController extends Controller
{
    public function create(Request $request)
    {
        return Inertia::render('Auth/MembershipRegister', ['activationCode' => $request->string('codigo')->toString()]);
    }

    public function store(Request $request, ActivationCodeService $codes)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:20'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::defaults()], 'activation_code' => ['required', 'string', 'max:40']]);
        $card = $codes->find($data['activation_code']);
        if (! $card || $card->status !== 'available' || ($card->expires_at && $card->expires_at->isPast())) {
            throw ValidationException::withMessages(['activation_code' => 'El código no es válido o ya fue utilizado.']);
        }
        $plain = (string) random_int(100000, 999999);
        $token = (string) Str::uuid();
        $verification = EmailVerificationCode::create(['registration_token' => $token, 'email' => Str::lower($data['email']), 'code_hash' => Hash::make($plain), 'payload' => ['name' => $data['name'], 'phone' => $data['phone'], 'password' => $data['password'], 'card_id' => $card->id], 'expires_at' => now()->addMinutes(10), 'last_sent_at' => now()]);
        Notification::route('mail', $verification->email)->notify(new RegistrationVerificationCode($plain));

        return redirect()->route('membership.verify.show', $token);
    }

    public function show(string $token)
    {
        $v = EmailVerificationCode::where('registration_token', $token)->firstOrFail();

        return Inertia::render('Auth/MembershipVerify', ['registrationToken' => $token, 'maskedEmail' => $this->mask($v->email), 'retryAfter' => max(0, 60 - $v->last_sent_at->diffInSeconds(now()))]);
    }

    public function verify(Request $request, ActivateMembership $activate)
    {
        $data = $request->validate(['registration_token' => ['required', 'uuid'], 'code' => ['required', 'digits:6']]);
        $v = EmailVerificationCode::where('registration_token', $data['registration_token'])->firstOrFail();
        if ($v->consumed_at || $v->expires_at->isPast() || $v->attempts >= 5) {
            throw ValidationException::withMessages(['code' => 'El código venció o alcanzó el límite de intentos.']);
        }
        if (! Hash::check($data['code'], $v->code_hash)) {
            $v->increment('attempts');
            throw ValidationException::withMessages(['code' => 'El código ingresado no es correcto.']);
        }
        $v->update(['verified_at' => now()]);
        $user = $activate->execute($v);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/')->with('success', 'Tu cuponera está activa.');
    }

    public function resend(Request $request)
    {
        $data = $request->validate(['registration_token' => ['required', 'uuid']]);
        $v = EmailVerificationCode::where('registration_token', $data['registration_token'])->firstOrFail();
        if ($v->consumed_at || $v->resend_count >= 3 || $v->last_sent_at->gt(now()->subMinute())) {
            throw ValidationException::withMessages(['code' => 'Espera antes de solicitar otro código.']);
        }
        $plain = (string) random_int(100000, 999999);
        $v->update(['code_hash' => Hash::make($plain), 'expires_at' => now()->addMinutes(10), 'attempts' => 0, 'resend_count' => $v->resend_count + 1, 'last_sent_at' => now()]);
        Notification::route('mail', $v->email)->notify(new RegistrationVerificationCode($plain));

        return back()->with('success', 'Enviamos un código nuevo.');
    }

    private function mask(string $email): string
    {
        [$a,$d] = explode('@', $email, 2);

        return mb_substr($a, 0, 2).str_repeat('*', max(2, mb_strlen($a) - 2)).'@'.$d;
    }
}
