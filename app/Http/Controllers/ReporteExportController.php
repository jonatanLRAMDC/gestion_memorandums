<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Support\LibroExcel;
use App\Support\ReporteMemorandos;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReporteExportController extends Controller
{
    /**
     * Descarga el reporte en un libro de Excel con una hoja por agrupación.
     */
    public function excel(Request $request): BinaryFileResponse
    {
        $reporte = $this->reporte($request);
        $datos = $reporte->datos();
        $gerencia = $request->user()->gerencia;
        $contexto = [$reporte->descripcionPeriodo(), 'Generado el '.now()->format('d/m/Y H:i').' por '.$request->user()->name];
        $totales = $datos['totales'];

        $libro = (new LibroExcel)
            ->hoja('Por mes', 'Memorandos por mes — '.$gerencia?->nombre, $contexto,
                ['Mes', 'Emitidos', 'Recibidos', 'Total'],
                array_map(fn (array $fila) => [$fila['mes'], $fila['emitidos'], $fila['recibidos'], $fila['total']], $datos['meses']),
                [20, 12, 12, 12],
                ['Total', $totales['emitidos'], $totales['recibidos'], $totales['total']],
            )
            ->hoja('Por gerencia', 'Memorandos por gerencia — '.$gerencia?->nombre, [
                ...$contexto,
                'Emitidos: memos enviados a esa gerencia (para o copia). Recibidos: memos que esa gerencia envió.',
            ],
                ['Gerencia', 'Siglas', 'Emitidos', 'Recibidos', 'Total'],
                array_map(fn (array $fila) => [$fila['gerencia'], $fila['siglas'], $fila['emitidos'], $fila['recibidos'], $fila['total']], $datos['gerencias']),
                [46, 10, 12, 12, 12],
            );

        $this->anotar($reporte, 'Excel');

        return $libro->descargar('reporte-memorandos-'.$reporte->desde->format('Ymd').'-'.$reporte->hasta->format('Ymd').'.xlsx');
    }

    /**
     * Vista lista para imprimir o guardar como PDF desde el navegador.
     */
    public function imprimir(Request $request): View
    {
        $reporte = $this->reporte($request);

        $this->anotar($reporte, 'PDF (impresión)');

        return view('reportes.imprimir', [
            'titulo' => 'Reporte de memorandos — '.$request->user()->gerencia?->nombre,
            'periodo' => $reporte->descripcionPeriodo(),
            'datos' => $reporte->datos(),
        ]);
    }

    protected function reporte(Request $request): ReporteMemorandos
    {
        abort_unless($request->user()->gerencia_id, 403, 'Su usuario no tiene una gerencia asignada.');

        return ReporteMemorandos::desdeFiltros($request->user()->gerencia_id, $request->query('desde'), $request->query('hasta'));
    }

    protected function anotar(ReporteMemorandos $reporte, string $formato): void
    {
        Bitacora::registrar('reporte_exportado', despues: [
            'formato' => $formato,
            'filtros' => ['desde' => $reporte->desde->format('d/m/Y'), 'hasta' => $reporte->hasta->format('d/m/Y')],
        ]);
    }
}
