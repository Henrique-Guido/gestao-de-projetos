<?php

namespace App\Core;

/**
 * Gerador mínimo de planilhas .xlsx (Office Open XML) sem dependências externas, usando ZipArchive.
 *
 * Em modo tabela, cada aba ganha cabeçalho colorido, linhas zebradas, bordas, filtro e a primeira
 * linha congelada. Uma célula pode ser um valor simples ou um array com 'valor' e, opcionalmente,
 * 'tipo' ('data' para 'Y-m-d' | 'link' para URL), 'fundo', 'cor' (hex RRGGBB), 'negrito' e 'tamanho'.
 */
class XlsxWriter
{
    private const FORMATO_DATA = 164;
    private const COR_BORDA = 'D9E1F2';
    private const COR_ZEBRA = 'F3F6FB';

    private array $abas = [];

    private array $fonts = ['<font><sz val="11"/><name val="Calibri"/></font>'];
    private array $fills = [
        '<fill><patternFill patternType="none"/></fill>',
        '<fill><patternFill patternType="gray125"/></fill>',
    ];
    private array $borders = ['<border><left/><right/><top/><bottom/><diagonal/></border>'];
    private array $xfs = ['<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'];

    /**
     * @param array $colunas lista de ['titulo' => string, 'largura' => int]
     * @param array $linhas  lista de linhas, cada uma com uma célula por coluna
     * @param bool  $tabela  false = sem cabeçalho/filtro/zebra (layout livre, ex.: aba de resumo)
     */
    public function addSheet(string $nome, string $cor, array $colunas, array $linhas, bool $tabela = true): void
    {
        $nome = mb_substr(preg_replace('#[\[\]:*?/\\\\]#', '', $nome), 0, 31);
        $ultimaColuna = self::coluna(max(count($colunas), 1) - 1);
        $xmlLinhas = '';
        $numero = 1;

        if ($tabela) {
            $cabecalho = array_map(
                fn (array $c) => ['valor' => $c['titulo'], 'negrito' => true, 'cor' => 'FFFFFF', 'fundo' => $cor],
                $colunas
            );
            $xmlLinhas .= $this->linha($numero++, $cabecalho, [], 24);
        }

        foreach ($linhas as $i => $linha) {
            $base = $tabela ? ['borda' => true, 'fundo' => $i % 2 === 1 ? self::COR_ZEBRA : null] : [];
            $xmlLinhas .= $this->linha($numero++, $linha, $base);
        }

        $ultimaLinha = max($numero - 1, 1);
        $intervalo = "A1:{$ultimaColuna}{$ultimaLinha}";

        $larguras = '';
        foreach ($colunas as $i => $c) {
            $n = $i + 1;
            $larguras .= "<col min=\"{$n}\" max=\"{$n}\" width=\"{$c['largura']}\" customWidth=\"1\"/>";
        }

        $selecionada = $this->abas === [] ? ' tabSelected="1"' : '';
        $congelar = $tabela
            ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            : '';

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><tabColor rgb="FF' . $cor . '"/><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<dimension ref="' . $intervalo . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"' . $selecionada . ($tabela ? '' : ' showGridLines="0"') . '>'
            . $congelar . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/>'
            . ($larguras !== '' ? "<cols>{$larguras}</cols>" : '')
            . "<sheetData>{$xmlLinhas}</sheetData>"
            . ($tabela ? '<autoFilter ref="' . $intervalo . '"/>' : '')
            . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            // Impressão em paisagem, com todas as colunas cabendo na largura da página.
            . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . '</worksheet>';

        $this->abas[] = ['nome' => $nome, 'xml' => $xml, 'filtro' => $tabela ? $intervalo : null];
    }

