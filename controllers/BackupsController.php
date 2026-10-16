<?php
// controllers/BackupsController.php
// Controlador do modulo "backups" - arquitectura MVC.
// Logica original dos antigos modules/backups/*.php preservada,
// agora separada em Model (dados) / Controller (fluxo) / View (HTML).

require_once BASE_PATH . '/models/BackupsModel.php';
require_once BASE_PATH . '/models/AuditModel.php';
class BackupsController extends Controller
{
    protected string $modulo = 'backups';

    /** Antes: modules/backups/export-excel.php */
    public function exportExcel(): void
    {
    // modules/backups/export-excel.php
    // Gera um backup .xlsx com uma folha por módulo (tabela), cada uma com
    // os respectivos registos.

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }

    if ($_SESSION['user_perfil'] != 'admin') {
        redirect('dashboard');
        exit;
    }

    require_once 'xlsx-writer.php';

    $conn = db();
    $tabelas = getListaTabelas($conn);

    $xlsx = new SimpleXLSXWriter();

    // Folha inicial com o resumo do backup
    $stats = getEstatisticasBackup($conn);
    $resumoLinhas = [];
    foreach ($tabelas as $tabela) {
        $resumoLinhas[] = [
            getTabelaLabel($tabela),
            $tabela,
            $stats['por_tabela'][$tabela] ?? 0,
        ];
    }
    $xlsx->addSheet('Resumo', ['Módulo', 'Tabela', 'Total de Registos'], $resumoLinhas);

    // Uma folha por tabela/módulo, com os seus registos
    foreach ($tabelas as $tabela) {
        $stmt = $conn->query('SELECT * FROM `' . $tabela . '`');
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($linhas)) {
            $cabecalhos = array_keys($linhas[0]);
            $dados = $linhas;
        } else {
            // Mesmo sem registos, tenta obter os nomes das colunas da tabela
            $stmtCols = $conn->query('SHOW COLUMNS FROM `' . $tabela . '`');
            $cabecalhos = array_column($stmtCols->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $dados = [];
        }

        $xlsx->addSheet(getTabelaLabel($tabela), $cabecalhos, $dados);
    }

    // Regista no histórico de auditoria
    registrarLog(
        $_SESSION['user_id'],
        $_SESSION['user_nome'],
        'exportar',
        'backups',
        'Gerou um backup completo da base de dados em formato Excel (' . count($tabelas) . ' módulos)'
    );

