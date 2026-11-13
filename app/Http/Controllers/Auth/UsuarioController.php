<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\App\Estado;
use App\Models\Auth\AuthUsuario;
use App\Models\Auth\AuthUsuarioPerfil;
use App\Services\App\PersonalizacionService;
use App\Services\Auth\PermisosEfectivosService;
use App\Services\Auth\TwoFactorService;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class UsuarioController extends Controller
{
    public function __construct(
        private EmailService $emailService = new EmailService,
        private TwoFactorService $twoFactorService = new TwoFactorService,
        private PermisosEfectivosService $permisosEfectivosService = new PermisosEfectivosService,
    ) {}

    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'query' => 'nullable|string|max:150',
                'created_at_desde' => 'nullable|date_format:Y-m-d',
                'created_at_hasta' => 'nullable|date_format:Y-m-d|after_or_equal:created_at_desde',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $query = AuthUsuario::query()
                ->with(['perfiles' => function ($q) {
                    $q->select('codigo', 'nombre');
                }])
                ->orderByDesc('created_at');

            if (! empty($validated['query'])) {
                $term = strtolower($validated['query']);
                $query->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(email) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(username) LIKE ?', ['%'.$term.'%']);
                });
            }

            $desde = ! empty($validated['created_at_desde']) ? Carbon::createFromFormat('Y-m-d', $validated['created_at_desde'])->startOfDay() : null;
            $hasta = ! empty($validated['created_at_hasta']) ? Carbon::createFromFormat('Y-m-d', $validated['created_at_hasta'])->endOfDay() : null;

            if ($desde && $hasta) {
                $query->whereBetween('created_at', [$desde, $hasta]);
            } elseif ($desde) {
                $query->where('created_at', '>=', $desde);
            } elseif ($hasta) {
                $query->where('created_at', '<=', $hasta);
            }

            $paginator = $query->paginate($perPage);

            $data = $paginator->getCollection()->map(function ($u) {
                return [
                    'id_usuario' => $u->id_usuario,
                    'username' => $u->username,
                    'email' => $u->email,
                    'id_estado' => $u->id_estado,
                    'estado' => [
                        'codigo' => $u->estado?->codigo,
                        'nombre_estado' => $u->estado?->nombre_estado,
                        'label' => $u->estado?->label,
                        'color' => $u->estado?->color,
                        'icono' => $u->estado?->icono,
                        'activo' => $u->estado?->activo,
                    ],
                    'perfiles' => $u->perfiles->map(fn ($p) => [
                        'codigo' => $p->codigo,
                        'nombre' => $p->nombre,
                    ]),
                    'created_at' => $u->created_at,
                    'updated_at' => $u->updated_at,
                ];
            });

            return response()->json([
                'data' => $data,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error listando usuarios', ['err' => $e->getMessage()]);

            return response()->json(['message' => 'Error al listar usuarios'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function mostrar(string $id_usuario)
    {
        try {
            $user = AuthUsuario::query()->findOrFail($id_usuario);

            return response()->json([
                'id_usuario' => $user->id_usuario,
                'username' => $user->username,
                'email' => $user->email,
                'id_estado' => $user->id_estado,
                'perfiles' => $user->perfiles->map(fn ($p) => [
                    'codigo' => $p->codigo,
                    'nombre' => $p->nombre,
                ]),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Error mostrando usuario', ['err' => $e->getMessage()]);

            return response()->json(['message' => 'Error al mostrar usuario'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function actualizar(Request $request, string $id_usuario)
    {
        try {
            $validated = $request->validate([
                'username' => 'sometimes|required|string|max:150',
                'email' => 'sometimes|required|email|unique:auth_usuario,email,'.$id_usuario.',id_usuario',
                'perfiles' => 'sometimes|required|array|min:1',
                'perfiles.*' => 'string|exists:seguridad_perfil,codigo',
            ]);

            $user = AuthUsuario::query()->findOrFail($id_usuario);
            $user->fill($validated);
            $user->save();

            if (isset($validated['perfiles'])) {
                $now = now();
                $existingProfiles = $user->perfiles->pluck('codigo')->toArray();
                $newProfiles = $validated['perfiles'];

                // Perfiles a eliminar
                $toDelete = array_diff($existingProfiles, $newProfiles);
                if (! empty($toDelete)) {
                    AuthUsuarioPerfil::where('id_usuario', $user->id_usuario)
                        ->whereIn('codigo_perfil', $toDelete)
                        ->delete();
                }

                // Perfiles a agregar
                $toAdd = array_diff($newProfiles, $existingProfiles);
                foreach ($toAdd as $codigoPerfil) {
                    AuthUsuarioPerfil::create([
                        'id_usuario' => $user->id_usuario,
                        'codigo_perfil' => $codigoPerfil,
                        'creado_por' => $request->attributes->get('auth_user')?->id_usuario,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $this->permisosEfectivosService->invalidarUsuario($user->id_usuario);
            }

            return response()->json(['message' => 'Usuario actualizado correctamente']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            Log::error('Error actualizando usuario', ['err' => $e->getMessage()]);

            return response()->json(['message' => 'Error al actualizar usuario'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function guardar(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'username' => 'required|string|max:150',
                'email' => 'required|email|unique:auth_usuario,email',
                'perfiles' => 'required|array|min:1',
                'perfiles.*' => 'string|exists:seguridad_perfil,codigo',
            ]);

            $passwordPlain = substr(bin2hex(random_bytes(8)), 0, 10);

            $user = AuthUsuario::create([
                'username' => $validated['username'],
                'email' => strtolower($validated['email']),
                'password' => Hash::make($passwordPlain),
                'id_estado' => 'ACTIVO',
                'creado_por' => $request->attributes->get('auth_user')?->id_usuario,
            ]);

            $now = now();
            foreach ($validated['perfiles'] as $codigoPerfil) {
                AuthUsuarioPerfil::create([
                    'id_usuario' => $user->id_usuario,
                    'codigo_perfil' => $codigoPerfil,
                    'creado_por' => $request->attributes->get('auth_user')?->id_usuario,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $subject = 'Tu usuario ha sido creado';
            $html = sprintf(
                'Hola %s!<br><br>Se ha creado tu cuenta. Tu contraseña inicial es: <b>%s</b><br><br>Equipo de %s',
                e($user->username ?? ''),
                e($passwordPlain),
                e(app(PersonalizacionService::class)->publica()['textos']['nombre'] ?? '')
            );
            $this->emailService->sendHtmlEmail($user->email, $subject, $html);

            DB::commit();

            return response()->json([
                'message' => 'Usuario creado correctamente',
                'data' => [
                    'id_usuario' => $user->id_usuario,
                ],
            ], Response::HTTP_CREATED);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json(['message' => 'Datos inválidos', 'errors' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error creando usuario', ['err' => $e->getMessage()]);

            return response()->json(['message' => 'Error al crear usuario'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function restablecerPassword(string $id_usuario)
    {
        $user = AuthUsuario::find($id_usuario);
        if (! $user) {
            return response()->json(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $newPassword = substr(bin2hex(random_bytes(8)), 0, 10);
        $user->password = Hash::make($newPassword);
        $user->save();

        // Enviar correo con nueva contraseña
        $subject = 'Tu contraseña ha sido restablecida';
        $html = sprintf(
            'Hola %s!<br><br>Tu nueva contraseña es: <b>%s</b><br><br>Equipo de %s',
            e($user->username ?? ''),
            e($newPassword),
            e(app(PersonalizacionService::class)->publica()['textos']['nombre'] ?? '')
        );
        $this->emailService->sendHtmlEmail($user->email, $subject, $html);

        return response()->json(['message' => 'Contraseña restablecida correctamente']);
    }

    public function alternarEstado(string $id_usuario)
    {
        $user = AuthUsuario::find($id_usuario);
        if (! $user) {
            return response()->json(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $user->id_estado = $user->id_estado === Estado::ACTIVO ? Estado::INACTIVO : Estado::ACTIVO;
        $user->save();
        $this->permisosEfectivosService->invalidarUsuario($user->id_usuario);

        return response()->json(['message' => 'Estado de usuario actualizado correctamente']);
    }

    public function alternar2FAEstado(string $id_usuario)
    {
        try {
            $user = AuthUsuario::query()->findOrFail($id_usuario);

            $current = (bool) ($user->{'tfa_activo'} ?? false);

            if (! $current) {
                return response()->json([
                    'message' => 'La activación debe realizarse desde la cuenta del usuario',
                    'data' => ['tfa_activo' => false],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->twoFactorService->clear($user);

            $enabled = false;
            $message = 'Doble factor deshabilitado';

            return response()->json([
                'message' => $message,
                'data' => [
                    'tfa_activo' => $enabled,
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Error alternando estado de 2FA', ['err' => $e->getMessage()]);

            return response()->json(['message' => 'Error al actualizar doble factor'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
