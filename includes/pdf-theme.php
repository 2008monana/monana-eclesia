<?php
// includes/pdf-theme.php
// Identidade visual MonanaEclésia para todos os PDFs gerados com DOMPDF.
// Dourado metálico + castanho do wordmark sobre branco/marfim.
// Fontes: DejaVu Serif (títulos, ecoa o wordmark serifado) e DejaVu Sans (dados),
// ambas incluídas no DOMPDF — não precisam de instalação.

/** Logótipo (símbolo) embutido em base64, para não depender de URLs remotas. */
function pdfLogoDataUri() {
    static $uri = null;
    if ($uri === null) {
        $ficheiro = __DIR__ . '/../assets/img/logo-mark.png';
        $uri = is_readable($ficheiro)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($ficheiro))
            : '';
    }
    return $uri;
}

/** Bloco de marca (símbolo + nome da igreja + plataforma). Vai dentro de <div class="header">. */
function pdfCabecalho() {
    $logo = pdfLogoDataUri();
    $img = $logo ? '<img src="' . $logo . '" style="width:42px;height:auto;">' : '';
    return '<table class="brand" cellspacing="0" cellpadding="0"><tr>'
         . '<td class="brand-logo">' . $img . '</td>'
         . '<td class="brand-name">'
         . '<div class="brand-church">Igreja Pentecostal Fonte da Vida em Angola</div>'
         . '<div class="brand-sub">I.P.F.V.A &middot; Calemba 2</div>'
         . '</td>'
         . '<td class="brand-plat">MONANA<span>ECLÉSIA</span></td>'
         . '</tr></table>';
}

/** CSS de marca. Colocado no fim do <style> de cada PDF, sobrepõe-se ao CSS local. */
function pdfBrandCss() {
    return '
        body { font-family: "DejaVu Sans", sans-serif; color: #3a271a; }
        .header { text-align: left; border-bottom: 2px solid #c99a2e; padding-bottom: 12px; margin-bottom: 18px; }
        table.brand { width: 100%; border-collapse: collapse; margin: 0 0 12px 0; }
        table.brand td { border: none; padding: 0; vertical-align: middle; background: transparent; }
        td.brand-logo { width: 50px; }
        td.brand-name { text-align: left; }
        .brand-church { font-family: "DejaVu Serif", serif; font-size: 11px; font-weight: bold; color: #2a1b12; }
        .brand-sub { font-size: 9px; color: #b08020; letter-spacing: 1px; margin-top: 2px; }
        td.brand-plat { text-align: right; font-family: "DejaVu Serif", serif; font-size: 12px; font-weight: bold; letter-spacing: 1px; color: #2a1b12; }
        td.brand-plat span { color: #c99a2e; }
        .header p { color: #7d6a5b; font-size: 9px; margin: 2px 0; text-align: left; }
        .header p.doc-title { font-family: "DejaVu Serif", serif; font-size: 17px; font-weight: bold; color: #2a1b12; margin: 4px 0 6px; }
        th { background: #3a271a; color: #fbf8f1; font-size: 8.5px; padding: 6px 6px; border: 1px solid #3a271a; }
        td { border: 1px solid #ece4d2; color: #3a271a; }
        tr.total-row td, .total-row td, .total-row { background: #fcf8ec; border-top: 2px solid #c99a2e; font-weight: bold; }
        .footer { border-top: 1px solid #c99a2e; margin-top: 28px; padding-top: 8px; color: #a39485; font-size: 8px; text-align: center; }
        .assinatura .line { border-top: none; color: #3a271a; }
    ';
}
