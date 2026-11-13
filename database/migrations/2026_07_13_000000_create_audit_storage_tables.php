<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_sesion_usuario', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_usuario')->comment('Usuario autenticado');
            $table->string('jti', 191)->comment('JWT ID del access token');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->json('acciones')->nullable();
            $table->json('permisos')->nullable();
            $table->json('menu')->nullable();
            $table->timestamp('creation_date')->nullable();
            $table->timestamp('updated_date')->nullable();

            $table->index('id_usuario');
            $table->index('jti');
            $table->unique(['id_usuario', 'jti']);
        });

        Schema::create('request_logs', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('method', 16);
            $table->string('uri', 2048);
            $table->json('headers')->nullable();
            $table->json('query')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->string('type', 50)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamp('creation_date')->nullable();
            $table->timestamp('updated_date')->nullable();

            $table->index('creation_date');
            $table->index('method');
            $table->index('status_code');
        });

        Schema::create('system_logs', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->text('message');
            $table->json('context')->nullable();
            $table->integer('level');
            $table->string('level_name', 50);
            $table->string('channel', 100);
            $table->timestamp('datetime');
            $table->json('extra')->nullable();

            $table->index('datetime');
            $table->index('channel');
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs');
        Schema::dropIfExists('request_logs');
        Schema::dropIfExists('auth_sesion_usuario');
    }
};
