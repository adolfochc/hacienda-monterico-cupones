<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->after('password');
            $table->string('member_code')->nullable()->unique()->after('role');
            $table->string('dni', 8)->nullable()->unique()->after('member_code');
            $table->string('status')->default('active')->after('dni');
            $table->boolean('must_change_password')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'role', 'member_code', 'dni', 'status', 'must_change_password',
        ]));
    }
};
