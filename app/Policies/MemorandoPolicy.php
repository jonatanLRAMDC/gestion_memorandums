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
     * Corregir los datos del memorando.
     */
    public function update(User $user, Memorando $memorando): bool
    {
        return $user->tienePermiso(Permiso::MemorandosEditar) && $this->esDeSuGerencia($user, $memorando);
    }

    /**
     * Cargar una nueva versión del PDF. La anterior se conserva como no vigente.
     */
    public function replacePdf(User $user, Memorando $memorando): bool
    {
        return $user->tienePermiso(Permiso::MemorandosReemplazarPdf) && $this->esDeSuGerencia($user, $memorando);
    }

    /**
     * Ver el historial de cambios y abrir las versiones anteriores del PDF.
     */
    public function viewHistory(User $user, Memorando $memorando): bool
    {
        return $user->tienePermiso(Permiso::MemorandosHistorial) && $this->esDeSuGerencia($user, $memorando);
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
