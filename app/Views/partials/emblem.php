<?php
/**
 * Emblema "ME" da Monana Eclesia em SVG (extraído do protótipo).
 *
 * Uso:  <?= emblem(190) ?>
 *       <?= emblem(48) ?>
 */
if (!function_exists('emblem')) {
    function emblem(int $size = 48): string
    {
        $id = 'emblemGrad' . $size;
        return <<<SVG
<svg width="{$size}" height="{$size}" viewBox="0 0 200 200" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="{$id}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#e3b64a" />
      <stop offset=".45" stop-color="#c9962f" />
      <stop offset="1" stop-color="#9c6d1c" />
    </linearGradient>
  </defs>
  <g fill="url(#{$id})" stroke="none">
    <rect x="96" y="2" width="8" height="34" />
    <rect x="85" y="10" width="30" height="7" />
    <path d="M80 24 A82 82 0 0 0 72 182 L74 178 A78 78 0 0 1 82 28 Z" />
    <path d="M120 24 A82 82 0 0 1 128 182 L126 178 A78 78 0 0 0 118 28 Z" />
    <path d="M100 32 C96 52 82 64 60 80 L62 82 C84 70 96 60 100 46 C104 60 116 70 138 82 L140 80 C118 64 104 52 100 32 Z" />
    <path d="M93 68 a7 7 0 0 1 14 0 v14 h-14 Z" />
  </g>
  <rect x="99.2" y="62" width="1.6" height="20" fill="#fff" />
  <rect x="93" y="72" width="14" height="1.6" fill="#fff" />
  <text x="100" y="152" text-anchor="middle" font-family="Cinzel, Georgia, serif" font-weight="600" font-size="84" fill="url(#{$id})" letter-spacing="-10">ME</text>
  <g fill="url(#{$id})">
    <circle cx="82" cy="160" r="5" />
    <circle cx="100" cy="155" r="6" />
    <circle cx="118" cy="160" r="5" />
    <path d="M72 168 q10 -2 14 8 v6 h-4 q0 -8 -10 -14 Z" />
    <path d="M128 168 q-10 -2 -14 8 v6 h4 q0 -8 10 -14 Z" />
    <path d="M88 166 q12 -6 24 0 q-8 4 -10 16 h-4 q-2 -12 -10 -16 Z" />
    <path d="M58 188 q42 -14 84 0 q-42 -8 -84 0 Z" />
  </g>
</svg>
SVG;
    }
}