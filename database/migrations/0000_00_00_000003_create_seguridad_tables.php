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
        Schema::create('seguridad_permiso', function (Blueprint $table) {
            $table->string('permiso')->primary()->notNullable()->comment('Código identificador del permiso');
            $table->string('descripcion')->nullable()->comment('Descripción del permiso');
            $table->boolean('activo')->notNullable()->default(true)->comment('Indica si el permiso está activo');
        });

        Schema::create('seguridad_grupo_accion', function (Blueprint $table) {
            $table->string('id_grupo')->primary()->notNullable()->comment('Identificador único del grupo de acciones');
            $table->string('nombre')->notNullable()->comment('Nombre del grupo de acciones');
            $table->string('descripcion')->nullable()->comment('Descripción del grupo de acciones');
            $table->boolean('activo')->notNullable()->default(true)->comment('Indica si el grupo de acciones está activo');
        });

        Schema::create('seguridad_accion', function (Blueprint $table) {
            $table->uuid('id_accion')->primary()->comment('Identificador único de la acción');
            $table->string('id_grupo')->notNullable()->comment('Identificador del grupo de acciones al que pertenece');
            $table->string('accion')->notNullable()->comment('Nombre de la acción');
            $table->string('descripcion')->nullable()->comment('Descripción de la acción');
            $table->boolean('activo')->notNullable()->default(true)->comment('Indica si la acción está activa');

            $table->foreign('id_grupo')->references('id_grupo')->on('seguridad_grupo_accion');
        });

        Schema::create('seguridad_permiso_accion', function (Blueprint $table) {
            $table->string('permiso')->notNullable()->comment('Código del permiso');
            $table->uuid('id_accion')->notNullable()->comment('Identificador de la acción');

            $table->primary(['permiso', 'id_accion'], 'pk_permiso_accion');

            $table->foreign('permiso')->references('permiso')->on('seguridad_permiso');
            $table->foreign('id_accion')->references('id_accion')->on('seguridad_accion');
        });

        Schema::create('seguridad_perfil', function (Blueprint $table) {
            $table->string('codigo')->primary()->notNullable()->comment('Código identificador del perfil');
            $table->string('nombre')->notNullable()->comment('Nombre del perfil');
            $table->string('descripcion')->nullable()->comment('Descripción del perfil');
            $table->boolean('activo')->notNullable()->default(true)->comment('Indica si el perfil está activo');
            $table->creadoPor();
            $table->timestamps();
        });

        Schema::create('seguridad_perfil_accion', function (Blueprint $table) {
            $table->string('codigo_perfil')->notNullable()->comment('Código del perfil');
            $table->uuid('id_accion')->notNullable()->comment('Identificador de la acción');
            $table->creadoPor();
            $table->timestamps();

            $table->primary(['codigo_perfil', 'id_accion'], 'pk_perfil_accion');

            $table->foreign('codigo_perfil')->references('codigo')->on('seguridad_perfil');
            $table->foreign('id_accion')->references('id_accion')->on('seguridad_accion');
        });

        Schema::create('auth_usuario_perfil', function (Blueprint $table) {
            $table->uuid('id_usuario');
            $table->string('codigo_perfil');
            $table->creadoPor();
            $table->timestamps();
            $table->softDeletes();
            $table->primary(['id_usuario', 'codigo_perfil']);

            $table->foreign('id_usuario')->references('id_usuario')->on('auth_usuario');
            $table->foreign('codigo_perfil')->references('codigo')->on('seguridad_perfil');
        });

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seguridad_perfil_accion');
        Schema::dropIfExists('auth_usuario_perfil');
        Schema::dropIfExists('seguridad_perfil');
        Schema::dropIfExists('seguridad_permiso_accion');
        Schema::dropIfExists('auth_usuario_accion');
        Schema::dropIfExists('seguridad_accion');
        Schema::dropIfExists('seguridad_grupo_accion');
        Schema::dropIfExists('seguridad_permiso');
    }
};
