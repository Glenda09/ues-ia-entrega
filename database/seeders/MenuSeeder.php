<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Seguridad\SeguridadMenuOpcion;
use App\Models\Seguridad\SeguridadMenuOpcionPermiso;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menuOptions = $this->opciones();

        foreach ($menuOptions as $option) {
            $exists = SeguridadMenuOpcion::where('id_modulo', $option['id_modulo'])
                ->where('ruta', $option['ruta'])
                ->first();

            $idOption = $exists->id_opcion ?? ($option['id_opcion'] ?? Str::uuid());
            $childrens = $option['childrens'] ?? [];
            $permissions = $option['permisos'] ?? [];
            unset($option['childrens']);
            unset($option['permisos']);

            if ($exists) {
                $exists->update($option);
            } else {
                SeguridadMenuOpcion::create([
                    'id_opcion' => $idOption,
                    ...$option,
                ]);
            }

            if (!empty($childrens)) {
                $this->createChildrensRoutes($childrens, $idOption);
            } elseif ($exists) {
                $this->removeChildrensRoutes($idOption);
            }

            $this->validatePermissions($idOption, $permissions);
        }
    }

    /**
     * Opciones de menú a sincronizar.
     */
    protected function opciones(): array
    {
        return include __DIR__ . '/data/menu.php';
    }

    protected function createChildrensRoutes($childrens, $idParentOption)
    {
        foreach ($childrens as $child) {
            $exists = SeguridadMenuOpcion::where('id_modulo', $child['id_modulo'])
                ->where('ruta', $child['ruta'])
                ->first();

            $idOption = $exists->id_opcion ?? ($child['id_opcion'] ?? Str::uuid());
            $permisos = $child['permisos'] ?? [];
            unset($child['permisos']);

            if ($exists) {
                $exists->update([
                    'id_opcion_padre' => $idParentOption,
                    ...$child,
                ]);
            } else {
                SeguridadMenuOpcion::create([
                    'id_opcion' => $idOption,
                    'id_opcion_padre' => $idParentOption,
                    ...$child,
                ]);
            }

            $this->validatePermissions($idOption, $permisos);
        }
    }

    protected function removeChildrensRoutes($idParentOption)
    {
        SeguridadMenuOpcion::where('id_opcion_padre', $idParentOption)->update(['id_opcion_padre' => null]);
    }

    protected function validatePermissions($idOption, $permissions)
    {
        // Eliminar permisos que ya no existen en el array
        SeguridadMenuOpcionPermiso::where('id_opcion', $idOption)
            ->whereNotIn('id_permiso', $permissions)
            ->delete();

        // Agregar nuevos permisos, si no existen
        foreach ($permissions as $permission) {
            $exists = SeguridadMenuOpcionPermiso::where('id_opcion', $idOption)
                ->where('id_permiso', $permission)
                ->first();

            if (!$exists) {
                SeguridadMenuOpcionPermiso::create([
                    'id_opcion' => $idOption,
                    'id_permiso' => $permission,
                    'creado_por' => DatabaseSeeder::ID_USUARIO_ADMINISTRADOR,
                ]);
            }
        }
    }
}
