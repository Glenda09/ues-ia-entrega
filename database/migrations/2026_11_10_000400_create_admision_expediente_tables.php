<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CAR-03, EXP-01 y EXP-02: convocatorias, formulario, solicitudes, admisión, estudiante, carnet y expediente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_usuario', function (Blueprint $table) {
            $table->boolean('password_cambio_requerido')->default(false)->comment('EST-01: cambio inicial obligatorio');
            $table->timestamp('password_temporal_expira_en')->nullable()->comment('EST-01: vencimiento de la contraseña temporal');
        });

        // Registro único de identificadores de acceso (email y username/carnet normalizados).
        Schema::create('auth_identificador', function (Blueprint $table) {
            $table->string('clave', 255)->primary();
            $table->uuid('id_usuario');
            $table->timestamps();
            $table->index('id_usuario');
            $table->foreign('id_usuario')->references('id_usuario')->on('auth_usuario');
        });

        Schema::create('convocatoria_ingreso', function (Blueprint $table) {
            $table->id('id_convocatoria');
            $table->foreignId('id_carrera')->constrained('carrera', 'id_carrera');
            $table->string('codigo', 50);
            $table->string('titulo_publico', 200);
            $table->text('descripcion_publica')->nullable();
            $table->timestamp('inicio')->nullable();
            $table->timestamp('fin')->nullable();
            $table->string('estado', 20)->default('borrador')->comment('borrador | publicada | cerrada');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('version_requisitos')->default(1)->comment('Versión vigente/publicada de requisitos');
            $table->boolean('requiere_pago')->default(true);
            $table->decimal('monto_pago', 10, 2)->nullable();
            $table->uuid('publicado_por')->nullable();
            $table->timestamp('publicado_en')->nullable();
            $table->timestamps();
            $table->unique(['id_carrera', 'codigo']);
        });

        Schema::create('requisito_ingreso', function (Blueprint $table) {
            $table->id('id_requisito');
            $table->foreignId('id_convocatoria')->constrained('convocatoria_ingreso', 'id_convocatoria');
            $table->unsignedInteger('version_requisitos');
            $table->string('codigo', 50);
            $table->string('tipo', 20)->comment('campo | anexo');
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->boolean('obligatorio')->default(true);
            $table->string('etapa', 20)->default('envio')->comment('envio | aceptacion');
            $table->json('condicion')->nullable();
            $table->unsignedBigInteger('max_bytes')->nullable();
            $table->json('tipos_permitidos')->nullable();
            $table->string('origen', 200)->nullable()->comment('Procedencia del requisito (nota D03)');
            $table->unique(['id_convocatoria', 'version_requisitos', 'codigo'], 'uq_requisito_version_codigo');
        });

        Schema::create('carrera_oferta_admision', function (Blueprint $table) {
            $table->id('id_oferta_admision');
            $table->foreignId('id_convocatoria')->constrained('convocatoria_ingreso', 'id_convocatoria');
            $table->foreignId('id_carrera')->constrained('carrera', 'id_carrera');
            $table->foreignId('id_sede')->constrained('sede', 'id_sede');
            $table->foreignId('id_facultad')->constrained('facultad', 'id_facultad');
            $table->string('modalidad_estudio', 30)->comment('presencial | distancia');
            $table->string('modalidad_ingreso', 30)->comment('general | especial');
            $table->unique(['id_convocatoria', 'id_carrera', 'id_sede', 'id_facultad', 'modalidad_estudio', 'modalidad_ingreso'], 'uq_oferta_admision');
        });

        Schema::create('verificacion_correo_ingreso', function (Blueprint $table) {
            $table->id('id_verificacion');
            $table->string('email', 255);
            $table->string('codigo_hash', 64);
            $table->timestamp('expira_en');
            $table->timestamp('consumido_en')->nullable();
            $table->timestamp('revocado_en')->nullable();
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['email', 'created_at']);
        });

        Schema::create('archivo_ingreso', function (Blueprint $table) {
            $table->id('id_archivo');
            $table->uuid('id_usuario');
            $table->unsignedBigInteger('id_convocatoria');
            $table->string('codigo_requisito', 50);
            $table->string('disco', 30);
            $table->string('ruta', 500);
            $table->string('nombre_seguro', 200);
            $table->string('mime', 100);
            $table->unsignedBigInteger('tamano');
            $table->string('hash', 64);
            $table->timestamps();
            $table->index(['id_usuario', 'id_convocatoria']);
        });

        Schema::create('borrador_ingreso', function (Blueprint $table) {
            $table->id('id_borrador');
            $table->uuid('id_usuario');
            $table->foreignId('id_convocatoria')->constrained('convocatoria_ingreso', 'id_convocatoria');
            $table->unsignedTinyInteger('etapa')->default(1);
            $table->json('datos');
            $table->json('anexos')->nullable()->comment('codigo_requisito => id_archivo');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['id_usuario', 'id_convocatoria']);
            $table->foreign('id_usuario')->references('id_usuario')->on('auth_usuario');
        });

        Schema::create('orden_pago_ingreso', function (Blueprint $table) {
            $table->id('id_orden_pago');
            $table->uuid('id_usuario');
            $table->foreignId('id_convocatoria')->constrained('convocatoria_ingreso', 'id_convocatoria');
            $table->string('folio', 40)->unique();
            $table->decimal('monto', 10, 2);
            $table->string('estado', 30)->default('pendiente')->comment('pendiente | comprobante_recibido | validado | observado');
            $table->string('referencia_comprobante', 100)->nullable();
            $table->unsignedBigInteger('id_archivo_comprobante')->nullable();
            $table->string('motivo_observacion', 1000)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['id_usuario', 'id_convocatoria']);
        });

        Schema::create('orden_pago_evento', function (Blueprint $table) {
            $table->id('id_orden_pago_evento');
            $table->foreignId('id_orden_pago')->constrained('orden_pago_ingreso', 'id_orden_pago');
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30);
            $table->unsignedBigInteger('id_archivo')->nullable();
            $table->string('motivo', 1000)->nullable();
            $table->uuid('id_usuario')->nullable();
            $table->timestamp('instante');
        });

        Schema::create('solicitud_ingreso', function (Blueprint $table) {
            $table->id('id_solicitud');
            $table->foreignId('id_convocatoria')->constrained('convocatoria_ingreso', 'id_convocatoria');
            $table->uuid('id_usuario');
            $table->string('folio', 40)->unique();
            $table->unsignedInteger('version_requisitos');
            $table->string('estado', 20)->default('recibida')->comment('recibida | observada | aceptada');
            $table->string('nombres', 200);
            $table->string('primer_apellido', 100);
            $table->string('segundo_apellido', 100)->nullable();
            $table->string('email', 255);
            $table->date('fecha_nacimiento');
            $table->string('nacionalidad', 60);
            $table->string('documento_tipo', 20)->nullable();
            $table->string('documento_numero', 50)->nullable();
            $table->foreignId('id_carrera')->constrained('carrera', 'id_carrera');
            $table->unsignedBigInteger('id_sede');
            $table->unsignedBigInteger('id_facultad');
            $table->string('modalidad_estudio', 30);
            $table->string('modalidad_ingreso', 30);
            $table->unsignedInteger('revision_actual')->default(1);
            $table->string('motivo_observacion', 1000)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['id_usuario', 'id_convocatoria']);
            $table->index(['id_carrera', 'estado']);
        });

        Schema::create('solicitud_ingreso_revision', function (Blueprint $table) {
            $table->id('id_revision');
            $table->foreignId('id_solicitud')->constrained('solicitud_ingreso', 'id_solicitud');
            $table->unsignedInteger('revision');
            $table->json('datos');
            $table->unsignedTinyInteger('edad');
            $table->timestamp('instante');
            $table->unique(['id_solicitud', 'revision']);
        });

        Schema::create('solicitud_anexo', function (Blueprint $table) {
            $table->id('id_anexo');
            $table->foreignId('id_revision')->constrained('solicitud_ingreso_revision', 'id_revision');
            $table->string('codigo_requisito', 50);
            $table->foreignId('id_archivo')->constrained('archivo_ingreso', 'id_archivo');
            $table->unique(['id_revision', 'codigo_requisito']);
        });

        Schema::create('estudiante', function (Blueprint $table) {
            $table->id('id_estudiante');
            $table->uuid('id_usuario')->unique();
            $table->string('carnet', 20)->unique();
            $table->string('carnet_prefijo', 2);
            $table->unsignedSmallInteger('carnet_anio');
            $table->unsignedInteger('carnet_correlativo');
            $table->string('carnet_regla', 100)->comment('Regla aplicada, p. ej. segundo apellido ausente => X');
            $table->string('nombres', 200);
            $table->string('primer_apellido', 100);
            $table->string('segundo_apellido', 100)->nullable();
            $table->string('nombre_uso', 200)->nullable();
            $table->string('email', 255);
            $table->string('telefono', 30)->nullable();
            $table->string('direccion', 300)->nullable();
            $table->string('contacto_emergencia', 300)->nullable();
            $table->string('documento_tipo', 20)->nullable();
            $table->string('documento_numero', 50)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['carnet_prefijo', 'carnet_anio', 'carnet_correlativo'], 'uq_carnet_componentes');
            $table->index(['documento_tipo', 'documento_numero']);
            $table->foreign('id_usuario')->references('id_usuario')->on('auth_usuario');
        });

        Schema::create('carnet_correlativo', function (Blueprint $table) {
            $table->id('id_carnet_correlativo');
            $table->string('prefijo', 2);
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('ultimo')->default(0);
            $table->unique(['prefijo', 'anio']);
        });

        Schema::create('expediente', function (Blueprint $table) {
            $table->id('id_expediente');
            $table->foreignId('id_estudiante')->constrained('estudiante', 'id_estudiante');
            $table->foreignId('id_carrera')->constrained('carrera', 'id_carrera');
            $table->foreignId('id_pensum')->constrained('pensum', 'id_pensum');
            $table->unsignedBigInteger('id_solicitud')->nullable()->unique();
            $table->date('fecha_ingreso');
            $table->string('estado', 20)->default('ACTIVO');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['id_estudiante', 'id_carrera']);
            $table->index(['id_carrera', 'estado']);
        });

        Schema::create('expediente_evento', function (Blueprint $table) {
            $table->id('id_expediente_evento');
            $table->foreignId('id_expediente')->constrained('expediente', 'id_expediente');
            $table->string('tipo', 30)->comment('admision | estado | datos');
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20)->nullable();
            $table->json('cambios')->nullable();
            $table->string('motivo', 1000)->nullable();
            $table->uuid('id_usuario')->nullable();
            $table->timestamp('instante');
        });

        Schema::create('decision_ingreso', function (Blueprint $table) {
            $table->id('id_decision');
            $table->foreignId('id_solicitud')->constrained('solicitud_ingreso', 'id_solicitud');
            $table->unsignedInteger('revision');
            $table->string('estado', 20);
            $table->string('motivo', 1000)->nullable();
            $table->uuid('id_usuario');
            $table->foreignId('id_expediente')->nullable()->constrained('expediente', 'id_expediente');
            $table->timestamp('instante');
        });

        Schema::create('correccion_ingreso', function (Blueprint $table) {
            $table->id('id_correccion');
            $table->foreignId('id_solicitud')->constrained('solicitud_ingreso', 'id_solicitud');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expira_en');
            $table->timestamp('consumido_en')->nullable();
            $table->timestamp('revocado_en')->nullable();
            $table->timestamps();
        });

        Schema::create('entrega_acceso', function (Blueprint $table) {
            $table->id('id_entrega');
            $table->uuid('id_usuario');
            $table->unsignedBigInteger('id_expediente')->nullable();
            $table->string('tipo', 40)->comment('credenciales_nuevas | carnet_asignado | observacion | correccion');
            $table->string('destinatario', 255);
            $table->string('estado', 20)->default('pendiente')->comment('pendiente | enviada | fallida');
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->text('secreto_cifrado')->nullable();
            $table->timestamp('secreto_expira_en')->nullable();
            $table->json('datos')->nullable()->comment('Metadatos no secretos para el mensaje');
            $table->string('ultimo_error', 500)->nullable();
            $table->timestamp('enviado_en')->nullable();
            $table->timestamps();
            $table->index(['id_usuario', 'tipo']);
        });

        DB::table('auth_usuario')->orderBy('id_usuario')->get(['id_usuario', 'email', 'username'])->each(function ($u) {
            foreach (array_unique(array_filter([mb_strtolower(trim((string) $u->email)), mb_strtolower(trim((string) $u->username))])) as $clave) {
                DB::table('auth_identificador')->insertOrIgnore(['clave' => $clave, 'id_usuario' => $u->id_usuario, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        foreach (['entrega_acceso', 'correccion_ingreso', 'decision_ingreso', 'expediente_evento', 'expediente', 'carnet_correlativo', 'estudiante', 'solicitud_anexo', 'solicitud_ingreso_revision', 'solicitud_ingreso', 'orden_pago_evento', 'orden_pago_ingreso', 'borrador_ingreso', 'archivo_ingreso', 'verificacion_correo_ingreso', 'carrera_oferta_admision', 'requisito_ingreso', 'convocatoria_ingreso', 'auth_identificador'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('auth_usuario', function (Blueprint $table) {
            $table->dropColumn(['password_cambio_requerido', 'password_temporal_expira_en']);
        });
    }
};
