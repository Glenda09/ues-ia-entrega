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
        Schema::dropIfExists('auth_usuario_accion');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('auth_usuario_accion', function (Blueprint $table) {
            $table->uuid('id_usuario');
            $table->uuid('id_accion');
            $table->creadoPor();
            $table->timestamps();
            $table->softDeletes();
            $table->primary(['id_usuario', 'id_accion']);

            $table->foreign('id_usuario')->references('id_usuario')->on('auth_usuario');
            $table->foreign('id_accion')->references('id_accion')->on('seguridad_accion');
        });
    }
};
