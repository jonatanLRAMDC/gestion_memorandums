@props(['titulo', 'contexto' => []])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    <style>
        @page { size: letter landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 11px/1.35 system-ui, "Segoe UI", Roboto, Arial, sans-serif; color: #18181b; background: #fff; }
        .hoja { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .barra { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 16px; }
        .barra button { font: inherit; font-size: 13px; padding: 7px 14px; border-radius: 6px; border: 1px solid #d4d4d8; background: #fff; cursor: pointer; }
        .barra button.primario { background: #1e4a9e; border-color: #1e4a9e; color: #fff; }
        header { display: flex; align-items: center; gap: 14px; padding-bottom: 12px; border-bottom: 2px solid #1e4a9e; }
        header img { width: 54px; height: 54px; object-fit: contain; }
        header .institucion { font-size: 10px; letter-spacing: .04em; text-transform: uppercase; color: #52525b; }
        header h1 { margin: 2px 0 0; font-size: 18px; color: #1e4a9e; }
        .contexto { margin: 10px 0 14px; color: #52525b; }
        .contexto p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e4a9e; color: #fff; text-align: left; font-weight: 600; padding: 6px; }
        td { padding: 5px 6px; border-bottom: 1px solid #e4e4e7; vertical-align: top; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        tfoot td { font-weight: 600; background: #f4f4f5; border-top: 1px solid #71717a; }
        .num { white-space: nowrap; font-variant-numeric: tabular-nums; }
        .der { text-align: right; }
        .mono { font-family: ui-monospace, Consolas, monospace; white-space: nowrap; }
        .gris { color: #71717a; }
        h2 { font-size: 13px; color: #1e4a9e; margin: 20px 0 6px; }
        footer { margin-top: 14px; color: #71717a; font-size: 10px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        @media print { .barra { display: none; } .hoja { padding: 0; max-width: none; } }
    </style>
</head>
<body>
    <div class="hoja">
        <div class="barra">
            <button type="button" class="primario" onclick="window.print()">Imprimir o guardar como PDF</button>
        </div>

        <header>
            @if (file_exists(public_path('images/escudo-amdc.png')))
                <img src="{{ asset('images/escudo-amdc.png') }}" alt="">
            @endif
            <div>
                <div class="institucion">Alcaldía Municipal del Distrito Central</div>
                <h1>{{ $titulo }}</h1>
            </div>
        </header>

        <div class="contexto">
            @foreach ($contexto as $linea)
                <p>{{ $linea }}</p>
            @endforeach
        </div>

        {{ $slot }}

        <footer>Generado el {{ now()->format('d/m/Y \a \l\a\s H:i') }} por {{ auth()->user()?->name }} · {{ config('app.name') }}</footer>
    </div>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300))</script>
</body>
</html>
