<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateStaffUser extends Command
{
    protected $signature = 'hmr:staff';
    protected $description = 'Crear una cuenta de personal con acceso exclusivo al canje';

    public function handle(): int
    {
        $data = ['name' => $this->ask('Nombre'), 'email' => strtolower(trim($this->ask('Correo'))), 'password' => $this->secret('Contraseña temporal (mínimo 12 caracteres)')];
        $validator = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12']);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) $this->error($message);
            return self::FAILURE;
        }
        $data['password'] = Hash::make($data['password']);
        User::create($data + ['role' => 'staff', 'status' => 'active', 'must_change_password' => true]);
        $this->info('Cuenta creada. Ingresa por /login y cambia la contraseña; luego se abrirá /canjes.');
        return self::SUCCESS;
    }
}
