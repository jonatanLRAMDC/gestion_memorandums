<?php

namespace App\Enums;

enum TipoRegistro: string
{
    case Emitido = 'emitido';
    case Recibido = 'recibido';

    /**
     * Nombre visible del tipo de registro.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Emitido => 'Emitido',
            self::Recibido => 'Recibido',
        };
    }
}
