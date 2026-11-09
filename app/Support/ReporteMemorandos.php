<?php

namespace App\Support;

use App\Enums\TipoRegistro;
use App\Models\Gerencia;
use App\Models\Registro;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Totales de memorandos emitidos y recibidos por una gerencia en un período, agrupados por
 * mes y por gerencia contraparte. El período se mide por la fecha de envío o recepción.
 */
class ReporteMemorandos
{
    public function __construct(public int $gerenciaId, public Carbon $desde, public Carbon $hasta) {}

    /**
     * Arma el reporte a partir de fechas aaaa-mm-dd. Sin fechas válidas, toma el año en curso.
     */
    public static function desdeFiltros(int $gerenciaId, ?string $desde, ?string $hasta): self
    {
        $desde = Registro::fechaValida($desde) ? Carbon::parse($desde) : now()->startOfYear();
        $hasta = Registro::fechaValida($hasta) ? Carbon::parse($hasta) : now();

        if ($desde->greaterThan($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return new self($gerenciaId, $desde->startOfDay(), $hasta->endOfDay());
    }

    public function descripcionPeriodo(): string
    {
        return 'Del '.$this->desde->format('d/m/Y').' al '.$this->hasta->format('d/m/Y').', según la fecha de envío o recepción';
    }

    /**
     * @return array{
     *     totales: array{emitidos: int, recibidos: int, total: int},
     *     meses: list<array{mes: string, emitidos: int, recibidos: int, total: int}>,
     *     gerencias: list<array{gerencia: string, siglas: ?string, emitidos: int, recibidos: int, total: int}>
     * }
     */
    public function datos(): array
    {
        /** @var Collection<int, Registro> $registros */
        $registros = Registro::query()
            ->deGerencia($this->gerenciaId)
            ->whereBetween('fecha_movimiento', [$this->desde->toDateString(), $this->hasta->toDateString()])
            ->with(['memorando:id,gerencia_emisora_id', 'memorando.destinos:id,memorando_id,gerencia_id'])
            ->get(['id', 'memorando_id', 'tipo', 'fecha_movimiento']);

        $emitidos = $registros->where('tipo', TipoRegistro::Emitido);
        $recibidos = $registros->where('tipo', TipoRegistro::Recibido);

        return [
            'totales' => [
                'emitidos' => $emitidos->count(),
                'recibidos' => $recibidos->count(),
                'total' => $registros->count(),
            ],
            'meses' => $this->porMes($emitidos, $recibidos),
            'gerencias' => $this->porGerencia($emitidos, $recibidos),
        ];
    }

    /**
     * @param  Collection<int, Registro>  $emitidos
     * @param  Collection<int, Registro>  $recibidos
     * @return list<array{mes: string, emitidos: int, recibidos: int, total: int}>
     */
    protected function porMes(Collection $emitidos, Collection $recibidos): array
    {
        $contar = fn (Collection $registros) => $registros->countBy(fn (Registro $registro) => $registro->fecha_movimiento->format('Y-m'));
        $porMesEmitidos = $contar($emitidos);
        $porMesRecibidos = $contar($recibidos);
        $meses = [];

        for ($mes = $this->desde->copy()->startOfMonth(); $mes->lessThanOrEqualTo($this->hasta); $mes->addMonth()) {
            $clave = $mes->format('Y-m');
            $fila = [
                'mes' => Str::ucfirst($mes->locale('es')->translatedFormat('F Y')),
                'emitidos' => $porMesEmitidos[$clave] ?? 0,
                'recibidos' => $porMesRecibidos[$clave] ?? 0,
            ];
            $meses[] = [...$fila, 'total' => $fila['emitidos'] + $fila['recibidos']];
        }

        return $meses;
    }

    /**
     * Emitidos: memos enviados a la gerencia (como PARA o COPIA). Recibidos: memos que la gerencia envió.
     *
     * @param  Collection<int, Registro>  $emitidos
     * @param  Collection<int, Registro>  $recibidos
     * @return list<array{gerencia: string, siglas: ?string, emitidos: int, recibidos: int, total: int}>
     */
    protected function porGerencia(Collection $emitidos, Collection $recibidos): array
    {
        $enviadosA = $emitidos
            ->flatMap(fn (Registro $registro) => $registro->memorando->destinos->pluck('gerencia_id')->unique())
            ->countBy();
        $recibidosDe = $recibidos->countBy(fn (Registro $registro) => $registro->memorando->gerencia_emisora_id);

        $ids = $enviadosA->keys()->merge($recibidosDe->keys())->unique()->reject(fn ($id) => (int) $id === $this->gerenciaId);

        return Gerencia::query()
            ->whereKey($ids->all())
            ->get(['id', 'nombre', 'siglas'])
            ->map(fn (Gerencia $gerencia) => [
                'gerencia' => $gerencia->nombre,
                'siglas' => $gerencia->siglas,
                'emitidos' => $enviadosA[$gerencia->id] ?? 0,
                'recibidos' => $recibidosDe[$gerencia->id] ?? 0,
                'total' => ($enviadosA[$gerencia->id] ?? 0) + ($recibidosDe[$gerencia->id] ?? 0),
            ])
            ->sortBy([['total', 'desc'], ['gerencia', 'asc']])
            ->values()
            ->all();
    }
}
