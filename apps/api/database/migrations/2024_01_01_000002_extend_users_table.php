<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('avatar_path', 255)->nullable()->after('phone');
            $table->string('status', 30)->default('active')->after('password');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->jsonb('preferences')->nullable()->after('last_login_at');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'avatar_path', 'status', 'last_login_at']);
            $table->dropSoftDeletes();
        });
    }
};
