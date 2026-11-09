<?php

namespace App\Support;

use App\Enums\TipoDestino;
use App\Enums\TipoRegistro;
use App\Models\Gerencia;
use App\Models\Registro;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Listado de memorandos de la gerencia del usuario con los mismos filtros y el mismo orden de
 * la pantalla, para exportarlo o imprimirlo.
 */
class ListadoMemorandos
{
    /**
     * @param  array{tipo: string, buscar: string, gerencia: string, desde: string, hasta: string, orden: string, dir: string}  $filtros
     */
    public function __construct(public User $usuario, public array $filtros) {}

    public static function desdeRequest(Request $request): self
    {
        return new self($request->user(), [
            'tipo' => (string) $request->query('tipo', ''),
            'buscar' => (string) $request->query('q', ''),
            'gerencia' => (string) $request->query('gerencia', ''),
            'desde' => (string) $request->query('desde', ''),
            'hasta' => (string) $request->query('hasta', ''),
            'orden' => (string) $request->query('orden', 'registrado'),
            'dir' => (string) $request->query('dir', 'desc'),
        ]);
    }

    /**
     * @return Builder<Registro>
     */
    public function consulta(): Builder
    {
        return Registro::query()
            ->deGerencia($this->usuario->gerencia_id)
            ->with([
                'usuario:id,name',
                'memorando.gerenciaEmisora:id,nombre,siglas',
                'memorando.destinos.gerencia:id,nombre,siglas',
            ])
            ->filtrar($this->filtros)
            ->ordenar($this->filtros['orden'], $this->filtros['dir']);
    }

    /**
     * Filtros aplicados, en palabras, para el encabezado del archivo.
     */
    public function descripcionFiltros(): string
    {
        $partes = [];

        if ($tipo = TipoRegistro::tryFrom($this->filtros['tipo'])) {
            $partes[] = 'Tipo: '.($tipo === TipoRegistro::Emitido ? 'emitidos' : 'recibidos');
        }

        if (trim($this->filtros['buscar']) !== '') {
            $partes[] = 'Búsqueda: “'.trim($this->filtros['buscar']).'”';
        }

        if (ctype_digit($this->filtros['gerencia']) && $gerencia = Gerencia::find((int) $this->filtros['gerencia'])) {
            $partes[] = 'Gerencia: '.$gerencia->nombre;
        }

        $desde = Registro::fechaValida($this->filtros['desde']);
        $hasta = Registro::fechaValida($this->filtros['hasta']);

        if ($desde || $hasta) {
            $partes[] = 'Fecha del memo: '.($desde ? 'desde '.Carbon::parse($desde)->format('d/m/Y') : '').($desde && $hasta ? ' ' : '').($hasta ? 'hasta '.Carbon::parse($hasta)->format('d/m/Y') : '');
        }

        $orden = Registro::ORDENES[$this->filtros['orden']] ?? Registro::ORDENES['registrado'];
        $partes[] = 'Orden: '.mb_strtolower($orden).($this->filtros['dir'] === 'asc' ? ' (ascendente)' : ' (descendente)');

        return implode(' · ', $partes);
    }

    /**
     * Filtros que se guardan en la bitácora (solo los que tienen valor).
     *
     * @return array<string, string>
     */
    public function filtrosParaBitacora(): array
    {
        return array_filter($this->filtros, fn (string $valor) => $valor !== '');
    }

    /**
     * Gerencias destinatarias de un tipo, separadas por punto y coma.
     */
    public static function destinos(Registro $registro, TipoDestino $tipo): string
    {
        return $registro->memorando->destinos
            ->where('tipo', $tipo)
            ->map(fn ($destino) => $destino->gerencia->nombre.($destino->persona_nombre ? " ({$destino->persona_nombre})" : ''))
            ->join('; ');
    }
}
