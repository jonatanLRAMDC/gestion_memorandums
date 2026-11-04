<?php

namespace App\Enums;

enum Rol: string
{
    case Recepcion = 'recepcion';
    case Consulta = 'consulta';
    case Administrador = 'administrador';

    /**
     * Nombre visible del rol.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Recepcion => 'Recepción',
            self::Consulta => 'Consulta',
            self::Administrador => 'Administrador',
        };
    }
}
