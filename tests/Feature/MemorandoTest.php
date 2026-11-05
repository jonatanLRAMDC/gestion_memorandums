<?php

use App\Models\Memorando;
use Illuminate\Database\UniqueConstraintViolationException;

test('the full number is built from its parts', function () {
    expect(Memorando::armarNumero(' gis ', '398', 2026))->toBe('AMDC-GIS-398-2026');
});

test('the same siglas, correlativo value and year can only be stored once', function () {
    Memorando::factory()->create(['siglas' => 'GIS', 'correlativo' => '398', 'correlativo_valor' => 398, 'anio' => 2026, 'numero' => 'AMDC-GIS-398-2026']);

    Memorando::factory()->create(['siglas' => 'GIS', 'correlativo' => '0398', 'correlativo_valor' => 398, 'anio' => 2026, 'numero' => 'AMDC-GIS-0398-2026']);
})->throws(UniqueConstraintViolationException::class);
