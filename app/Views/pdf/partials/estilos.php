<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
?>
@page { margin: 22mm 15mm 20mm; }
* { box-sizing: border-box; }
body { margin: 0; color: #3a271a; font-family: "DejaVu Sans", sans-serif; font-size: 9pt; }
.pdf-header { margin-bottom: 16pt; padding-bottom: 10pt; border-bottom: 2px solid #c99a2e; }
.pdf-brand { width: 100%; border-collapse: collapse; }
.pdf-brand td { padding: 0; border: 0; vertical-align: middle; }
.pdf-logo-cell { width: 48pt; }
.pdf-logo { width: 38pt; height: auto; }
.pdf-church { color: #2a1b12; font-family: "DejaVu Serif", serif; font-size: 10pt; font-weight: bold; }
.pdf-church-short { margin-top: 3pt; color: #b08020; font-size: 8pt; letter-spacing: .6pt; }
.pdf-platform { color: #2a1b12; font-family: "DejaVu Serif", serif; font-size: 10pt; font-weight: bold; letter-spacing: .6pt; text-align: right; white-space: nowrap; }
.pdf-platform span { color: #c99a2e; }
.pdf-content h1 { margin: 0 0 6pt; color: #2a1b12; font-family: "DejaVu Serif", serif; font-size: 17pt; }
.pdf-subtitle { margin: 0 0 14pt; color: #7d6a5b; font-size: 9pt; }
.pdf-content table { width: 100%; border-collapse: collapse; }
.pdf-content th { padding: 6pt; border: 1px solid #3a271a; background: #3a271a; color: #fbf8f1; font-size: 8pt; text-align: left; }
.pdf-content td { padding: 5pt 6pt; border: 1px solid #ece4d2; color: #3a271a; }
.pdf-content tr.total-row td, .pdf-content .total-row td { border-top: 2px solid #c99a2e; background: #fcf8ec; font-weight: bold; }
.pdf-content p { line-height: 1.5; }
.pdf-footer { margin-top: 22pt; padding-top: 7pt; border-top: 1px solid #c99a2e; color: #a39485; font-size: 7.5pt; text-align: center; }
