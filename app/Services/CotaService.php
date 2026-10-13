<?php
namespace App\Services;

use App\Core\Request;
use App\Core\Validator;
use App\Repositories\CotaDiariaRepository;
use DateTimeImmutable;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Regras de negócio das cotas, incluindo ciclos anuais móveis de 12 meses. */
final class CotaService
{
    /** @var CotaDiariaRepository */
    private $repository;

    /** @var AuditoriaService */
    private $audit;

    public function __construct(?CotaDiariaRepository $repository = null, ?AuditoriaService $audit = null)
    {
        $this->repository = $repository ?: new CotaDiariaRepository();
        $this->audit = $audit ?: new AuditoriaService();
    }

    public function listar(int $churchId, $date): array
    {
        $today = date('Y-m-d');
        $validator = new Validator(['data' => $date]);
        if (!$validator->validate(['data' => 'required|date'])) {
            $date = $today;
        }
        $date = (string) $date;
        $records = $this->repository->listarPorData($churchId, $date);
        return [
            'registos' => $records,
            'data' => $date,
            'hoje' => $today,
            'total' => count($records),
            'configuracao' => $this->repository->configuracao($churchId),
        ];
    }

    public function formulario(int $churchId): array
    {
        return [
            'membros' => $this->repository->membrosActivos($churchId),
            'configuracao' => $this->repository->configuracao($churchId),
        ];
    }

    public function encontrar(int $churchId, int $paymentId): ?array
    {
        if ($churchId < 1 || $paymentId < 1) {
            return null;
        }
        return $this->repository->encontrar($churchId, $paymentId);
    }

    public function validar(array $input): array
    {
        $memberId = $input['membro_id'] ?? '';
        $amountInput = $input['valor'] ?? '';
        $amount = $this->parseMoney($amountInput);
        $note = $input['observacao'] ?? '';
        if (is_string($note)) {
            $note = trim($note);
        }

        $validator = new Validator([
            'membro_id' => $memberId,
            'valor' => $amount === null ? '' : $amount,
            'observacao' => $note,
        ]);
        $valid = $validator->validate([
            'membro_id' => 'required|integer|min:1',
            'valor' => 'required|numeric|min:0.01|max:99999999999.99',
            'observacao' => 'nullable|string|max:1000',
        ]);
        $values = $validator->validated();
        $display = [
            'membro_id' => $this->displayValue($memberId),
            'valor' => $this->displayValue($amountInput),
            'observacao' => $this->displayValue($note),
        ];
        if ($valid) {
            $display = [
                'membro_id' => (int) $values['membro_id'],
                'valor' => round((float) $values['valor'], 2),
                'observacao' => (string) ($values['observacao'] ?? ''),
            ];
        }
        return ['valido' => $valid, 'dados' => $display, 'erros' => $validator->firstErrors()];
    }

    public function criar(int $churchId, int $actorId, array $input, Request $request): array
    {
        $validation = $this->validar($input);
        if (!$validation['valido']) {
            return ['valido' => false, 'dados' => $validation['dados'], 'erros' => $validation['erros']];
        }

        $data = $validation['dados'];
        $member = $this->repository->membroActivo($churchId, (int) $data['membro_id']);
        if ($member === null) {
            return $this->failure($data, 'membro_id', 'Selecciona um membro activo desta igreja.');
        }
        $reference = $this->proximaReferencia($churchId, (int) $data['membro_id']);
        if (empty($reference['valido'])) {
            return $this->failure($data, 'valor', (string) $reference['erro']);
        }

        $paymentData = [
            'membro_id' => (int) $data['membro_id'],
            'data_pagamento' => date('Y-m-d'),
            'mes_referencia' => $reference['data'],
            'valor' => (float) $data['valor'],
            'observacao' => $data['observacao'] !== '' ? $data['observacao'] : null,
            'registrado_por' => $actorId,
        ];
        $paymentId = $this->repository->criar($churchId, $paymentData);
        $this->audit->registar(
            'criar',
            'registry',
            'Registou uma cota de ' . $this->formatarKz($paymentData['valor'])
                . ' Kz para ' . (string) $member['nome_completo']
                . ' com referência ' . $this->rotuloReferencia($paymentData['mes_referencia'], $reference['periodicidade']) . '.',
            $request,
            null,
            null,
            $churchId,
            null,
            ['id' => $paymentId, 'membro_id' => $paymentData['membro_id'], 'valor' => $paymentData['valor'], 'mes_referencia' => $paymentData['mes_referencia']]
        );
        return ['valido' => true, 'id' => $paymentId, 'referencia' => $paymentData['mes_referencia'], 'periodicidade' => $reference['periodicidade']];
    }

