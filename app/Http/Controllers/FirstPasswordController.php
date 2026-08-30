<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class FirstPasswordController extends Controller
{
    public function edit() { return Inertia::render('Auth/FirstPassword'); }

    public function update(Request $request)
    {
        $data = $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);
        $request->user()->update(['password' => Hash::make($data['password']), 'must_change_password' => false]);
        return redirect('/')->with('success', 'Contraseña actualizada.');
    }
}
