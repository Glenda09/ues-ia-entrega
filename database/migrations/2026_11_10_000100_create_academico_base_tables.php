<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BASE-01 y BASE-02: auditoría de dominio, idempotencia, configuración versionada y escalas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academico_auditoria', function (Blueprint $table) {
            $table->id('id_auditoria');
            $table->uuid('id_usuario')->nullable()->comment('Actor; null para procesos de sistema o públicos');
            $table->string('operacion', 120)->comment('Operación de negocio');
            $table->string('entidad', 80);
            $table->string('id_entidad', 80)->nullable();
            $table->string('motivo', 1000)->nullable();
            $table->json('metadatos')->nullable()->comment('Metadatos mínimos de negocio, sin payload sensible');
            $table->timestamp('instante');
            $table->index(['entidad', 'id_entidad']);
            $table->index('id_usuario');
        });

        Schema::create('academico_idempotencia', function (Blueprint $table) {
            $table->id('id_idempotencia');
            $table->string('propietario', 120)->comment('usuario:<uuid> o sesion:<hash>');
            $table->string('operacion', 120);
            $table->string('recurso', 120);
            $table->string('clave', 128);
            $table->string('hash_cuerpo', 64);
            $table->unsignedSmallInteger('estado_http')->nullable();
            $table->json('respuesta')->nullable();
            $table->timestamps();
            $table->unique(['propietario', 'operacion', 'recurso', 'clave'], 'uq_idempotencia_alcance');
        });

        Schema::create('configuracion_academica', function (Blueprint $table) {
            $table->id('id_configuracion');
            $table->string('ambito', 30)->unique()->default('global');
            $table->unsignedInteger('version')->default(1)->comment('Versión de edición esperada');
            $table->json('valores');
            $table->timestamps();
        });

        Schema::create('configuracion_academica_version', function (Blueprint $table) {
            $table->id('id_configuracion_version');
            $table->foreignId('id_configuracion')->constrained('configuracion_academica', 'id_configuracion');
            $table->unsignedInteger('revision');
            $table->json('valores');
            $table->string('motivo', 1000);
            $table->uuid('id_usuario')->nullable();
            $table->timestamp('instante');
            $table->unique(['id_configuracion', 'revision']);
        });

        Schema::create('escala_calificacion', function (Blueprint $table) {
            $table->id('id_escala');
            $table->string('codigo', 50);
            $table->unsignedInteger('version_politica');
            $table->string('nombre', 200);
            $table->decimal('minimo', 8, 4);
            $table->decimal('maximo', 8, 4);
            $table->decimal('umbral_aprobacion', 8, 4);
            $table->unsignedTinyInteger('precision');
            $table->string('redondeo', 20)->comment('half_up | half_even | down');
            $table->decimal('ponderacion_total', 8, 4);
            $table->string('ausencia', 20)->comment('bloquear | minimo');
            $table->string('estado', 20)->default('activa');
            $table->uuid('creado_por')->nullable();
            $table->timestamps();
            $table->unique(['codigo', 'version_politica']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escala_calificacion');
        Schema::dropIfExists('configuracion_academica_version');
        Schema::dropIfExists('configuracion_academica');
        Schema::dropIfExists('academico_idempotencia');
        Schema::dropIfExists('academico_auditoria');
    }
};
