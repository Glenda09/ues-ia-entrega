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
        Schema::create('auth_api_key_permiso', function (Blueprint $table) {
            $table->uuid('api_key')->comment('FK a auth_api_key.api_key');
            $table->string('id_permiso')->comment('FK a seguridad_permiso.permiso');
            // Nullable para permitir seed inicial antes de crear usuarios
            $table->creadoPor(true);
            $table->timestamp('created_at')->useCurrent()->comment('Fecha de creación');

            $table->primary(['api_key', 'id_permiso'], 'pk_auth_api_key_permiso');

            $table->foreign('api_key')->references('api_key')->on('auth_api_key');
            $table->foreign('id_permiso')->references('permiso')->on('seguridad_permiso');

            $table->index('api_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_api_key_permiso');
    }
};

