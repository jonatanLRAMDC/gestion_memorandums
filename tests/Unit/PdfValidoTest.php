<?php

use App\Rules\PdfValido;

test('it counts the pages of a PDF', function () {
    $pdf = pdfDePrueba(3);

    expect(PdfValido::contarPaginas($pdf->getRealPath()))->toBe(3)
        ->and(PdfValido::tieneEncabezadoPdf($pdf->getRealPath()))->toBeTrue()
        ->and(PdfValido::tieneFinDeArchivo($pdf->getRealPath()))->toBeTrue();
});

test('it returns null when the pages can not be read', function () {
    $ruta = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($ruta, "%PDF-1.5\nstream comprimido\n%%EOF");

    expect(PdfValido::contarPaginas($ruta))->toBeNull();
});
