<?php

namespace App\Http\Controllers;

use App\Enums\TipoDestino;
use App\Enums\TipoRegistro;
use App\Models\Bitacora;
use App\Models\Registro;
use App\Support\LibroExcel;
use App\Support\ListadoMemorandos;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MemorandoExportController extends Controller
{
    /**
     * Máximo de filas por exportación, para no saturar el servidor.
     */
    public const MAXIMO_FILAS = 5000;

    /**
     * Descarga el listado filtrado en un libro de Excel.
     */
    public function excel(Request $request): BinaryFileResponse
    {
        $listado = ListadoMemorandos::desdeRequest($request);
        $registros = $listado->consulta()->limit(self::MAXIMO_FILAS)->get();
        $gerencia = $request->user()->gerencia;

        $filas = $registros->map(fn (Registro $registro) => [
            $registro->memorando->numero,
            $registro->tipo->etiqueta(),
            $registro->memorando->gerenciaEmisora->nombre,
            ListadoMemorandos::destinos($registro, TipoDestino::Para),
            ListadoMemorandos::destinos($registro, TipoDestino::Copia),
            $registro->memorando->asunto,
            trim($registro->memorando->remitente_nombre.($registro->memorando->remitente_cargo ? ', '.$registro->memorando->remitente_cargo : '')),
            $registro->memorando->fecha_memo,
            $registro->fecha_movimiento,
            $registro->created_at,
            $registro->usuario?->name,
            $registro->observaciones,
        ]);

        $libro = (new LibroExcel)->hoja(
            nombre: 'Memorandos',
            titulo: 'Memorandos de '.$gerencia?->nombre,
            contexto: [
                $listado->descripcionFiltros(),
                'Generado el '.now()->format('d/m/Y H:i').' por '.$request->user()->name.' · '.$registros->count().' registro(s)'
                    .($registros->count() >= self::MAXIMO_FILAS ? ' (se muestran los primeros '.self::MAXIMO_FILAS.'; afine los filtros)' : ''),
            ],
            encabezados: ['Número', 'Tipo', 'De', 'Para', 'Con copia a', 'Asunto', 'Firmante', 'Fecha del memo', 'Fecha de envío o recepción', 'Registrado', 'Registrado por', 'Observaciones'],
            filas: $filas,
            anchos: [24, 10, 34, 38, 30, 50, 30, 14, 14, 17, 24, 36],
        );

        Bitacora::registrar('listado_exportado', despues: [
            'formato' => 'Excel',
            'registros' => $registros->count(),
            'filtros' => $listado->filtrosParaBitacora(),
        ]);

        return $libro->descargar('memorandos-'.mb_strtolower((string) $gerencia?->siglas).'-'.now()->format('Y-m-d-His').'.xlsx');
    }

    /**
     * Vista lista para imprimir o guardar como PDF desde el navegador.
     */
    public function imprimir(Request $request): View
    {
        $listado = ListadoMemorandos::desdeRequest($request);
        $registros = $listado->consulta()->limit(self::MAXIMO_FILAS)->get();

        Bitacora::registrar('listado_exportado', despues: [
            'formato' => 'PDF (impresión)',
            'registros' => $registros->count(),
            'filtros' => $listado->filtrosParaBitacora(),
        ]);

        return view('memorandos.imprimir', [
            'titulo' => 'Memorandos de '.$request->user()->gerencia?->nombre,
            'filtros' => $listado->descripcionFiltros(),
            'registros' => $registros,
            'maximo' => self::MAXIMO_FILAS,
            'emitido' => TipoRegistro::Emitido,
        ]);
    }
}
