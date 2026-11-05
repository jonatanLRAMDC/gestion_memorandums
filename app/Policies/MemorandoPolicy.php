<?php

namespace App\Policies;

use App\Enums\Permiso;
use App\Models\Memorando;
use App\Models\User;

class MemorandoPolicy
{
    /**
     * Ver el memorando y abrir su PDF en el navegador.
     */
    public function view(User $user, Memorando $memorando): bool
    {
        return $user->tienePermiso(Permiso::MemorandosConsultar) && $this->esDeSuGerencia($user, $memorando);
    }

    /**
     * Descargar el PDF.
     */
    public function download(User $user, Memorando $memorando): bool
    {
        return $user->tienePermiso(Permiso::MemorandosDescargar) && $this->esDeSuGerencia($user, $memorando);
    }

    /**
     * En la versión 1 cada gerencia ve los memorandos que ella misma registró.
     */
    protected function esDeSuGerencia(User $user, Memorando $memorando): bool
    {
        return $user->gerencia_id !== null
            && $memorando->registros()->where('gerencia_id', $user->gerencia_id)->exists();
    }
}
