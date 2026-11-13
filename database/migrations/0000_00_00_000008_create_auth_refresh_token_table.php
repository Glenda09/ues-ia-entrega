<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auth_refresh_token', function (Blueprint $table) {
            $table->uuid('id_refresh_token')->primary();
            $table->uuid('id_usuario');
            $table->string('token_hash', 128)->unique();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('revoked')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index('id_usuario');
            $table->index(['revoked', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_refresh_token');
    }
};

