<?php
namespace App\Services;

use App\Repositories\RelatorioRepository;
use DateTimeImmutable;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Prepara relatórios por período sem expor dados de outras igrejas. */
final class RelatorioService
{
    private $repository;

    public function __construct(?RelatorioRepository $repository = null)
    {
        $this->repository = $repository ?: new RelatorioRepository();
    }

    public function periodo(int $churchId, $startInput, $endInput): array
    {
        $today = new DateTimeImmutable('today');
        $start = $this->parseDate($startInput) ?: $today->setDate((int) $today->format('Y'), (int) $today->format('n'), 1);
        $end = $this->parseDate($endInput) ?: $today;
        if ($start > $end) {
            $swap = $start;
            $start = $end;
            $end = $swap;
        }
        if ($start->diff($end)->days > 3660) {
            $start = $end->modify('-10 years');
        }
        $startValue = $start->format('Y-m-d');
        $endValue = $end->format('Y-m-d');
        return [
            'inicio' => $startValue,
            'fim' => $endValue,
            'resumo' => $this->repository->resumo($churchId, $startValue, $endValue),
            'categorias' => $this->repository->despesasPorCategoria($churchId, $startValue, $endValue),
        ];
    }

    public function anual(int $churchId, $yearInput): array
    {
        $year = filter_var($yearInput, FILTER_VALIDATE_INT);
        $year = $year === false || $year < 2000 || $year > 2100 ? (int) date('Y') : (int) $year;
        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-12-31', $year);
        return [
            'ano' => $year,
            'resumo' => $this->repository->resumo($churchId, $start, $end),
            'serie' => $this->repository->serieMensal($churchId, $year),
        ];
    }

    public function saidas(int $churchId, $start, $end): array
    {
        return $this->periodo($churchId, $start, $end);
    }

    private function parseDate($value): ?DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date instanceof DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            return null;
        }
        return $date;
    }
}
