<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EXP-03 (esquema compartido), INS-02, DOC-01, DOC-02 y EXP-04.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscripcion', function (Blueprint $table) {
            $table->id('id_inscripcion');
            $table->foreignId('id_expediente')->constrained('expediente', 'id_expediente');
            $table->foreignId('id_ciclo_carrera')->constrained('ciclo_carrera', 'id_ciclo_carrera');
            $table->foreignId('id_materia')->constrained('materia', 'id_materia');
            $table->foreignId('id_grupo')->constrained('grupo', 'id_grupo');
            $table->string('estado', 20)->default('activa')->comment('activa | retirada');
            $table->timestamp('inscrito_en');
            $table->timestamp('retirado_en')->nullable();
            $table->uuid('id_usuario')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['id_expediente', 'id_ciclo_carrera', 'id_materia'], 'uq_inscripcion_materia_ciclo');
            $table->index(['id_grupo', 'estado']);
        });

        Schema::create('inscripcion_evento', function (Blueprint $table) {
            $table->id('id_inscripcion_evento');
            $table->foreignId('id_inscripcion')->constrained('inscripcion', 'id_inscripcion');
            $table->string('operacion', 20)->comment('alta | retiro | cambio_grupo | reinscripcion');
            $table->unsignedBigInteger('id_grupo_anterior')->nullable();
            $table->unsignedBigInteger('id_grupo_nuevo')->nullable();
            $table->string('motivo', 1000)->nullable();
            $table->uuid('id_usuario')->nullable();
            $table->timestamp('instante');
        });

        Schema::create('acta_grupo', function (Blueprint $table) {
            $table->id('id_acta');
            $table->foreignId('id_grupo')->unique()->constrained('grupo', 'id_grupo');
            $table->foreignId('id_escala')->constrained('escala_calificacion', 'id_escala');
            $table->string('estado', 20)->default('borrador')->comment('borrador | publicada');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('calculado_en')->nullable();
            $table->uuid('publicado_por')->nullable();
            $table->timestamp('publicado_en')->nullable();
            $table->timestamps();
        });

        Schema::create('evaluacion_grupo', function (Blueprint $table) {
            $table->id('id_evaluacion');
            $table->foreignId('id_grupo')->constrained('grupo', 'id_grupo');
            $table->foreignId('id_escala')->constrained('escala_calificacion', 'id_escala');
            $table->string('nombre', 100);
            $table->decimal('ponderacion', 8, 4);
            $table->unsignedSmallInteger('orden');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['id_grupo', 'orden']);
        });

        Schema::create('nota_evaluacion', function (Blueprint $table) {
            $table->id('id_nota');
            $table->foreignId('id_evaluacion')->constrained('evaluacion_grupo', 'id_evaluacion');
            $table->foreignId('id_inscripcion')->constrained('inscripcion', 'id_inscripcion');
            $table->decimal('valor', 8, 4)->nullable();
            $table->string('condicion', 20)->default('nota')->comment('nota | ausente');
            $table->uuid('id_usuario');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['id_evaluacion', 'id_inscripcion']);
        });

        Schema::create('resultado_final', function (Blueprint $table) {
            $table->id('id_resultado');
            $table->foreignId('id_inscripcion')->unique()->constrained('inscripcion', 'id_inscripcion');
            $table->foreignId('id_acta')->constrained('acta_grupo', 'id_acta');
            $table->decimal('nota_final', 8, 4);
            $table->string('condicion', 20)->comment('aprobada | reprobada');
            $table->decimal('creditos', 6, 2);
            $table->foreignId('id_escala')->constrained('escala_calificacion', 'id_escala');
            $table->unsignedInteger('version_politica');
            $table->json('detalle')->nullable()->comment('Contribuciones por evaluación');
            $table->timestamps();
        });

        Schema::create('autoridad_documental', function (Blueprint $table) {
            $table->id('id_autoridad');
            $table->string('clave', 20)->unique()->default('principal');
            $table->uuid('id_usuario');
            $table->string('nombre_firmante', 200);
            $table->string('cargo', 200);
            $table->unsignedInteger('version')->default(1);
            $table->uuid('asignado_por')->nullable();
            $table->timestamps();
        });

        Schema::create('autoridad_documental_historial', function (Blueprint $table) {
            $table->id('id_autoridad_historial');
            $table->uuid('id_usuario_anterior')->nullable();
            $table->uuid('id_usuario_nuevo');
            $table->string('cargo', 200);
            $table->unsignedInteger('version');
            $table->uuid('asignado_por')->nullable();
            $table->timestamp('instante');
        });

        Schema::create('documento_expediente', function (Blueprint $table) {
            $table->id('id_documento');
            $table->foreignId('id_expediente')->constrained('expediente', 'id_expediente');
            $table->string('tipo', 40)->comment('certificacion_notas | constancia_estudio');
            $table->string('folio', 40)->unique();
            $table->string('estado', 20)->default('pendiente')->comment('pendiente | emitido | fallido');
            $table->uuid('id_emisor');
            $table->string('clave_idempotencia', 128);
            $table->json('instantanea');
            $table->json('identidad_institucional');
            $table->uuid('id_usuario_autoridad')->nullable();
            $table->string('nombre_autoridad', 200)->nullable();
            $table->string('cargo_autoridad', 200)->nullable();
            $table->unsignedInteger('version_autoridad')->nullable();
            $table->string('version_plantilla', 20);
            $table->string('ruta_archivo', 500)->nullable();
            $table->unsignedSmallInteger('paginas')->nullable();
            $table->string('codigo_error', 60)->nullable();
            $table->string('mensaje_error', 500)->nullable();
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->timestamp('emitido_en')->nullable();
            $table->timestamps();
            $table->unique(['id_emisor', 'clave_idempotencia'], 'uq_documento_idempotencia');
        });
    }

    public function down(): void
    {
        foreach (['documento_expediente', 'autoridad_documental_historial', 'autoridad_documental', 'resultado_final', 'nota_evaluacion', 'evaluacion_grupo', 'acta_grupo', 'inscripcion_evento', 'inscripcion'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
