<?php
// core/View.php
// ============================================
// VISTA (View) da arquitectura MVC
// ============================================
// Renderiza ficheiros de views/ separando:
//  - partials (header, sidebar, topbar, footer) para as páginas com layout;
//  - vistas "completas" (login, PDFs, exports) renderizadas com ob_start().

class View
{
    /**
     * Inclui um ficheiro de vista (caminho relativo à pasta views/),
     * injectando os dados extraídos como variáveis locais.
     */
    public static function partial(string $path, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $file = BASE_PATH . '/views/' . $path . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Vista não encontrada: {$file}");
        }
        include $file;
    }

    /**
     * Captura o output de uma vista completa num buffer e devolve-o
     * como string (usado por controladores que geram HTML/PDF/CSV).
     */
    public static function capture(string $path, array $data = []): string
    {
        ob_start();
        self::partial($path, $data);
        return ob_get_clean();
    }

    /**
     * Renderiza uma vista completa directamente para o navegador.
     */
    public static function render(string $path, array $data = []): void
    {
        self::partial($path, $data);
    }

    /**
     * Envia uma string como download (Excel, SQL, etc.).
     */
    public static function download(string $content, string $filename, string $mime): void
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
}
