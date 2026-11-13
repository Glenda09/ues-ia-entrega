<?php

/**
 * Prepara las cuentas de aspirante que usa la prueba de estrés del formulario de inscripción.
 *
 * Crea (o reactiva) aspirante.stress.1@ues.test … aspirante.stress.N@ues.test con el perfil
 * academico_aspirante, correo verificado y la contraseña indicada. Es idempotente: si la cuenta
 * existe, actualiza la contraseña y asegura el perfil. Con --reiniciar elimina los borradores y
 * órdenes de pago de esas cuentas en la convocatoria para empezar desde version=0.
 *
 * Uso (dentro del contenedor, desde app/):
 *   php stress/preparar-cuentas.php --cantidad=50 --password=Stress2026! [--convocatoria=1] [--reiniciar]
 *
 * Solo para entornos de desarrollo o QA: rechaza APP_ENV=production.
 */

use App\Models\Auth\AuthUsuario;
use App\Services\Academico\CatalogoService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$opciones = getopt('', ['cantidad::', 'password::', 'convocatoria::', 'reiniciar']);
$cantidad = (int) ($opciones['cantidad'] ?? 50);
$password = (string) ($opciones['password'] ?? 'Stress2026!');
$convocatoria = (int) ($opciones['convocatoria'] ?? 1);
$reiniciar = array_key_exists('reiniciar', $opciones);

if (app()->environment('production')) {
    fwrite(STDERR, "Rechazado: APP_ENV=production. Ejecuta la prueba de estrés en desarrollo o QA.\n");
    exit(1);
}
if ($cantidad < 1 || $cantidad > 1000) {
    fwrite(STDERR, "--cantidad debe estar entre 1 y 1000.\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "--password debe tener al menos 8 caracteres.\n");
    exit(1);
}

$admin = DatabaseSeeder::ID_USUARIO_ADMINISTRADOR;
$catalogo = app(CatalogoService::class);
$ids = [];

for ($i = 1; $i <= $cantidad; $i++) {
    $email = "aspirante.stress.{$i}@ues.test";
    $ids[] = DB::transaction(function () use ($email, $password, $admin, $catalogo) {
        $u = AuthUsuario::query()->firstOrNew(['email' => $email]);
        $u->fill([
            'username' => $email,
            'password' => $password,
            'fecha_verificacion' => $u->fecha_verificacion ?? now(),
            'id_estado' => 'ACTIVO',
            'tfa_activo' => false,
        ]);
        $u->creado_por ??= $admin;
        $u->password_cambio_requerido = false;
        $u->save();
        DB::table('auth_identificador')->insertOrIgnore(['clave' => $email, 'id_usuario' => $u->id_usuario, 'created_at' => now(), 'updated_at' => now()]);
        $catalogo->asegurarPerfil($u->id_usuario, 'academico_aspirante', $admin);

        return $u->id_usuario;
    });
}

$borrados = ['borradores' => 0, 'ordenes' => 0];
if ($reiniciar) {
    DB::transaction(function () use ($ids, $convocatoria, &$borrados) {
        $ordenes = DB::table('orden_pago_ingreso')->whereIn('id_usuario', $ids)->where('id_convocatoria', $convocatoria)->pluck('id_orden_pago');
        DB::table('orden_pago_evento')->whereIn('id_orden_pago', $ordenes)->delete();
        $borrados['ordenes'] = DB::table('orden_pago_ingreso')->whereIn('id_orden_pago', $ordenes)->delete();
        $borrados['borradores'] = DB::table('borrador_ingreso')->whereIn('id_usuario', $ids)->where('id_convocatoria', $convocatoria)->delete();
    });
}

$aviso = config('auth.two_factor_enforce_all') ? ' AVISO: AUTH_2FA_ENFORCE_ALL=true; el login devolverá 202 y la prueba fallará.' : '';
$limite = (int) config('app.api_rate_limit', 60);
$avisoLimite = $limite < 1200 ? " AVISO: APP_API_RATE_LIMIT={$limite}/min por IP; 15 peticiones/s requieren al menos 900 (recomendado 1200 o más)." : '';

echo "Cuentas listas: aspirante.stress.1..{$cantidad}@ues.test (perfil academico_aspirante).";
if ($reiniciar) {
    echo " Reiniciados en convocatoria {$convocatoria}: {$borrados['borradores']} borradores, {$borrados['ordenes']} órdenes de pago.";
}
echo $aviso.$avisoLimite.PHP_EOL;
