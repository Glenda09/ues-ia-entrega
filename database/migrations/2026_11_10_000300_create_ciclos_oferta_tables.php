<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CAR-01 y CAR-02: ciclos, ventanas, oferta, grupos, horarios y asignaciones docentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ciclo_carrera', function (Blueprint $table) {
            $table->id('id_ciclo_carrera');
            $table->foreignId('id_carrera')->constrained('carrera', 'id_carrera');
            $table->string('codigo', 50);
            $table->string('nombre', 200);
            $table->timestamp('inicio')->nullable();
            $table->timestamp('fin')->nullable();
            $table->string('zona', 64);
            $table->string('estado', 20)->default('borrador')->comment('borrador | publicado | cerrado');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('version_configuracion')->nullable()->comment('Revisión BASE-02 aplicada al publicar');
            $table->json('configuracion_aplicada')->nullable()->comment('Instantánea de parámetros efectivos');
            $table->uuid('creado_por')->nullable();
            $table->timestamps();
            $table->unique(['id_carrera', 'codigo']);
            $table->index(['id_carrera', 'estado']);
        });

        Schema::create('ventana_ciclo', function (Blueprint $table) {
            $table->id('id_ventana_ciclo');
            $table->foreignId('id_ciclo_carrera')->constrained('ciclo_carrera', 'id_ciclo_carrera');
            $table->string('tipo', 20)->comment('inscripcion | notas');
            $table->timestamp('inicio')->nullable();
            $table->timestamp('fin_regular')->nullable();
            $table->timestamp('fin_gracia')->nullable();
            $table->unique(['id_ciclo_carrera', 'tipo']);
        });

        Schema::create('ciclo_ajuste', function (Blueprint $table) {
            $table->id('id_ciclo_ajuste');
            $table->foreignId('id_ciclo_carrera')->constrained('ciclo_carrera', 'id_ciclo_carrera');
            $table->unsignedInteger('version_resultante');
            $table->json('anterior');
            $table->json('nuevo');
            $table->string('motivo', 1000)->nullable();
            $table->uuid('id_usuario')->nullable();
            $table->timestamp('instante');
        });

        Schema::create('oferta_materia', function (Blueprint $table) {
            $table->id('id_oferta_materia');
            $table->foreignId('id_ciclo_carrera')->constrained('ciclo_carrera', 'id_ciclo_carrera');
            $table->foreignId('id_materia')->constrained('materia', 'id_materia');
            $table->uuid('creado_por')->nullable();
            $table->timestamps();
            $table->unique(['id_ciclo_carrera', 'id_materia']);
        });

        Schema::create('grupo', function (Blueprint $table) {
            $table->id('id_grupo');
            $table->foreignId('id_oferta_materia')->constrained('oferta_materia', 'id_oferta_materia');
            $table->string('codigo', 20);
            $table->unsignedInteger('cupo');
            $table->string('estado', 20)->default('borrador')->comment('borrador | publicado | cancelado');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['id_oferta_materia', 'codigo']);
        });

        Schema::create('horario_grupo', function (Blueprint $table) {
            $table->id('id_horario_grupo');
            $table->foreignId('id_grupo')->constrained('grupo', 'id_grupo');
            $table->unsignedTinyInteger('dia')->comment('ISO 1-7');
            $table->string('hora_inicio', 5);
            $table->string('hora_fin', 5);
        });

        Schema::create('asignacion_docente', function (Blueprint $table) {
            $table->id('id_asignacion_docente');
            $table->foreignId('id_grupo')->constrained('grupo', 'id_grupo');
            $table->foreignId('id_docente')->constrained('docente', 'id_docente');
            $table->timestamp('desde');
            $table->timestamp('hasta')->nullable();
            $table->uuid('asignado_por')->nullable();
            $table->timestamps();
            $table->index(['id_grupo', 'hasta']);
            $table->index(['id_docente', 'hasta']);
        });
    }

    public function down(): void
    {
        foreach (['asignacion_docente', 'horario_grupo', 'grupo', 'oferta_materia', 'ciclo_ajuste', 'ventana_ciclo', 'ciclo_carrera'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
