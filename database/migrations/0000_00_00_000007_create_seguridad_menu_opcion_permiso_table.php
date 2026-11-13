<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguridad_menu_opcion_permiso', function (Blueprint $table) {
            $table->string('id_permiso', 255)->comment('Código del permiso (FK seguridad_permiso.permiso)');
            $table->uuid('id_opcion')->comment('Opción de menú (FK seguridad_menu_opcion.id_opcion)');
            $table->uuid('creado_por')->comment('Usuario que creó el registro');
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->primary(['id_permiso', 'id_opcion'], 'pk_menu_opcion_permiso');

            $table->foreign('id_permiso')->references('permiso')->on('seguridad_permiso');
            $table->foreign('id_opcion')->references('id_opcion')->on('seguridad_menu_opcion');
            $table->foreign('creado_por')->references('id_usuario')->on('auth_usuario');

            $table->index('id_permiso');
            $table->index('id_opcion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguridad_menu_opcion_permiso');
    }
};

