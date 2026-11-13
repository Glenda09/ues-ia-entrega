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
        Schema::create('seguridad_menu_opcion', function (Blueprint $table) {
            $table->uuid('id_opcion')->primary()->comment('Identificador único de la opción de menú');
            $table->string('id_modulo')->comment('Código del módulo al que pertenece');
            $table->uuid('id_opcion_padre')->nullable()->comment('Opción padre (self FK)');
            $table->string('nombre')->comment('Nombre visible de la opción');
            $table->string('ruta')->nullable()->comment('Ruta o path asociado');
            $table->string('icono')->nullable()->comment('Icono representativo');
            $table->boolean('desplegable')->default(false)->comment('Indica si la opción es desplegable');
            $table->integer('orden')->default(0)->comment('Orden para visualización');
            $table->estado()->comment('Estado del registro (tabla estado)');
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('id_modulo');
            $table->index('id_estado');
            $table->index('id_opcion_padre');
        });

        // Relaciones
        Schema::table('seguridad_menu_opcion', function (Blueprint $table) {
            $table->foreign('id_modulo')->references('codigo')->on('app_modulo');
            $table->foreign('id_opcion_padre')->references('id_opcion')->on('seguridad_menu_opcion')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seguridad_menu_opcion');
    }
};

