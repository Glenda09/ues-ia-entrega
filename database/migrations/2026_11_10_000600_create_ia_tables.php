<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IA-BASE-01 e IAA-01: conversaciones, ejecuciones, presupuesto, tarifas, sesiones públicas y fuentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_sesion_publica', function (Blueprint $table) {
            $table->id('id_sesion_publica');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expira_en');
            $table->timestamps();
        });

        Schema::create('ia_conversacion', function (Blueprint $table) {
            $table->uuid('id_conversacion')->primary();
            $table->string('propietario_tipo', 20)->comment('usuario | sesion_publica');
            $table->string('propietario_id', 64);
            $table->string('agente', 20)->comment('aspirantes | estudiantes');
            $table->unsignedBigInteger('id_expediente')->nullable();
            $table->string('titulo', 200)->nullable();
            $table->timestamps();
            $table->index(['propietario_tipo', 'propietario_id', 'agente', 'id_expediente'], 'idx_ia_conv_propietario');
        });

        Schema::create('ia_mensaje', function (Blueprint $table) {
            $table->id('id_mensaje');
            $table->uuid('id_conversacion');
            $table->unsignedInteger('secuencia');
            $table->string('rol', 20)->comment('user | assistant');
            $table->text('contenido');
            $table->json('referencias')->nullable();
            $table->string('estado', 20)->default('entregado');
            $table->timestamps();
            $table->unique(['id_conversacion', 'secuencia']);
            $table->foreign('id_conversacion')->references('id_conversacion')->on('ia_conversacion');
        });

        Schema::create('ia_tarifa', function (Blueprint $table) {
            $table->id('id_tarifa');
            $table->string('proveedor', 40);
            $table->string('modelo', 100);
            $table->unsignedInteger('version');
            $table->decimal('precio_entrada_mtok', 24, 12);
            $table->decimal('precio_entrada_cache_mtok', 24, 12)->default(0);
            $table->decimal('precio_salida_mtok', 24, 12);
            $table->string('fuente', 300)->nullable();
            $table->timestamp('vigente_desde');
            $table->uuid('creado_por')->nullable();
            $table->timestamps();
            $table->unique(['proveedor', 'modelo', 'version']);
        });

        Schema::create('ia_presupuesto', function (Blueprint $table) {
            $table->id('id_presupuesto');
            $table->string('ambito', 20)->unique()->comment('real | simulacion');
            $table->decimal('gastado', 24, 12)->default(0);
            $table->decimal('reservado', 24, 12)->default(0);
            $table->boolean('incidente_tarifa')->default(false);
            $table->timestamps();
        });

        Schema::create('ia_ejecucion', function (Blueprint $table) {
            $table->id('id_ejecucion');
            $table->string('propietario', 120);
            $table->string('agente', 20);
            $table->unsignedBigInteger('id_expediente')->nullable();
            $table->string('clave_turno', 128);
            $table->string('hash_cuerpo', 64);
            $table->uuid('id_conversacion')->nullable();
            $table->string('proveedor', 40);
            $table->string('modelo', 100);
            $table->boolean('simulado')->default(true);
            $table->string('estado', 20)->default('reservada')->comment('reservada | enviada | liquidada | incierta | fallida | liberada');
            $table->unsignedSmallInteger('inferencias')->default(0);
            $table->unsignedInteger('tokens_entrada')->default(0);
            $table->unsignedInteger('tokens_salida')->default(0);
            $table->decimal('costo_reservado', 24, 12)->default(0);
            $table->decimal('costo_real', 24, 12)->default(0);
            $table->unsignedBigInteger('id_tarifa')->nullable();
            $table->string('id_respuesta_proveedor', 200)->nullable();
            $table->json('resultado')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
            $table->unique(['propietario', 'agente', 'id_expediente', 'clave_turno'], 'uq_ia_turno');
        });

        Schema::create('ia_movimiento_gasto', function (Blueprint $table) {
            $table->id('id_movimiento');
            $table->foreignId('id_ejecucion')->constrained('ia_ejecucion', 'id_ejecucion');
            $table->string('ambito', 20);
            $table->string('tipo', 20)->comment('reserva | liquidacion | liberacion | ajuste');
            $table->decimal('monto', 24, 12);
            $table->string('clave', 150)->unique();
            $table->timestamps();
        });

        Schema::create('ia_fuente', function (Blueprint $table) {
            $table->id('id_fuente');
            $table->foreignId('id_carrera')->constrained('carrera', 'id_carrera');
            $table->string('tipo', 30)->comment('carrera | pensum | convocatoria | requisitos');
            $table->unsignedBigInteger('id_origen');
            $table->unsignedInteger('version');
            $table->string('titulo', 200);
            $table->text('contenido');
            $table->string('url', 300);
            $table->string('hash_origen', 64);
            $table->string('estado', 20)->default('publicada')->comment('publicada | retirada');
            $table->timestamp('vigente_hasta')->nullable();
            $table->uuid('publicado_por')->nullable();
            $table->timestamp('publicado_en')->nullable();
            $table->uuid('retirado_por')->nullable();
            $table->timestamp('retirado_en')->nullable();
            $table->timestamps();
            $table->unique(['tipo', 'id_origen', 'version']);
            $table->index(['id_carrera', 'estado']);
        });
    }

    public function down(): void
    {
        foreach (['ia_fuente', 'ia_movimiento_gasto', 'ia_ejecucion', 'ia_presupuesto', 'ia_tarifa', 'ia_mensaje', 'ia_conversacion', 'ia_sesion_publica'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
