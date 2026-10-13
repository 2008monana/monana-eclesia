<?php
if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}
$title = trim((string) ($title ?? 'Documento'));
if ($title === '') {
    $title = 'Documento';
}
$subtitle = trim((string) ($subtitle ?? ''));
$churchName = trim((string) ($churchName ?? 'Igreja'));
$churchShortName = trim((string) ($churchShortName ?? ''));
$generatedAtValue = $generatedAt ?? null;
if ($generatedAtValue instanceof \DateTimeInterface) {
    $generatedAt = $generatedAtValue->format('d/m/Y');
} elseif (is_int($generatedAtValue)) {
    $generatedAt = date('d/m/Y', $generatedAtValue);
} elseif ($generatedAtValue === null) {
    $generatedAt = (new \DateTimeImmutable())->format('d/m/Y');
} else {
    $generatedAt = trim((string) $generatedAtValue);
    $parsedDate = \DateTimeImmutable::createFromFormat('!d/m/Y', $generatedAt);
    $dateErrors = \DateTimeImmutable::getLastErrors();
    $validDate = $parsedDate instanceof \DateTimeImmutable
        && ($dateErrors === false
            || ((int) ($dateErrors['warning_count'] ?? 1) === 0 && (int) ($dateErrors['error_count'] ?? 1) === 0));
    if (!$validDate) {
        throw new \InvalidArgumentException('A data do PDF deve usar o formato dd/mm/aaaa.');
    }
    $generatedAt = $parsedDate->format('d/m/Y');
}
$content = (string) ($content ?? '');
if ($churchName === '') {
    $churchName = 'Igreja';
}
$logoPath = BASE_PATH . '/assets/img/logo-mark.png';
$logoDataUri = '';
if (is_readable($logoPath)) {
    $logoContents = file_get_contents($logoPath);
    if (is_string($logoContents) && $logoContents !== '') {
        $logoDataUri = 'data:image/png;base64,' . base64_encode($logoContents);
    }
}
?>
<!doctype html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — MonanaEclésia</title>
    <style><?php include BASE_PATH . '/app/Views/pdf/partials/estilos.php'; ?></style>
</head>
<body>
    <?php include BASE_PATH . '/app/Views/pdf/partials/cabecalho.php'; ?>
    <main class="pdf-content">
        <h1><?= e($title) ?></h1>
        <?php if ($subtitle !== ''): ?>
            <p class="pdf-subtitle"><?= e($subtitle) ?></p>
        <?php endif; ?>
        <?= $content ?>
    </main>
    <?php include BASE_PATH . '/app/Views/pdf/partials/rodape.php'; ?>
</body>
</html>
