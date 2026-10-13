<?php
namespace App\Services;

use App\Core\Database;
use App\Repositories\BackupRepository;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Cria uma cópia JSON completa da igreja sem credenciais nem dados de outros tenants. */
final class BackupService
{
    private const MAX_REGISTOS = 50000;
    private $repository;

    public function __construct(?BackupRepository $repository = null)
    {
        $this->repository = $repository ?: new BackupRepository();
    }

    public function resumo(int $churchId): array
    {
        $counts = $this->repository->counts($churchId);
        return [
            'quantidades' => $counts,
            'total' => array_sum($counts),
            'limite' => self::MAX_REGISTOS,
            'elegivel' => array_sum($counts) <= self::MAX_REGISTOS,
        ];
    }

    public function exportar(int $churchId): array
    {
        return Database::transaction(function () use ($churchId): array {
            $counts = $this->repository->counts($churchId);
            $totalRecords = array_sum($counts);
            if ($totalRecords > self::MAX_REGISTOS) {
                return ['sucesso' => false, 'quantidades' => $counts, 'erro' => 'A cópia ultrapassa o limite seguro de exportação. Contacta o suporte para uma cópia assistida.'];
            }
            $snapshot = $this->repository->snapshot($churchId);
            $document = [
                'formato' => 'monana-eclesia-backup-json',
                'versao' => 1,
                'gerado_em_utc' => gmdate('Y-m-d\TH:i:s\Z'),
                'escopo' => 'igreja_autenticada',
                'aviso' => 'Não contém senhas, hashes de senha, tokens, sessões nem payloads de email.',
                'quantidades' => $counts,
                'dados' => $snapshot,
            ];
            $json = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if (!is_string($json)) {
                return ['sucesso' => false, 'erro' => 'Não foi possível codificar a cópia de segurança.'];
            }
            return ['sucesso' => true, 'conteudo' => $json, 'quantidades' => $counts];
        });
    }
}
