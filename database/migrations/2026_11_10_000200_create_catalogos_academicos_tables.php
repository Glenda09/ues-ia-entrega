<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GEN-01, GEN-02 y GEN-03: docentes, catálogos, gestores y pensums.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente', function (Blueprint $table) {
            $table->id('id_docente');
            $table->uuid('id_usuario')->unique();
            $table->string('codigo', 50)->unique();
            $table->string('nombre_academico', 200);
            $table->string('estado', 20)->default('activo')->comment('activo | inactivo');
            $table->unsignedInteger('version')->default(1);
            $table->uuid('creado_por')->nullable();
            $table->timestamps();
            $table->foreign('id_usuario')->references('id_usuario')->on('auth_usuario');
        });

        foreach (['sede', 'facultad'] as $nombre) {
            Schema::create($nombre, function (Blueprint $table) use ($nombre) {
                $table->id('id_'.$nombre);
                $table->string('codigo', 50)->unique();
                $table->string('nombre', 200);
                $table->string('estado', 20)->default('activa');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
            });
        }

        Schema::create('carrera', function (Blueprint $table) {
            $table->id('id_carrera');
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->text('descripcion_publica')->nullable();
            $table->string('clasificacion', 30)->default('general')->comment('general | profesorado');
            $table->foreignId('id_facultad')->nullable()->constrained('facultad', 'id_facultad');
            $table->string('estado', 20)->default('activa')->comment('activa | archivada');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('carrera_sede', function (Blueprint $table) {
            $table->foreignId('id_carrera')->constrained('carrera', 'id_carrera');
            $table->foreignId('id_sede')->constrained('sede', 'id_sede');
            $table->primary(['id_carrera', 'id_sede']);
        });

        Schema::create('materia', function (Blueprint $table) {
            $table->id('id_materia');
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->string('estado', 20)->default('activa');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('gestor_carrera', function (Blueprint $table) {
            $table->id('id_gestor_carrera');
            $table->uuid('id_usuario');
            $table->foreignId('id_carrera')->constrained('carrera', 'id_carrera');
            $table->uuid('asignado_por')->nullable();
            $table->timestamps();
            $table->unique(['id_usuario', 'id_carrera']);
            $table->foreign('id_usuario')->references('id_usuario')->on('auth_usuario');
        });

        Schema::create('pensum', function (Blueprint $table) {
            $table->id('id_pensum');
            $table->foreignId('id_carrera')->constrained('carrera', 'id_carrera');
            $table->string('version_curricular', 50);
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->string('estado', 20)->default('borrador')->comment('borrador | publicado | retirado');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('id_pensum_origen')->nullable();
            $table->uuid('publicado_por')->nullable();
            $table->timestamp('publicado_en')->nullable();
            $table->timestamps();
            $table->unique(['id_carrera', 'version_curricular']);
            $table->index(['id_carrera', 'estado']);
        });

        Schema::create('pensum_materia', function (Blueprint $table) {
            $table->id('id_pensum_materia');
            $table->foreignId('id_pensum')->constrained('pensum', 'id_pensum');
            $table->foreignId('id_materia')->constrained('materia', 'id_materia');
            $table->unsignedSmallInteger('nivel');
            $table->unsignedSmallInteger('orden')->default(1);
            $table->decimal('creditos', 6, 2);
            $table->boolean('obligatoria')->default(true);
            $table->unique(['id_pensum', 'id_materia']);
        });

        Schema::create('pensum_requisito', function (Blueprint $table) {
            $table->id('id_pensum_requisito');
            $table->foreignId('id_pensum')->constrained('pensum', 'id_pensum');
            $table->foreignId('id_materia')->constrained('materia', 'id_materia')->comment('Materia destino');
            $table->foreignId('id_materia_requisito')->constrained('materia', 'id_materia');
            $table->string('tipo', 20)->default('prerrequisito')->comment('prerrequisito | correquisito');
            $table->unique(['id_pensum', 'id_materia', 'id_materia_requisito']);
        });

        Schema::create('pensum_equivalencia', function (Blueprint $table) {
            $table->id('id_pensum_equivalencia');
            $table->foreignId('id_pensum')->constrained('pensum', 'id_pensum')->comment('Pensum destino');
            $table->foreignId('id_materia_origen')->constrained('materia', 'id_materia');
            $table->foreignId('id_materia_destino')->constrained('materia', 'id_materia');
            $table->unique(['id_pensum', 'id_materia_origen']);
        });
    }

    public function down(): void
    {
        foreach (['pensum_equivalencia', 'pensum_requisito', 'pensum_materia', 'pensum', 'gestor_carrera', 'materia', 'carrera_sede', 'carrera', 'facultad', 'sede', 'docente'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
