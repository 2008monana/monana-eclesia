<?php
namespace App\Services;

use App\Repositories\AuditRepository;
use DateTimeImmutable;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Filtros e leitura da auditoria da igreja autenticada. */
final class AuditoriaConsultaService
{
    private $repository;

    public function __construct(?AuditRepository $repository = null)
    {
        $this->repository = $repository ?: new AuditRepository();
    }

    public function listar(int $churchId, array $input): array
    {
        $userId = filter_var($input['usuario_id'] ?? 0, FILTER_VALIDATE_INT);
        $userId = $userId === false || $userId < 1 ? 0 : (int) $userId;
        $module = $this->filterText($input['modulo'] ?? '', 80);
        $action = $this->filterText($input['acao'] ?? '', 80);
        $start = $this->parseDate($input['data_inicio'] ?? null);
        $end = $this->parseDate($input['data_fim'] ?? null);
        if ($start !== null && $end !== null && $start > $end) {
            $swap = $start;
            $start = $end;
            $end = $swap;
        }
        $filters = [
            'usuario_id' => $userId,
            'modulo' => $module,
            'acao' => $action,
            'inicio' => $start ? $start->format('Y-m-d') : '',
            'fim_exclusivo' => $end ? $end->modify('+1 day')->format('Y-m-d') : '',
        ];
        return [
            'logs' => $this->repository->listarDaIgreja($churchId, $filters),
            'utilizadores' => $this->repository->utilizadoresDaIgreja($churchId),
            'modulos' => $this->repository->modulosDaIgreja($churchId),
            'acoes' => $this->repository->acoesDaIgreja($churchId),
            'totalGeral' => $this->repository->totalDaIgreja($churchId),
            'ultimoLogin' => $this->repository->ultimoLoginDaIgreja($churchId),
            'filtros' => [
                'usuario_id' => $userId,
                'modulo' => $module,
                'acao' => $action,
                'data_inicio' => $start ? $start->format('Y-m-d') : '',
                'data_fim' => $end ? $end->format('Y-m-d') : '',
            ],
        ];
    }

    private function filterText($value, int $max): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        return strlen($value) <= $max ? $value : '';
    }

    private function parseDate($value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date instanceof DateTimeImmutable
            || ($errors !== false && ((int) $errors['warning_count'] > 0 || (int) $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            return null;
        }
        return $date;
    }
}
