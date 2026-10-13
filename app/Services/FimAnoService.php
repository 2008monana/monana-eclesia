<?php
namespace App\Services;

use App\Core\Request;
use App\Core\Validator;
use App\Repositories\ContribuicaoFimAnoRepository;
use DateTimeImmutable;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Contribuições de fim de ano, com limite de 5.000 Kz por membro/ano. */
final class FimAnoService
{
    private const META_ANUAL = 5000.00;
    /** @var ContribuicaoFimAnoRepository */
    private $repository;
    /** @var AuditoriaService */
    private $audit;

    public function __construct(?ContribuicaoFimAnoRepository $repository = null, ?AuditoriaService $audit = null)
    {
        $this->repository = $repository ?: new ContribuicaoFimAnoRepository();
        $this->audit = $audit ?: new AuditoriaService();
    }

    public function listar(int $churchId, $yearInput, $categoryInput): array
    {
        $year = filter_var($yearInput, FILTER_VALIDATE_INT);
        $year = $year === false || $year < 2000 || $year > 2100 ? (int) date('Y') : (int) $year;
        $category = is_string($categoryInput) ? $categoryInput : 'todos';
        if (!in_array($category, ['todos', 'mama', 'papa', 'jovem'], true)) {
            $category = 'todos';
        }
        $start = $this->repository->inicio($churchId);
        $startDate = $this->parseStartDate($start);
        $enabled = $startDate instanceof DateTimeImmutable
            && $year >= (int) $startDate->format('Y')
            && $startDate <= new DateTimeImmutable('today');
        $totals = $this->repository->totaisPorAno($churchId, $year);
        $payments = $this->repository->pagamentos($churchId, $year);
        $paymentsByMember = [];
        foreach ($payments as $payment) {
            $paymentsByMember[(int) $payment['membro_id']][] = $payment;
        }
        $members = $this->repository->membros($churchId, $category);
        $collected = $this->repository->totalArrecadado($churchId, $year);
        $paidMembers = 0;
        $settledMembers = 0;
        foreach ($members as &$member) {
            $id = (int) $member['id'];
            $total = (float) ($totals[$id]['total_pago'] ?? 0);
            $member['total_pago'] = round($total, 2);
            $member['quantidade_pagamentos'] = (int) ($totals[$id]['quantidade'] ?? 0);
            $member['saldo_restante'] = max(0, round(self::META_ANUAL - $total, 2));
            $member['esta_quitado'] = $total >= self::META_ANUAL;
            $member['pagamentos'] = $paymentsByMember[$id] ?? [];
            if ($total > 0) {
                $paidMembers++;
            }
            if ($member['esta_quitado']) {
                $settledMembers++;
            }
        }
        unset($member);
        $expenses = $this->repository->totalDespesas($churchId, $year);
        return [
            'membros' => $members,
            'ano' => $year,
            'categoria' => $category,
            'inicio' => $start,
            'configurada' => $enabled,
            'metaAnual' => self::META_ANUAL,
            'totalMembros' => count($members),
            'membrosComPagamento' => $paidMembers,
            'membrosQuitados' => $settledMembers,
            'totalArrecadado' => round($collected, 2),
            'totalSaidas' => $expenses,
            'saldoFundo' => round($collected - $expenses, 2),
        ];
    }

    public function validarPagamento(array $input): array
    {
        $memberId = $input['membro_id'] ?? '';
        $year = $input['ano'] ?? '';
        $amount = $this->parseMoney($input['valor'] ?? '');
        $validator = new Validator([
            'membro_id' => $memberId,
            'ano' => $year,
            'valor' => $amount,
        ]);
        $valid = $validator->validate([
            'membro_id' => 'required|integer|min:1',
            'ano' => 'required|integer|min:2000|max:2100',
            'valor' => 'required|numeric|min:0.01|max:5000',
        ]);
        $values = $validator->validated();
        return [
            'valido' => $valid,
            'dados' => $valid ? [
                'membro_id' => (int) $values['membro_id'],
                'ano' => (int) $values['ano'],
                'valor' => round((float) $values['valor'], 2),
            ] : [],
            'erros' => $validator->firstErrors(),
        ];
    }

