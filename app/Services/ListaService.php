<?php
namespace App\Services;

use App\Repositories\ListaRepository;
use DateTimeImmutable;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Prepara grelhas dominicais e saldos acumulados das listas. */
final class ListaService
{
    /** @var ListaRepository */
    private $repository;

    public function __construct(?ListaRepository $repository = null)
    {
        $this->repository = $repository ?: new ListaRepository();
    }

    public function listar(int $churchId, $monthInput, $yearInput, $categoryInput): array
    {
        $month = filter_var($monthInput, FILTER_VALIDATE_INT);
        $year = filter_var($yearInput, FILTER_VALIDATE_INT);
        $month = $month === false || $month < 1 || $month > 12 ? (int) date('n') : (int) $month;
        $year = $year === false || $year < 2000 || $year > 2100 ? (int) date('Y') : (int) $year;
        $category = is_string($categoryInput) ? $categoryInput : 'todos';
        if (!in_array($category, ['todos', 'mama', 'papa', 'jovem', 'crianca'], true)) {
            $category = 'todos';
        }

        $monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $monthEnd = $monthStart->modify('last day of this month');
        $today = new DateTimeImmutable('today');
        $currentMonth = $today->setDate((int) $today->format('Y'), (int) $today->format('n'), 1);
        $statusMonth = $monthStart > $currentMonth ? $currentMonth : $monthStart;
        $statusDate = $monthEnd > $today ? $today : $monthEnd;
        $config = $this->repository->configuracao($churchId);
        $quota = round((float) ($config['valor_cota'] ?? 0), 2);
        $periodicity = (string) ($config['periodicidade_cota'] ?? 'mensal');
        $start = $this->parseDate((string) ($config['inicio_cotas'] ?? ''));
        $duePeriods = 0;
        $rangeStart = null;
        $rangeEnd = null;

        if ($start instanceof DateTimeImmutable && $quota > 0 && in_array($periodicity, ['mensal', 'anual'], true)) {
            if ($periodicity === 'mensal'
                && $statusMonth >= $start->setDate((int) $start->format('Y'), (int) $start->format('m'), 1)
            ) {
                $startMonth = $start->setDate((int) $start->format('Y'), (int) $start->format('m'), 1);
                $statusYear = (int) $statusMonth->format('Y');
                $rangeStart = $statusYear === (int) $startMonth->format('Y')
                    ? $startMonth
                    : $statusMonth->setDate($statusYear, 1, 1);
                $rangeEnd = $statusMonth;
                $duePeriods = (($statusYear - (int) $rangeStart->format('Y')) * 12)
                    + ((int) $rangeEnd->format('n') - (int) $rangeStart->format('n')) + 1;
            } elseif ($periodicity === 'anual' && $statusDate >= $start) {
                $cycleIndex = $this->cycleIndex($start, $statusDate);
                $rangeStart = $start;
                $rangeEnd = $this->anniversary($start, $cycleIndex);
                $duePeriods = $cycleIndex + 1;
            }
        }

        $totals = [];
        if ($duePeriods > 0 && $rangeStart instanceof DateTimeImmutable && $rangeEnd instanceof DateTimeImmutable) {
            $totals = $this->repository->totaisPorReferencia(
                $churchId,
                $rangeStart->format('Y-m-d'),
                $rangeEnd->format('Y-m-d')
            );
        }
        $payments = $this->repository->pagamentosDoMes(
            $churchId,
            $monthStart->format('Y-m-d'),
            $monthEnd->format('Y-m-d')
        );
        $paymentMap = [];
        foreach ($payments as $payment) {
            $memberId = (int) ($payment['membro_id'] ?? 0);
            $date = (string) ($payment['data_pagamento'] ?? '');
            $paymentMap[$memberId][$date] = round((float) ($paymentMap[$memberId][$date] ?? 0) + (float) ($payment['valor'] ?? 0), 2);
        }

        $members = $this->repository->membros($churchId, $category);
        foreach ($members as &$member) {
            $memberId = (int) ($member['id'] ?? 0);
            $paid = round((float) ($totals[$memberId] ?? 0), 2);
            $paidPeriods = $quota > 0 ? (int) floor(($paid + 0.00001) / $quota) : 0;
            $member['total_pago'] = $paid;
            $member['periodos_devidos'] = $duePeriods;
            $member['periodos_quitados'] = min($duePeriods, $paidPeriods);
            $member['periodos_em_atraso'] = max(0, $duePeriods - $paidPeriods);
            $member['saldo'] = round($paid - ($duePeriods * $quota), 2);
            $member['estado_cota'] = $duePeriods === 0
                ? 'nao_iniciado'
                : ($paidPeriods >= $duePeriods ? 'quitado' : 'pendente');
            $member['pagamentos_mes'] = $paymentMap[$memberId] ?? [];
        }
        unset($member);

        return [
            'membros' => $members,
            'domingos' => $this->sundays($monthStart),
            'mes' => $month,
            'ano' => $year,
            'categoria' => $category,
            'configuracao' => $config,
            'cotaConfigurada' => $quota > 0 && $start instanceof DateTimeImmutable,
            'periodicidade' => $periodicity,
            'periodosDevidos' => $duePeriods,
            'totalMembros' => count($members),
            'totalQuitados' => count(array_filter($members, static function (array $member): bool {
                return ($member['estado_cota'] ?? '') === 'quitado';
            })),
            'totalEmAtraso' => count(array_filter($members, static function (array $member): bool {
                return ($member['estado_cota'] ?? '') === 'pendente';
            })),
        ];
    }

    private function sundays(DateTimeImmutable $monthStart): array
    {
        $sundays = [];
        $days = (int) $monthStart->format('t');
        for ($day = 1; $day <= $days; $day++) {
            $date = $monthStart->setDate(
                (int) $monthStart->format('Y'),
                (int) $monthStart->format('n'),
                $day
            );
            if ($date->format('w') === '0') {
                $sundays[] = $date->format('Y-m-d');
            }
        }
        return $sundays;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
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

    private function cycleIndex(DateTimeImmutable $anchor, DateTimeImmutable $date): int
    {
        $index = max(0, (int) $date->format('Y') - (int) $anchor->format('Y'));
        while ($index > 0 && $this->anniversary($anchor, $index) > $date) {
            $index--;
        }
        while ($this->anniversary($anchor, $index + 1) <= $date) {
            $index++;
        }
        return $index;
    }

    private function anniversary(DateTimeImmutable $anchor, int $years): DateTimeImmutable
    {
        $year = (int) $anchor->format('Y') + $years;
        $month = (int) $anchor->format('n');
        $monthStart = $anchor->setDate($year, $month, 1);
        $day = min((int) $anchor->format('j'), (int) $monthStart->format('t'));
        return $monthStart->setDate($year, $month, $day)->setTime(0, 0, 0);
    }
}