    $nomeArquivo = 'backup_ipfva_' . date('Y-m-d_His') . '.xlsx';
    $xlsx->download($nomeArquivo);
    ?>

    }

    /** Antes: modules/backups/export-sql.php */
    public function exportSql(): void
    {
    // modules/backups/export-sql.php
    // Gera um backup .sql completo (estrutura + dados) de todas as tabelas.

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }

    if ($_SESSION['user_perfil'] != 'admin') {
        redirect('dashboard');
        exit;
    }


    $conn = db();

    $tabelas = getListaTabelas($conn);

    $dataGeracao = date('d-M-Y \à\s H:i');
    $sql = "-- ============================================\n";
    $sql .= "-- BACKUP DA BASE DE DADOS\n";
    $sql .= "-- Sistema de Gestão I.P.F.V.A - Calemba 2\n";
    $sql .= "-- ============================================\n";
    $sql .= "-- Base de dados: `" . DB_NAME . "`\n";
    $sql .= "-- Gerado em: " . $dataGeracao . "\n";
    $sql .= "-- Gerado por: " . $_SESSION['user_nome'] . "\n";
    $sql .= "-- ============================================\n\n";
    $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sql .= "SET AUTOCOMMIT = 0;\n";
    $sql .= "START TRANSACTION;\n";
    $sql .= "SET time_zone = \"+00:00\";\n";
    $sql .= "SET NAMES utf8mb4;\n\n";

    foreach ($tabelas as $tabela) {
        // Estrutura
        $stmt = $conn->query('SHOW CREATE TABLE `' . $tabela . '`');
        $criar = $stmt->fetch(PDO::FETCH_ASSOC);
        $createSql = $criar['Create Table'] ?? '';

        $sql .= "-- --------------------------------------------------------\n\n";
        $sql .= "--\n-- Estrutura da tabela `$tabela`\n--\n\n";
        $sql .= "DROP TABLE IF EXISTS `$tabela`;\n";
        $sql .= $createSql . ";\n\n";

        // Dados
        $stmtDados = $conn->query('SELECT * FROM `' . $tabela . '`');
        $linhas = $stmtDados->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($linhas)) {
            $colunas = array_keys($linhas[0]);
            $colunasEscapadas = array_map(function ($c) { return "`$c`"; }, $colunas);

            $sql .= "--\n-- Extraindo dados da tabela `$tabela`\n--\n\n";
            $sql .= "INSERT INTO `$tabela` (" . implode(', ', $colunasEscapadas) . ") VALUES\n";

            $valoresLinhas = [];
            foreach ($linhas as $linha) {
                $valores = [];
                foreach ($linha as $valor) {
                    if ($valor === null) {
                        $valores[] = 'NULL';
                    } else {
                        $valores[] = $conn->quote($valor);
                    }
                }
                $valoresLinhas[] = '(' . implode(', ', $valores) . ')';
            }

            $sql .= implode(",\n", $valoresLinhas) . ";\n\n";
        }
    }

    $sql .= "COMMIT;\n";

    // Regista no histórico de auditoria
    registrarLog(
        $_SESSION['user_id'],
        $_SESSION['user_nome'],
        'exportar',
        'backups',
        'Gerou um backup completo da base de dados em formato SQL (' . count($tabelas) . ' tabelas)'
    );

    $nomeArquivo = 'backup_ipfva_' . date('Y-m-d_His') . '.sql';

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Content-Length: ' . strlen($sql));
    header('Cache-Control: max-age=0');

    echo $sql;
    exit;
    ?>

    }

    /** Antes: modules/backups/functions.php */
    public function functions(): void
    {
    // modules/backups/functions.php

    /** Nome amigável (em português) para cada tabela da base de dados. */
    function getTabelaLabel($tabela)
    {
        $labels = [
            'membros'              => 'Membros',
            'usuarios'             => 'Utilizadores',
            'cotas_diarias'        => 'Registo Diário de Cotas',
            'contribuicao_fim_ano' => 'Contribuição de Fim de Ano',
            'saidas'               => 'Saídas/Despesas',
            'logs_auditoria'       => 'Auditoria',
        ];
        return $labels[$tabela] ?? ucfirst(str_replace('_', ' ', $tabela));
    }

    /** Ícone FontAwesome associado a cada tabela, para a listagem. */
    function getTabelaIcon($tabela)
    {
        $icons = [
            'membros'              => 'fa-users',
            'usuarios'             => 'fa-user-cog',
            'cotas_diarias'        => 'fa-calendar-day',
            'contribuicao_fim_ano' => 'fa-gift',
            'saidas'               => 'fa-money-bill-wave',
            'logs_auditoria'       => 'fa-shield-alt',
        ];
        return $icons[$tabela] ?? 'fa-table';
    }

    /** Devolve a lista de todas as tabelas da base de dados actual. */
    function getListaTabelas(PDO $conn)
    {
        $stmt = $conn->query('SHOW TABLES');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Devolve estatísticas gerais da base de dados: número de tabelas, total
     * de registos, tamanho aproximado em disco e contagem por tabela.
     */
    function getEstatisticasBackup(PDO $conn)
    {
        $tabelas = getListaTabelas($conn);
        $porTabela = [];
        $totalRegistos = 0;

        foreach ($tabelas as $tabela) {
            $stmt = $conn->query('SELECT COUNT(*) AS total FROM `' . $tabela . '`');
            $total = (int) $stmt->fetch()['total'];
            $porTabela[$tabela] = $total;
            $totalRegistos += $total;
        }

        $tamanhoBytes = 0;
        try {
            $stmt = $conn->prepare(
                "SELECT SUM(data_length + index_length) AS tamanho
                 FROM information_schema.tables
                 WHERE table_schema = :db"
            );
            $stmt->execute([':db' => DB_NAME]);
            $tamanhoBytes = (float) ($stmt->fetch()['tamanho'] ?? 0);
        } catch (PDOException $e) {
            $tamanhoBytes = 0;
        }

        return [
            'tabelas'         => $tabelas,
            'por_tabela'      => $porTabela,
            'total_tabelas'   => count($tabelas),
            'total_registos'  => $totalRegistos,
            'tamanho_bytes'   => $tamanhoBytes,
        ];
    }

    /** Formata um número de bytes de forma legível (KB, MB, ...). */
    function formatarBytes($bytes, $casas = 1)
    {
        if ($bytes <= 0) return '0 KB';
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));
        $i = max(0, min($i, count($unidades) - 1));
        return round($bytes / (1024 ** $i), $casas) . ' ' . $unidades[$i];
    }

    /** Devolve o registo do último backup (SQL ou Excel) feito no sistema, se existir. */
    function getUltimoBackup(PDO $conn)
    {
        $stmt = $conn->prepare(
            "SELECT * FROM logs_auditoria WHERE modulo = 'backups' ORDER BY criado_em DESC LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
    ?>

    }

    /** Antes: modules/backups/index.php */
    public function index(): void
    {
    // modules/backups/index.php

    if (!isset($_SESSION['user_id'])) {
        redirect('login');
        exit;
    }

    // Apenas admin pode acessar
    if ($_SESSION['user_perfil'] != 'admin') {
        redirect('dashboard');
        exit;
    }

    $page_title = 'Backups - I.P.F.V.A Calemba 2';

    View::partial('partials/header');
    View::partial('partials/sidebar');

    $conn = db();

    $stats = getEstatisticasBackup($conn);
    $ultimoBackup = getUltimoBackup($conn);
    }

    /** Antes: modules/backups/xlsx-writer.php */
    public function xlsxWriter(): void
    {
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

    }

}
