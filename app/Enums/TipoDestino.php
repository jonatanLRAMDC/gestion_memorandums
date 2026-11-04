<?php

namespace App\Enums;

enum TipoDestino: string
{
    case Para = 'para';
    case Copia = 'copia';

    /**
     * Nombre visible del tipo de destinatario.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Para => 'PARA',
            self::Copia => 'COPIA',
        };
    }
}