    public function actualizar(int $churchId, int $paymentId, array $input, Request $request): array
    {
        $existing = $this->repository->encontrar($churchId, $paymentId);
        if ($existing === null) {
            return ['encontrado' => false, 'valido' => false, 'erros' => []];
        }
        $today = date('Y-m-d');
        if ((string) $existing['data_pagamento'] !== $today) {
            return ['encontrado' => true, 'valido' => false, 'bloqueado' => true, 'erros' => []];
        }

        $validation = $this->validar($input);
        if (!$validation['valido']) {
            return [
                'encontrado' => true,
                'valido' => false,
                'dados' => $validation['dados'],
                'erros' => $validation['erros'],
                'registo' => $existing,
            ];
        }
        $data = $validation['dados'];
        $member = $this->repository->membroActivo($churchId, (int) $data['membro_id']);
        if ($member === null) {
            return [
                'encontrado' => true,
                'valido' => false,
                'dados' => $data,
                'erros' => ['membro_id' => 'Selecciona um membro activo desta igreja.'],
                'registo' => $existing,
            ];
        }
        $reference = $this->proximaReferencia($churchId, (int) $data['membro_id'], $paymentId);
        if (empty($reference['valido'])) {
            return [
                'encontrado' => true,
                'valido' => false,
                'dados' => $data,
                'erros' => ['valor' => (string) $reference['erro']],
                'registo' => $existing,
            ];
        }
        $updated = $this->repository->actualizar($churchId, $paymentId, [
            'membro_id' => (int) $data['membro_id'],
            'data_pagamento' => $today,
            'mes_referencia' => $reference['data'],
            'valor' => (float) $data['valor'],
            'observacao' => $data['observacao'] !== '' ? $data['observacao'] : null,
        ]);
        if (!$updated) {
            return ['encontrado' => false, 'valido' => false, 'erros' => []];
        }
        $this->audit->registar(
            'editar',
            'registry',
            'Editou o registo de cota #' . $paymentId . '.',
            $request,
            null,
            null,
            $churchId,
            ['membro_id' => (int) $existing['membro_id'], 'valor' => (float) $existing['valor'], 'mes_referencia' => (string) $existing['mes_referencia']],
            ['membro_id' => (int) $data['membro_id'], 'valor' => (float) $data['valor'], 'mes_referencia' => $reference['data']]
        );
        return ['encontrado' => true, 'valido' => true, 'referencia' => $reference['data']];
    }

    public function apagar(int $churchId, int $paymentId, Request $request): array
    {
        $existing = $this->repository->encontrar($churchId, $paymentId);
        if ($existing === null) {
            return ['encontrado' => false, 'apagado' => false];
        }
        $today = date('Y-m-d');
        if ((string) $existing['data_pagamento'] !== $today) {
            return ['encontrado' => true, 'apagado' => false, 'bloqueado' => true];
        }
        if (!$this->repository->apagarHoje($churchId, $paymentId, $today)) {
            return ['encontrado' => false, 'apagado' => false];
        }
        $this->audit->registar(
            'excluir',
            'registry',
            'Excluiu o registo de cota #' . $paymentId . ' de ' . $this->formatarKz((float) $existing['valor']) . ' Kz.',
            $request,
            null,
            null,
            $churchId,
            ['id' => $paymentId, 'membro_id' => (int) $existing['membro_id'], 'valor' => (float) $existing['valor']],
            null
        );
        return ['encontrado' => true, 'apagado' => true];
    }

    /** Próximo período ainda não coberto, respeitando a regra RN01–RN04. */
    private function proximaReferencia(int $churchId, int $memberId, ?int $excludeId = null): array
    {
        $config = $this->repository->configuracao($churchId);
        $quota = round((float) ($config['valor_cota'] ?? 0), 2);
        $periodicity = (string) ($config['periodicidade_cota'] ?? '');
        $startRaw = (string) ($config['inicio_cotas'] ?? '');
        $start = $this->isoDate($startRaw);
        if ($quota <= 0) {
            return ['valido' => false, 'erro' => 'A igreja ainda não definiu um valor de cota.'];
        }
        if (!in_array($periodicity, ['mensal', 'anual'], true)) {
            return ['valido' => false, 'erro' => 'A periodicidade da cota ainda não está configurada.'];
        }
        if (!$start instanceof DateTimeImmutable) {
            return ['valido' => false, 'erro' => 'Define o início das cotas nas configurações da igreja antes de registar pagamentos.'];
        }
        $today = new DateTimeImmutable('today');
        if ($start > $today) {
            return ['valido' => false, 'erro' => 'A data de início das cotas ainda não foi alcançada.'];
        }

        if ($periodicity === 'mensal') {
            return $this->proximaReferenciaMensal($churchId, $memberId, $start, $today, $quota, $excludeId);
        }
        return $this->proximaReferenciaAnual($churchId, $memberId, $start, $today, $quota, $excludeId);
    }

