<?php

namespace App\Jobs\Academico;

use App\Models\Academico\EntregaAcceso;
use App\Services\App\PersonalizacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envía una entrega de acceso (credenciales, carnet, observación o corrección).
 *
 * Transporta solo el ID; el secreto se descifra en el worker y se elimina al enviarse.
 * Los logs conservan metadatos sin secretos.
 */
class EnviarEntregaAccesoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $idEntrega) {}

    public function handle(): void
    {
        $e = EntregaAcceso::find($this->idEntrega);
        if (! $e || $e->estado === 'enviada') {
            return;
        }
        $e->increment('intentos');
        $institucion = app(PersonalizacionService::class)->publica()['textos']['nombre'] ?: 'la universidad';
        $secreto = null;
        if ($e->secreto_cifrado) {
            if ($e->secreto_expira_en && $e->secreto_expira_en->isPast()) {
                $e->update(['estado' => 'fallida', 'ultimo_error' => 'Secreto vencido; requiere reenvío.', 'secreto_cifrado' => null]);

                return;
            }
            $secreto = Crypt::decryptString($e->secreto_cifrado);
        }
        [$asunto, $html] = $this->mensaje($e, $secreto, $institucion);

        try {
            Mail::html($html, fn ($m) => $m->to($e->destinatario)->subject($asunto));
            $e->update(['estado' => 'enviada', 'enviado_en' => now(), 'secreto_cifrado' => null, 'ultimo_error' => null]);
            Log::channel('mail')->info('Entrega académica enviada', ['id_entrega' => $e->id_entrega, 'tipo' => $e->tipo]);
        } catch (\Throwable $ex) {
            $e->update(['estado' => 'fallida', 'ultimo_error' => mb_substr($ex->getMessage(), 0, 500)]);
            Log::warning('Fallo de entrega académica', ['id_entrega' => $e->id_entrega, 'tipo' => $e->tipo]);
        }
    }

    /**
     * @return array{0:string,1:string}
     */
    private function mensaje(EntregaAcceso $e, ?string $secreto, string $institucion): array
    {
        $d = $e->datos ?? [];
        $url = rtrim((string) config('app.url'), '/');

        return match ($e->tipo) {
            'credenciales_nuevas' => ['Tu acceso estudiantil', sprintf(
                'Bienvenido(a) a %s.<br><br>Tu carnet es <b>%s</b> y tu contraseña temporal es <b>%s</b>.<br>Ingresa en <a href="%s/login">%s/login</a> con tu carnet. Deberás cambiar la contraseña en el primer acceso; vence el %s.',
                e($institucion), e($d['carnet'] ?? ''), e((string) $secreto), e($url), e($url), e($d['vence'] ?? '')
            )],
            'carnet_asignado' => ['Carnet asignado', sprintf(
                'Tu solicitud fue aceptada en %s.<br><br>Tu carnet es <b>%s</b>. Puedes ingresar con tu carnet o tu correo y tu contraseña actual en <a href="%s/login">%s/login</a>.',
                e($institucion), e($d['carnet'] ?? ''), e($url), e($url)
            )],
            'observacion' => ['Solicitud de ingreso observada', sprintf(
                'Tu solicitud %s tiene observaciones:<br><br><i>%s</i><br><br>Corrígela en: <a href="%s/admision/correccion#%s">enlace de corrección</a> (un solo uso, vence %s).',
                e($d['folio'] ?? ''), e($d['motivo'] ?? ''), e($url), e((string) $secreto), e($d['vence'] ?? '')
            )],
            'verificacion' => ['Código de verificación de correo', sprintf(
                'Tu código de verificación para el registro de admisión en %s es <b>%s</b>. Vence en 15 minutos.',
                e($institucion), e((string) $secreto)
            )],
            default => ['Notificación académica', e($d['mensaje'] ?? '')],
        };
    }
}
