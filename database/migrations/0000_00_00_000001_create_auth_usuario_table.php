<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auth_usuario', function (Blueprint $table) {
            $table->uuid('id_usuario')->primary()->comment('Identificador único del usuario');
            $table->string('username')->comment('Nombre de usuario');
            $table->string('email')->unique()->index()->comment('Correo electrónico del usuario');
            $table->string('password')->comment('Contraseña del usuario');
            $table->timestamp('fecha_verificacion')->nullable()->comment('Fecha de verificación del correo electrónico');
            $table->timestamp('ultima_sesion')->nullable()->comment('Fecha de la última sesión del usuario');
            $table->boolean('tfa_activo')->default(false)->comment('¿Está activada la autenticación de dos factores?');
            $table->string('temporal_token', 8)->nullable()->comment('Token temporal para usos varios');
            $table->uuid('creado_por')->nullable()->comment('Usuario que creó este registro');
            $table->estado()->comment('Estado del usuario (tabla estado)');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('auth_usuario', function (Blueprint $table) {
            $table->foreign('creado_por')->references('id_usuario')->on('auth_usuario')->comment('Clave foránea del usuario que creó este registro');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()->comment('Correo electrónico para restablecer la contraseña');
            $table->string('token')->comment('Token de restablecimiento de contraseña');
            $table->timestamp('created_at')->nullable()->comment('Fecha de creación del token');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary()->comment('Identificador único de la sesión');
            $table->foreignId('user_id')->nullable()->index()->comment('Identificador del usuario asociado a la sesión');
            $table->string('ip_address', 45)->nullable()->comment('Dirección IP del usuario');
            $table->text('user_agent')->nullable()->comment('Agente de usuario del navegador');
            $table->longText('payload')->comment('Datos de la sesión');
            $table->integer('last_activity')->index()->comment('Última actividad de la sesión');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_usuario');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
