<?php
// modules/backups/xlsx-writer.php
//
// Gerador de ficheiros .xlsx simples e SEM DEPENDÊNCIAS (não precisa de
// composer nem do PhpSpreadsheet). Usa apenas a extensão ZipArchive, que
// já vem activada por omissão no PHP do InfinityFree.
//
// Uso:
//   $xlsx = new SimpleXLSXWriter();
//   $xlsx->addSheet('Membros', ['ID','Nome'], [[1,'Ana'], [2,'João']]);
//   $xlsx->download('backup.xlsx');

class SimpleXLSXWriter
{
    /** @var array Lista de folhas: cada uma ['name'=>, 'headers'=>, 'rows'=>] */
    private $sheets = [];

    /**
     * Adiciona uma folha ao livro.
     * @param string $name    Nome da folha (máx. 31 caracteres, sem : \ / ? * [ ])
     * @param array  $headers Nomes das colunas
     * @param array  $rows    Lista de linhas (cada linha é um array de valores)
     */
    public function addSheet($name, array $headers, array $rows)
    {
        $this->sheets[] = [
            'name'    => $this->sanitizeSheetName($name),
            'headers' => $headers,
            'rows'    => $rows,
        ];
    }

    private function sanitizeSheetName($name)
    {
        $name = preg_replace('/[:\\\\\/\?\*\[\]]/', '', (string) $name);
        $name = trim($name);
        if ($name === '') $name = 'Folha';
        return mb_substr($name, 0, 31);
    }

    /** Converte índice de coluna (0-based) para letra(s) do Excel (A, B, ..., AA, ...) */
    private function colLetter($index)
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = intdiv($index - $mod, 26);
        }
        return $letter;
    }

    private function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function buildSheetXml(array $headers, array $rows)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';

        // Linha de cabeçalho (estilo 1 = negrito com fundo)
        $xml .= '<row r="1">';
        foreach ($headers as $c => $val) {
            $ref = $this->colLetter($c) . '1';
            $xml .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t xml:space="preserve">' . $this->escape($val) . '</t></is></c>';
        }
        $xml .= '</row>';

        $r = 2;
        foreach ($rows as $row) {
            $xml .= '<row r="' . $r . '">';
            foreach (array_values($row) as $c => $val) {
                $ref = $this->colLetter($c) . $r;
                $isNumeric = is_int($val) || is_float($val)
                    || (is_string($val) && $val !== '' && is_numeric($val) && !preg_match('/^0[0-9]/', $val));

                if ($val === null || $val === '') {
                    // célula vazia
                } elseif ($isNumeric) {
                    $xml .= '<c r="' . $ref . '" t="n"><v>' . (0 + $val) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $this->escape($val) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
            $r++;
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function buildStylesXml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<fonts count="2">' .
                '<font><sz val="11"/><name val="Calibri"/></font>' .
                '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>' .
            '</fonts>' .
            '<fills count="3">' .
                '<fill><patternFill patternType="none"/></fill>' .
                '<fill><patternFill patternType="gray125"/></fill>' .
                '<fill><patternFill patternType="solid"><fgColor rgb="FF1A2A4A"/><bgColor indexed="64"/></patternFill></fill>' .
            '</fills>' .
            '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
            '<cellXfs count="2">' .
                '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
                '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>' .
            '</cellXfs>' .
            '</styleSheet>';
    }

    private function buildWorkbookXml()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets>';
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $xml .= '<sheet name="' . $this->escape($sheet['name']) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        }
        $xml .= '</sheets></workbook>';
        return $xml;
    }

    private function buildWorkbookRelsXml()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $count = count($this->sheets);
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $xml .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        }
        $xml .= '<Relationship Id="rId' . ($count + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $xml .= '</Relationships>';
        return $xml;
    }

    private function buildContentTypesXml()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $xml .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $xml .= '</Types>';
        return $xml;
    }

    private function buildRootRelsXml()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
    }

    /** Constrói o ficheiro .xlsx num caminho temporário e devolve o caminho. */
    public function save($destPath)
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('A extensão ZipArchive não está disponível neste servidor.');
        }

        if (file_exists($destPath)) @unlink($destPath);

        $zip = new ZipArchive();
        if ($zip->open($destPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Não foi possível criar o ficheiro .xlsx.');
        }

        $zip->addEmptyDir('_rels');
        $zip->addEmptyDir('xl');
        $zip->addEmptyDir('xl/_rels');
        $zip->addEmptyDir('xl/worksheets');

        $zip->addFromString('[Content_Types].xml', $this->buildContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->buildRootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->buildWorkbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->buildStylesXml());

        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $zip->addFromString('xl/worksheets/sheet' . $n . '.xml', $this->buildSheetXml($sheet['headers'], $sheet['rows']));
        }

        $zip->close();
        return $destPath;
    }

    /** Gera o ficheiro e envia-o directamente para download ao browser. */
    public function download($filename)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $this->save($tmp);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp));
        header('Cache-Control: max-age=0');

        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}
?>
