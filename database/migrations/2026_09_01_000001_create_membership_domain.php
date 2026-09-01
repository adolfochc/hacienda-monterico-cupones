<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booklet_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('booklet_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booklet_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['booklet_template_id', 'coupon_id']);
        });
        Schema::create('card_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('booklet_template_id')->constrained();
            $table->unsignedInteger('quantity')->default(0);
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('membership_cards', function (Blueprint $table) {
            $table->id();
            $table->string('activation_code_hash', 64)->unique();
            $table->string('activation_code_last4', 4);
            $table->foreignId('booklet_template_id')->constrained();
            $table->foreignId('card_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('available');
            $table->foreignId('activated_by_user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });
        Schema::create('booklets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_card_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('booklet_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
        Schema::create('email_verification_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('registration_token')->unique();
            $table->string('email')->index();
            $table->string('code_hash', 64);
            $table->text('payload');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('resend_count')->default(0);
            $table->timestamp('last_sent_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('coupon_user', fn (Blueprint $table) => $table->index('coupon_id', 'coupon_user_coupon_id_index'));
        Schema::table('coupon_user', function (Blueprint $table) {
            $table->dropUnique(['coupon_id', 'user_id']);
            $table->foreignId('booklet_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->uuid('public_id')->nullable()->unique()->after('booklet_id');
            $table->unsignedInteger('position')->default(0)->after('public_id');
            $table->string('redemption_method')->nullable()->after('redemption_note');
            $table->index(['booklet_id', 'status']);
        });

        // Preserve every existing assignment inside one legacy booklet per member.
        $templateId = DB::table('booklet_templates')->insertGetId(['name' => 'Cuponera heredada', 'description' => 'Datos anteriores al autorregistro', 'version' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->where('role', 'member')->orderBy('id')->each(function ($user) use ($templateId) {
            $bookletId = DB::table('booklets')->insertGetId(['user_id' => $user->id, 'booklet_template_id' => $templateId, 'status' => 'active', 'activated_at' => $user->created_at, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('coupon_user')->where('user_id', $user->id)->update(['booklet_id' => $bookletId]);
        });
        DB::table('coupon_user')->whereNull('public_id')->orderBy('id')->each(fn ($row) => DB::table('coupon_user')->where('id', $row->id)->update(['public_id' => (string) Illuminate\Support\Str::uuid()]));
    }

    public function down(): void
    {
        Schema::table('coupon_user', function (Blueprint $table) {
            $table->dropForeign(['booklet_id']);
            $table->dropIndex(['booklet_id', 'status']);
            $table->dropColumn(['booklet_id', 'public_id', 'position', 'redemption_method']);
        });
        Schema::dropIfExists('email_verification_codes');
        Schema::dropIfExists('booklets');
        Schema::dropIfExists('membership_cards');
        Schema::dropIfExists('card_batches');
        Schema::dropIfExists('booklet_template_items');
        Schema::dropIfExists('booklet_templates');
    }
};
