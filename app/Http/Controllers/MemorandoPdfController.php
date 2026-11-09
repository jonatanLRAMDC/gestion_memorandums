<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Memorando;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemorandoPdfController extends Controller
{
    /**
     * Entrega el PDF del memorando solo a usuarios autorizados y lo anota en la bitácora.
     * Por omisión es la versión vigente; con ?version={archivo} se pide una anterior, que
     * solo pueden abrir quienes tienen permiso para ver el historial.
     */
    public function __invoke(Request $request, Memorando $memorando): StreamedResponse
    {
        $descargar = $request->boolean('descargar');

        Gate::authorize($descargar ? 'download' : 'view', $memorando);

        $archivo = $request->filled('version')
            ? $memorando->archivos()->whereNull('registro_id')->whereKey($request->integer('version'))->first()
            : $memorando->archivoVigente;

        if ($archivo && ! $archivo->vigente) {
            Gate::authorize('viewHistory', $memorando);
        }

        $disco = Storage::disk(config('memorandos.disco'));

        abort_if(! $archivo || ! $disco->exists($archivo->ruta_interna), 404, 'El PDF de este memorando no está disponible.');

        Bitacora::registrar($descargar ? 'documento_descargado' : 'documento_consultado', $memorando, despues: [
            'numero' => $memorando->numero,
            'archivo_id' => $archivo->id,
            'version' => $archivo->version,
        ]);

        return $disco->response(
            $archivo->ruta_interna,
            $memorando->numero.($archivo->vigente ? '' : '-v'.$archivo->version).'.pdf',
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            $descargar ? 'attachment' : 'inline',
        );
    }
}