    public function save(string $caminho, string $titulo = ''): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($caminho, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Não foi possível criar a planilha em {$caminho}");
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '</Relationships>');
        $zip->addFromString('docProps/core.xml', $this->core($titulo));
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->abas as $i => $aba) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $aba['xml']);
        }

        $zip->close();
    }

    private function linha(int $numero, array $celulas, array $base, ?int $altura = null): string
    {
        $xml = '';

        foreach (array_values($celulas) as $i => $celula) {
            $props = is_array($celula) ? $celula : ['valor' => $celula];
            // Fundo nulo na célula não apaga a zebra da linha.
            $props = array_merge($base, array_filter($props, static fn ($v) => $v !== null));
            $xml .= $this->celula(self::coluna($i) . $numero, $props);
        }

        $atributos = $altura ? " ht=\"{$altura}\" customHeight=\"1\"" : '';

        return "<row r=\"{$numero}\"{$atributos}>{$xml}</row>";
    }

    private function celula(string $ref, array $p): string
    {
        $valor = $p['valor'] ?? null;
        $tipo = $p['tipo'] ?? null;

        if ($tipo === 'data' && is_string($valor)) {
            $data = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($valor, 0, 10), new \DateTimeZone('UTC'));

            if ($data !== false) {
                $serial = intdiv($data->getTimestamp(), 86400) + 25569;

                return "<c r=\"{$ref}\" s=\"{$this->estilo($p + ['data' => true])}\"><v>{$serial}</v></c>";
            }
        }

        if ($tipo === 'link' && is_string($valor) && preg_match('#^https?://#i', $valor)) {
            $estilo = $this->estilo($p + ['cor' => '0563C1', 'sublinhado' => true]);
            $formula = 'HYPERLINK("' . str_replace('"', '""', $valor) . '")';

            return "<c r=\"{$ref}\" s=\"{$estilo}\" t=\"str\"><f>" . self::xml($formula) . '</f><v>' . self::xml($valor) . '</v></c>';
        }

        $estilo = $this->estilo($p);

        if ($valor === null || $valor === '') {
            return "<c r=\"{$ref}\" s=\"{$estilo}\"/>";
        }

        if (is_int($valor) || is_float($valor)) {
            return "<c r=\"{$ref}\" s=\"{$estilo}\"><v>{$valor}</v></c>";
        }

        return "<c r=\"{$ref}\" s=\"{$estilo}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">"
            . self::xml((string) $valor) . '</t></is></c>';
    }

    /** Registra (sem duplicar) a combinação de fonte, preenchimento e borda e devolve o índice do estilo. */
    private function estilo(array $p): int
    {
        $font = '<font>' . (!empty($p['negrito']) ? '<b/>' : '') . (!empty($p['sublinhado']) ? '<u/>' : '')
            . '<sz val="' . ($p['tamanho'] ?? 11) . '"/>'
            . (isset($p['cor']) ? '<color rgb="FF' . $p['cor'] . '"/>' : '')
            . '<name val="Calibri"/></font>';

        $fill = isset($p['fundo'])
            ? '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $p['fundo'] . '"/><bgColor indexed="64"/></patternFill></fill>'
            : $this->fills[0];

        $lado = '<color rgb="FF' . self::COR_BORDA . '"/>';
        $border = !empty($p['borda'])
            ? "<border><left style=\"thin\">{$lado}</left><right style=\"thin\">{$lado}</right>"
                . "<top style=\"thin\">{$lado}</top><bottom style=\"thin\">{$lado}</bottom><diagonal/></border>"
            : $this->borders[0];

        $xf = sprintf(
            '<xf numFmtId="%d" fontId="%d" fillId="%d" borderId="%d" xfId="0" applyNumberFormat="1" applyFont="1"'
            . ' applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>',
            !empty($p['data']) ? self::FORMATO_DATA : 0,
            self::registrar($this->fonts, $font),
            self::registrar($this->fills, $fill),
            self::registrar($this->borders, $border)
        );

        return self::registrar($this->xfs, $xf);
    }

    private static function registrar(array &$lista, string $xml): int
    {
        $indice = array_search($xml, $lista, true);

        if ($indice === false) {
            $lista[] = $xml;
            $indice = count($lista) - 1;
        }

        return $indice;
    }

    private function styles(): string
    {
        $grupo = static fn (string $tag, array $itens) => "<{$tag} count=\"" . count($itens) . "\">" . implode('', $itens) . "</{$tag}>";

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="' . self::FORMATO_DATA . '" formatCode="dd/mm/yyyy"/></numFmts>'
            . $grupo('fonts', $this->fonts)
            . $grupo('fills', $this->fills)
            . $grupo('borders', $this->borders)
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . $grupo('cellXfs', $this->xfs)
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function workbook(): string
    {
        $abas = '';
        $nomes = '';

        foreach ($this->abas as $i => $aba) {
            $id = $i + 1;
            $abas .= '<sheet name="' . self::xml($aba['nome']) . "\" sheetId=\"{$id}\" r:id=\"rId{$id}\"/>";

            if ($aba['filtro']) {
                $absoluto = preg_replace_callback('/([A-Z]+)(\d+)/', static fn (array $m) => '$' . $m[1] . '$' . $m[2], $aba['filtro']);
                $referencia = "'" . str_replace("'", "''", $aba['nome']) . "'!" . $absoluto;
                $nomes .= "<definedName name=\"_xlnm._FilterDatabase\" localSheetId=\"{$i}\" hidden=\"1\">" . self::xml($referencia) . '</definedName>';
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0"/></bookViews>'
            . "<sheets>{$abas}</sheets>"
            . ($nomes !== '' ? "<definedNames>{$nomes}</definedNames>" : '')
            . '</workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';

        foreach (array_keys($this->abas) as $i) {
            $id = $i + 1;
            $rels .="<Relationship Id=\"rId{$id}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$id}.xml\"/>";
        }

        $rels .= '<Relationship Id="rId' . (count($this->abas) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . "<Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\">{$rels}</Relationships>";
    }

    private function contentTypes(): string
    {
        $abas = '';

        foreach (array_keys($this->abas) as $i) {
            $abas .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . $abas
            . '</Types>';
    }

    private function core(string $titulo): string
    {
        $agora = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
            . ' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . self::xml($titulo) . '</dc:title><dc:creator>' . self::xml($titulo) . '</dc:creator>'
            . "<dcterms:created xsi:type=\"dcterms:W3CDTF\">{$agora}</dcterms:created>"
            . '</cp:coreProperties>';
    }

    /** Índice de coluna (0 = A) para letra(s) do Excel. */
    private static function coluna(int $indice): string
    {
        $letras = '';

        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letras = chr(65 + ($n - 1) % 26) . $letras;
        }

        return $letras;
    }

    private static function xml(string $texto): string
    {
        // Remove caracteres de controle inválidos em XML 1.0.
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texto);

        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
