<?php

namespace App\Support;

use DateTimeInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Genera libros de Excel (.xlsx) sencillos con la extensión zip de PHP, sin paquetes externos:
 * título, líneas de contexto, encabezados con filtro, primera fila fija, anchos de columna,
 * números y fechas reales.
 */
class LibroExcel
{
    /**
     * @var list<array{nombre: string, xml: string, filtro: string}>
     */
    protected array $hojas = [];

    protected const ESTILO_TITULO = 1;

    protected const ESTILO_CONTEXTO = 2;

    protected const ESTILO_ENCABEZADO = 3;

    protected const ESTILO_TEXTO = 4;

    protected const ESTILO_FECHA = 5;

    protected const ESTILO_FECHA_HORA = 6;

    protected const ESTILO_TOTAL = 7;

    public static function disponible(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * Agrega una hoja. Las celdas pueden ser texto, números, fechas (DateTimeInterface) o null.
     *
     * @param  list<string>  $contexto  Líneas bajo el título (filtros, fecha de generación…).
     * @param  list<string>  $encabezados
     * @param  iterable<array<int, mixed>>  $filas
     * @param  list<int>  $anchos  Ancho de cada columna, en caracteres.
     * @param  array<int, mixed>|null  $total  Fila final resaltada.
     */
    public function hoja(string $nombre, string $titulo, array $contexto, array $encabezados, iterable $filas, array $anchos = [], ?array $total = null): static
    {
        $columnas = count($encabezados);
        $filasXml = [];
        $numero = 1;

        $filasXml[] = $this->fila($numero++, [$titulo], self::ESTILO_TITULO);

        foreach ($contexto as $linea) {
            $filasXml[] = $this->fila($numero++, [$linea], self::ESTILO_CONTEXTO);
        }

        $numero++;
        $filaEncabezado = $numero;
        $filasXml[] = $this->fila($numero++, $encabezados, self::ESTILO_ENCABEZADO);

        foreach ($filas as $fila) {
            $filasXml[] = $this->fila($numero++, array_values($fila));
        }

        $ultimaFila = $numero - 1;

        if ($total !== null) {
            $filasXml[] = $this->fila($numero++, array_values($total), self::ESTILO_TOTAL);
        }

        $ultimaColumna = self::letra($columnas - 1);
        $anchosXml = '';

        foreach ($anchos as $indice => $ancho) {
            $columna = $indice + 1;
            $anchosXml .= "<col min=\"{$columna}\" max=\"{$columna}\" width=\"{$ancho}\" customWidth=\"1\"/>";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$filaEncabezado.'" topLeftCell="A'.($filaEncabezado + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .($anchosXml !== '' ? "<cols>{$anchosXml}</cols>" : '')
            .'<sheetData>'.implode('', $filasXml).'</sheetData>'
            ."<autoFilter ref=\"A{$filaEncabezado}:{$ultimaColumna}".max($filaEncabezado, $ultimaFila).'"/>'
            .'<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';

        $this->hojas[] = ['nombre' => $this->nombreHoja($nombre), 'xml' => $xml, 'filtro' => "A{$filaEncabezado}:{$ultimaColumna}".max($filaEncabezado, $ultimaFila)];

        return $this;
    }

    /**
     * Escribe el libro en un archivo temporal y devuelve su ruta.
     */
    public function guardar(): string
    {
        if (! self::disponible()) {
            throw new RuntimeException('Para exportar a Excel habilite la extensión zip de PHP (extension=zip en php.ini).');
        }

        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($ruta, ZipArchive::OVERWRITE);

        $tipos = '';
        $relaciones = '';
        $hojas = '';
        $nombresDefinidos = '';

        foreach ($this->hojas as $indice => $hoja) {
            $numero = $indice + 1;
            $zip->addFromString("xl/worksheets/sheet{$numero}.xml", $hoja['xml']);
            $tipos .= "<Override PartName=\"/xl/worksheets/sheet{$numero}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
            $relaciones .= "<Relationship Id=\"rId{$numero}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$numero}.xml\"/>";
            $hojas .= '<sheet name="'.self::escapar($hoja['nombre'])."\" sheetId=\"{$numero}\" r:id=\"rId{$numero}\"/>";
            [$desde, $hasta] = explode(':', $hoja['filtro']);
            $nombresDefinidos .= '<definedName name="_xlnm._FilterDatabase" localSheetId="'.$indice.'" hidden="1">\''.str_replace("'", "''", $hoja['nombre']).'\'!$'.preg_replace('/(\d+)/', '\$$1', $desde).':$'.preg_replace('/(\d+)/', '\$$1', $hasta).'</definedName>';
        }

        $estilos = count($this->hojas) + 1;

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$tipos.'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            ."<sheets>{$hojas}</sheets><definedNames>{$nombresDefinidos}</definedNames></workbook>");

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$relaciones
            ."<Relationship Id=\"rId{$estilos}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>"
            .'</Relationships>');

        $zip->addFromString('xl/styles.xml', self::estilos());
        $zip->close();

        return $ruta;
    }

    /**
     * Respuesta de descarga; el archivo temporal se borra al enviarse.
     */
    public function descargar(string $nombreArchivo): BinaryFileResponse
    {
        return response()
            ->download($this->guardar(), $nombreArchivo, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * @param  array<int, mixed>  $celdas
     */
    protected function fila(int $numero, array $celdas, ?int $estilo = null): string
    {
        $xml = '';

        foreach ($celdas as $indice => $valor) {
            $referencia = self::letra($indice).$numero;

            $xml .= match (true) {
                $valor === null || $valor === '' => $estilo ? "<c r=\"{$referencia}\" s=\"{$estilo}\"/>" : '',
                $valor instanceof DateTimeInterface => "<c r=\"{$referencia}\" s=\"".($estilo ?? ($valor->format('H:i:s') === '00:00:00' ? self::ESTILO_FECHA : self::ESTILO_FECHA_HORA)).'"><v>'.self::serialFecha($valor).'</v></c>',
                is_int($valor) || is_float($valor) => "<c r=\"{$referencia}\"".($estilo ? " s=\"{$estilo}\"" : '')."><v>{$valor}</v></c>",
                default => "<c r=\"{$referencia}\" t=\"inlineStr\" s=\"".($estilo ?? self::ESTILO_TEXTO).'"><is><t xml:space="preserve">'.self::escapar((string) $valor).'</t></is></c>',
            };
        }

        return "<row r=\"{$numero}\">{$xml}</row>";
    }

    /**
     * Letra de la columna a partir de su índice (0 = A).
     */
    public static function letra(int $indice): string
    {
        $letra = '';

        for ($indice++; $indice > 0; $indice = intdiv($indice - 1, 26)) {
            $letra = chr(65 + (($indice - 1) % 26)).$letra;
        }

        return $letra;
    }

    protected static function serialFecha(DateTimeInterface $fecha): float
    {
        $dias = (int) floor(((int) $fecha->format('U') + (int) $fecha->format('Z')) / 86400) + 25569;
        $segundos = ((int) $fecha->format('H')) * 3600 + ((int) $fecha->format('i')) * 60 + (int) $fecha->format('s');

        return round($dias + $segundos / 86400, 6);
    }

    protected function nombreHoja(string $nombre): string
    {
        return mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $nombre), 0, 31);
    }

    protected static function escapar(string $texto): string
    {
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texto);

        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected static function estilos(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy hh:mm"/></numFmts>'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><color rgb="FF1E4A9E"/><name val="Calibri"/></font>'
            .'<font><sz val="10"/><color rgb="FF52525B"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1E4A9E"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE4E4E7"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left/><right/><top style="thin"><color rgb="FF71717A"/></top><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="8">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="left" vertical="top"/></xf>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="left" vertical="top"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
