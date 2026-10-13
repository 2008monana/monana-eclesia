<?php
namespace App\Controllers\Igreja;

use App\Core\Response;
use App\Services\AuditoriaService;
use App\Services\BackupService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Exporta cópias JSON completas apenas da igreja autenticada, sem segredos. */
final class BackupController extends BaseIgrejaController
{
    public function index(): Response
    {
        $guard = $this->authorizeModule('backups', ['admin_igreja']);
        if ($guard instanceof Response) {
            return $guard;
        }
        return $this->renderChurchModule('igreja/backups/index', (new BackupService())->resumo($this->churchId), 'Cópias de segurança', 'backups');
    }

    public function exportar(): Response
    {
        $guard = $this->authorizeModule('backups', ['admin_igreja']);
        if ($guard instanceof Response) {
            return $guard;
        }
        $result = (new BackupService())->exportar($this->churchId);
        if (empty($result['sucesso'])) {
            return Response::text((string) ($result['erro'] ?? 'Não foi possível gerar a cópia.'), 413);
        }
        (new AuditoriaService())->registar(
            'exportar', 'backups', 'Exportou uma cópia JSON tenant-scoped da igreja.',
            $this->request, (int) ($this->actor['id'] ?? 0), null, $this->churchId,
            null, ['quantidades' => $result['quantidades'] ?? []]
        );
        $filename = 'monana-backup-igreja-' . $this->churchId . '-' . date('Ymd-His') . '.json';
        return new Response((string) $result['conteudo'], 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