    private function proximaReferenciaMensal(
        int $churchId,
        int $memberId,
        DateTimeImmutable $start,
        DateTimeImmutable $today,
        float $quota,
        ?int $excludeId
    ): array {
        $startMonth = $start->setDate((int) $start->format('Y'), (int) $start->format('m'), 1);
        $currentMonth = $today->setDate((int) $today->format('Y'), (int) $today->format('m'), 1);
        $currentYear = (int) $today->format('Y');
        $firstMonth = $currentYear === (int) $startMonth->format('Y')
            ? $startMonth
            : $today->setDate($currentYear, 1, 1);
        $total = $this->repository->totalPorReferencia(
            $churchId,
            $memberId,
            $firstMonth->format('Y-m-d'),
            $currentMonth->format('Y-m-d'),
            $excludeId
        );
        $periodsDue = (($currentYear - (int) $firstMonth->format('Y')) * 12)
            + ((int) $currentMonth->format('m') - (int) $firstMonth->format('m')) + 1;
        $index = min(max(0, $periodsDue - 1), (int) floor($total / $quota));
        $reference = $firstMonth->modify('+' . $index . ' months');
        return ['valido' => true, 'data' => $reference->format('Y-m-d'), 'periodicidade' => 'mensal'];
    }

    private function proximaReferenciaAnual(
        int $churchId,
        int $memberId,
        DateTimeImmutable $start,
        DateTimeImmutable $today,
        float $quota,
        ?int $excludeId
    ): array {
        $yearOffset = (int) $today->format('Y') - (int) $start->format('Y');
        $cycleIndex = max(0, $yearOffset);
        while ($cycleIndex > 0 && $this->aniversario($start, $cycleIndex) > $today) {
            $cycleIndex--;
        }
        while ($this->aniversario($start, $cycleIndex + 1) <= $today) {
            $cycleIndex++;
        }
        $currentCycle = $this->aniversario($start, $cycleIndex);
        $total = $this->repository->totalPorReferencia(
            $churchId,
            $memberId,
            $start->format('Y-m-d'),
            $currentCycle->format('Y-m-d'),
            $excludeId
        );
        $targetIndex = min($cycleIndex, (int) floor($total / $quota));
        $reference = $this->aniversario($start, $targetIndex);
        return ['valido' => true, 'data' => $reference->format('Y-m-d'), 'periodicidade' => 'anual'];
    }

    /** Aniversário anual com ajuste para o último dia quando o mês não tem o dia-âncora. */
    private function aniversario(DateTimeImmutable $anchor, int $years): DateTimeImmutable
    {
        $year = (int) $anchor->format('Y') + $years;
        $month = (int) $anchor->format('m');
        $day = min((int) $anchor->format('d'), (int) $anchor->setDate($year, $month, 1)->format('t'));
        return $anchor->setDate($year, $month, $day)->setTime(0, 0, 0);
    }

    private function isoDate(string $date): ?DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$parsed instanceof DateTimeImmutable
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date
        ) {
            return null;
        }
        return $parsed;
    }

    private function failure(array $data, string $field, string $message): array
    {
        return ['valido' => false, 'dados' => $data, 'erros' => [$field => $message]];
    }

    private function parseMoney($value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? round((float) $value, 2) : null;
        }
        if (!is_string($value)) {
            return null;
        }
        $value = trim(str_replace(["\xc2\xa0", ' ', 'Kz', 'kz'], '', $value));
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
        $amount = (float) $value;
        return is_finite($amount) ? round($amount, 2) : null;
    }

    private function displayValue($value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }

    private function rotuloReferencia(string $reference, string $periodicity): string
    {
        if ($periodicity === 'mensal') {
            return date('m/Y', strtotime($reference));
        }
        return date('d/m/Y', strtotime($reference));
    }

    private function formatarKz(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }
}
