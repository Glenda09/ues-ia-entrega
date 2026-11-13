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
        Schema::create('estado', function (Blueprint $table) {
            $table->string('codigo')->primary()->notNullable()->comment('Código identificador del estado');
            $table->string('nombre_estado')->notNullable()->comment('Nombre del estado');
            $table->string('label')->notNullable()->default('default')->comment('Etiquetas separadas por comas para el alcance del estado');
            $table->string('color')->notNullable()->default('#000000')->comment('Color asociado al estado (hexadecimal)');
            $table->string('icono')->nullable()->comment('Icono representativo del estado (material icons de google)');
            $table->boolean('activo')->notNullable()->default(true)->comment('Indica si el estado está activo');
        });

        Schema::create('app_modulo', function (Blueprint $table) {
            $table->string('codigo')->primary()->notNullable()->comment('Código identificador del módulo');
            $table->string('nombre')->notNullable()->comment('Nombre del módulo');
            $table->estado();
        });

        // Índices
        Schema::table('estado', function (Blueprint $table) {
            $table->index('codigo');
        });

        Schema::table('app_modulo', function (Blueprint $table) {
            $table->index('id_estado');
            $table->index('codigo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_modulo');
        Schema::dropIfExists('estado');
    }
};
