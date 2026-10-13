<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Exportações de cópia de segurança sem segredos de autenticação ou dados globais. */
final class BackupRepository
{
    public function snapshot(int $churchId): array
    {
        return [
            'configuracoes' => $this->one(
                'SELECT nome_exibicao, subtitulo, rodape, versiculo, valor_cota, periodicidade_cota, '
                . 'inicio_cotas, inicio_contribuicoes, moeda FROM configuracoes_igreja WHERE igreja_id = :igreja_id LIMIT 1',
                $churchId
            ),
            'utilizadores' => $this->rows(
                'SELECT id, nome_completo, email, papel, modulos_permitidos, ativo, '
                . 'email_verificado_em, ultimo_login, criado_em FROM usuarios '
                . "WHERE igreja_id = :igreja_id AND papel <> 'superadmin' ORDER BY id",
                $churchId
            ),
            'permissoes_modulo' => $this->rows(
                'SELECT usuario_id, modulo, permitido, criado_em, actualizado_em FROM permissoes_modulo '
                . 'WHERE igreja_id = :igreja_id ORDER BY usuario_id, modulo',
                $churchId
            ),
            'membros' => $this->rows(
                'SELECT id, nome_completo, categoria, telefone, endereco, data_nascimento, ativo, criado_em '
                . 'FROM membros WHERE igreja_id = :igreja_id ORDER BY id',
                $churchId
            ),
            'cotas_diarias' => $this->rows(
                'SELECT id, membro_id, data_pagamento, mes_referencia, valor, observacao, registrado_por, criado_em '
                . 'FROM cotas_diarias WHERE igreja_id = :igreja_id ORDER BY id',
                $churchId
            ),
            'contribuicao_fim_ano' => $this->rows(
                'SELECT id, membro_id, ano, valor, data_pagamento, registrado_por '
                . 'FROM contribuicao_fim_ano WHERE igreja_id = :igreja_id ORDER BY id',
                $churchId
            ),
            'saidas' => $this->rows(
                'SELECT id, descricao, valor, data_saida, categoria, origem_fundo, beneficiario, registrado_por, criado_em '
                . 'FROM saidas WHERE igreja_id = :igreja_id ORDER BY id',
                $churchId
            ),
            'logs_auditoria' => $this->rows(
                'SELECT id, usuario_id, usuario_nome, acao, modulo, descricao, dados_anteriores, dados_novos, '
                . 'ip, user_agent, criado_em FROM logs_auditoria WHERE igreja_id = :igreja_id ORDER BY id',
                $churchId
            ),
        ];
    }

    public function counts(int $churchId): array
    {
        $tables = [
            'usuarios' => 'usuarios',
            'permissoes_modulo' => 'permissoes_modulo',
            'membros' => 'membros',
            'cotas_diarias' => 'cotas_diarias',
            'contribuicao_fim_ano' => 'contribuicao_fim_ano',
            'saidas' => 'saidas',
            'logs_auditoria' => 'logs_auditoria',
        ];
        $counts = [];
        foreach ($tables as $key => $table) {
            $statement = Database::connection()->prepare(
                'SELECT COUNT(*) FROM ' . $table . ' WHERE igreja_id = :igreja_id'
            );
            $statement->execute([':igreja_id' => $churchId]);
            $counts[$key] = (int) $statement->fetchColumn();
        }
        return $counts;
    }

    private function rows(string $sql, int $churchId): array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute([':igreja_id' => $churchId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function one(string $sql, int $churchId): ?array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute([':igreja_id' => $churchId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
