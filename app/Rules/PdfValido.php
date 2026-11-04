<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

class PdfValido implements ValidationRule
{
    /**
     * Comprueba que el archivo sea realmente un PDF legible y no esté vacío ni dañado.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('No se pudo leer el archivo. Vuelva a cargarlo.');

            return;
        }

        $ruta = $value->getRealPath();

        if (! self::tieneEncabezadoPdf($ruta)) {
            $fail('El archivo no es un PDF válido.');

            return;
        }

        if (! self::tieneFinDeArchivo($ruta)) {
            $fail('El PDF está incompleto o dañado. Vuelva a escanearlo o a guardarlo.');
        }
    }

    /**
     * Un PDF comienza con la firma "%PDF-" en sus primeros bytes.
     */
    public static function tieneEncabezadoPdf(string $ruta): bool
    {
        $inicio = (string) @file_get_contents($ruta, length: 1024);

        return str_contains($inicio, '%PDF-');
    }

    /**
     * Un PDF completo termina con el marcador "%%EOF".
     */
    public static function tieneFinDeArchivo(string $ruta): bool
    {
        $tamano = (int) @filesize($ruta);
        $final = (string) @file_get_contents($ruta, offset: max(0, $tamano - 2048));

        return str_contains($final, '%%EOF');
    }

    /**
     * Cuenta las páginas leyendo los objetos de página del PDF.
     * Devuelve null cuando el PDF guarda esa información comprimida y no se puede leer sin una librería.
     */
    public static function contarPaginas(string $ruta): ?int
    {
        $contenido = (string) @file_get_contents($ruta);

        if ($contenido === '') {
            return null;
        }

        $paginas = preg_match_all('/\/Type\s*\/Page(?![a-zA-Z])/', $contenido);

        if ($paginas > 0) {
            return $paginas;
        }

        if (preg_match_all('/\/Type\s*\/Pages\b[^>]*?\/Count\s+(\d+)/s', $contenido, $coincidencias) && $coincidencias[1] !== []) {
            return max(array_map('intval', $coincidencias[1]));
        }

        return null;
    }
}