    public function registar(int $churchId, int $actorId, array $input, Request $request): array
    {
        $validation = $this->validarPagamento($input);
        if (!$validation['valido']) {
            return ['valido' => false, 'erros' => $validation['erros']];
        }
        $data = $validation['dados'];
        $start = $this->repository->inicio($churchId);
        $startDate = $this->parseStartDate($start);
        if (!$startDate instanceof DateTimeImmutable
            || $data['ano'] < (int) $startDate->format('Y')
            || $startDate > new DateTimeImmutable('today')
        ) {
            return ['valido' => false, 'erros' => ['ano' => 'Define o início das contribuições e aguarda a data indicada antes de registar valores.']];
        }
        $member = $this->repository->membro($churchId, $data['membro_id']);
        if ($member === null) {
            return ['valido' => false, 'erros' => ['membro_id' => 'Selecciona um membro activo desta igreja.']];
        }
        $totals = $this->repository->totaisPorAno($churchId, $data['ano']);
        $alreadyPaid = (float) ($totals[$data['membro_id']]['total_pago'] ?? 0);
        $remaining = max(0, round(self::META_ANUAL - $alreadyPaid, 2));
        if ($data['valor'] > $remaining) {
            return ['valido' => false, 'erros' => ['valor' => 'O valor excede o saldo restante de ' . $this->kz($remaining) . ' Kz.']];
        }
        $id = $this->repository->criar($churchId, $data['membro_id'], $data['ano'], $data['valor'], $actorId);
        $this->audit->registar('registar', 'year_end', 'Registou uma contribuição de fim de ano de ' . $this->kz($data['valor']) . ' Kz.', $request, null, null, $churchId, null, ['id' => $id, 'membro_id' => $data['membro_id'], 'ano' => $data['ano'], 'valor' => $data['valor']]);
        return ['valido' => true, 'id' => $id];
    }

    public function alternarEstado(int $churchId, int $actorId, int $memberId, int $year, int $action, Request $request): array
    {
        $member = $this->repository->membro($churchId, $memberId);
        if ($member === null) {
            return ['encontrado' => false, 'sucesso' => false];
        }
        $start = $this->repository->inicio($churchId);
        $startDate = $this->parseStartDate($start);
        if (!$startDate instanceof DateTimeImmutable
            || $year < (int) $startDate->format('Y')
            || $startDate > new DateTimeImmutable('today')
        ) {
            return ['encontrado' => true, 'sucesso' => false, 'erro' => 'Define o início das contribuições e aguarda a data indicada.'];
        }
        if ($action === 1) {
            $totals = $this->repository->totaisPorAno($churchId, $year);
            if ((float) ($totals[$memberId]['total_pago'] ?? 0) > 0) {
                return ['encontrado' => true, 'sucesso' => false, 'erro' => 'Este membro já tem pagamentos. Regista apenas o saldo em falta.'];
            }
            $id = $this->repository->criar($churchId, $memberId, $year, self::META_ANUAL, $actorId);
            $this->audit->registar('marcar_pago', 'year_end', 'Marcou a contribuição anual do membro #' . $memberId . ' como quitada.', $request, null, null, $churchId, null, ['id' => $id, 'membro_id' => $memberId, 'ano' => $year, 'valor' => self::META_ANUAL]);
            return ['encontrado' => true, 'sucesso' => true];
        }
        $removed = $this->repository->apagarAnoMembro($churchId, $memberId, $year);
        $this->audit->registar('desmarcar', 'year_end', 'Removeu contribuições do membro #' . $memberId . ' no ano ' . $year . '.', $request, null, null, $churchId, ['membro_id' => $memberId, 'ano' => $year], null);
        return ['encontrado' => true, 'sucesso' => true, 'removidos' => $removed];
    }

    public function remover(int $churchId, int $memberId, int $year, Request $request): bool
    {
        $member = $this->repository->membro($churchId, $memberId);
        if ($member === null) {
            return false;
        }
        $removed = $this->repository->apagarAnoMembro($churchId, $memberId, $year);
        if ($removed > 0) {
            $this->audit->registar('remover', 'year_end', 'Removeu contribuições de fim de ano do membro #' . $memberId . ' (' . $year . ').', $request, null, null, $churchId, ['membro_id' => $memberId, 'ano' => $year], null);
        }
        return $removed > 0;
    }

    public function saldo(int $churchId, int $memberId, int $year): array
    {
        $member = $this->repository->membro($churchId, $memberId);
        if ($member === null) {
            return ['encontrado' => false];
        }
        $totals = $this->repository->totaisPorAno($churchId, $year);
        $paid = round((float) ($totals[$memberId]['total_pago'] ?? 0), 2);
        return [
            'encontrado' => true,
            'total_pago' => $paid,
            'saldo_restante' => max(0, round(self::META_ANUAL - $paid, 2)),
            'esta_quitado' => $paid >= self::META_ANUAL,
        ];
    }

    private function parseStartDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
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

    private function parseMoney($value): ?float
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $value = trim(str_replace(["\xc2\xa0", ' ', 'Kz', 'kz'], '', (string) $value));
        if ($value === '') {
            return null;
        }
        if (strpos($value, ',') !== false) {
            if (!preg_match('/^\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?$|^\d+(?:,\d{1,2})?$/', $value)) {
                return null;
            }
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $value)) {
            $value = str_replace('.', '', $value);
        } elseif (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            return null;
        }
        return is_finite((float) $value) ? round((float) $value, 2) : null;
    }

    private function kz(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }
}
