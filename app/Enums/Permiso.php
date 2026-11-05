<?php

namespace App\Enums;

enum Permiso: string
{
    case MemorandosConsultar = 'memorandos.consultar';
    case MemorandosRegistrar = 'memorandos.registrar';
    case MemorandosEditar = 'memorandos.editar';
    case MemorandosDescargar = 'memorandos.descargar';

    case GerenciasConsultar = 'gerencias.consultar';
    case GerenciasCrear = 'gerencias.crear';
    case GerenciasEditar = 'gerencias.editar';
    case GerenciasActivar = 'gerencias.activar';

    case UsuariosConsultar = 'usuarios.consultar';
    case UsuariosCrear = 'usuarios.crear';
    case UsuariosEditar = 'usuarios.editar';
    case UsuariosActivar = 'usuarios.activar';
    case UsuariosRestablecer = 'usuarios.restablecer';

    case RolesConsultar = 'roles.consultar';
    case RolesCrear = 'roles.crear';
    case RolesEditar = 'roles.editar';
    case RolesActivar = 'roles.activar';

    case BitacoraConsultar = 'bitacora.consultar';

    /**
     * Módulo al que pertenece el permiso.
     */
    public function modulo(): string
    {
        return match (explode('.', $this->value)[0]) {
            'memorandos' => 'Memorandos',
            'gerencias' => 'Gerencias',
            'usuarios' => 'Usuarios',
            'roles' => 'Roles',
            'bitacora' => 'Bitácora',
        };
    }

    /**
     * Descripción visible del permiso.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::MemorandosConsultar => 'Consultar memorandos y ver sus PDF',
            self::MemorandosRegistrar => 'Registrar memorandos emitidos y recibidos',
            self::MemorandosEditar => 'Editar memorandos y reemplazar PDF',
            self::MemorandosDescargar => 'Descargar los PDF',
            self::GerenciasConsultar => 'Consultar gerencias',
            self::GerenciasCrear => 'Crear gerencias',
            self::GerenciasEditar => 'Editar gerencias',
            self::GerenciasActivar => 'Activar o desactivar gerencias',
            self::UsuariosConsultar => 'Consultar usuarios',
            self::UsuariosCrear => 'Crear usuarios',
            self::UsuariosEditar => 'Editar usuarios',
            self::UsuariosActivar => 'Activar o desactivar usuarios',
            self::UsuariosRestablecer => 'Restablecer contraseñas y desbloquear cuentas',
            self::RolesConsultar => 'Consultar roles',
            self::RolesCrear => 'Crear roles',
            self::RolesEditar => 'Editar roles y sus permisos',
            self::RolesActivar => 'Activar o desactivar roles',
            self::BitacoraConsultar => 'Consultar la bitácora',
        };
    }

    /**
     * Permisos agrupados por módulo, en el orden en que se muestran.
     *
     * @return array<string, list<self>>
     */
    public static function agrupados(): array
    {
        $grupos = [];

        foreach (self::cases() as $permiso) {
            $grupos[$permiso->modulo()][] = $permiso;
        }

        return $grupos;
    }

    /**
     * Permisos con los que nacen los roles base. El Administrador los tiene todos siempre.
     *
     * @return list<self>
     */
    public static function porDefecto(string $slugRol): array
    {
        return match ($slugRol) {
            'recepcion' => [self::MemorandosConsultar, self::MemorandosRegistrar, self::MemorandosEditar, self::MemorandosDescargar],
            'consulta' => [self::MemorandosConsultar, self::MemorandosDescargar],
            default => [],
        };
    }
}
