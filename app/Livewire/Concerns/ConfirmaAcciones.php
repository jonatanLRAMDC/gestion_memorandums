<?php

namespace App\Livewire\Concerns;

use Flux\Flux;
use Livewire\ImplicitlyBoundMethod;

/**
 * Muestra una ventana de confirmación de Flux antes de ejecutar una acción del componente.
 */
trait ConfirmaAcciones
{
    /**
     * @var array{titulo: string, texto: string, boton: string, peligro: bool, accion: ?string, id: ?int}
     */
    public array $confirmacion = [
        'titulo' => '',
        'texto' => '',
        'boton' => '',
        'peligro' => false,
        'accion' => null,
        'id' => null,
    ];

    /**
     * Métodos del componente que se pueden ejecutar desde la ventana de confirmación.
     *
     * @return list<string>
     */
    abstract protected function accionesConfirmables(): array;

    protected function pedirConfirmacion(string $accion, int $id, string $titulo, string $texto, string $boton, bool $peligro = true): void
    {
        $this->confirmacion = [
            'titulo' => $titulo,
            'texto' => $texto,
            'boton' => $boton,
            'peligro' => $peligro,
            'accion' => $accion,
            'id' => $id,
        ];

        Flux::modal('confirmar-accion')->show();
    }

    public function confirmarAccion(): void
    {
        $accion = $this->confirmacion['accion'];
        $id = $this->confirmacion['id'];

        abort_unless(in_array($accion, $this->accionesConfirmables(), true) && $id !== null, 403);

        Flux::modal('confirmar-accion')->close();
        $this->reset('confirmacion');

        ImplicitlyBoundMethod::call(app(), [$this, $accion], [$id]);
    }
}
