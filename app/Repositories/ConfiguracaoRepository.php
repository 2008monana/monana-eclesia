<?php
namespace App\Repositories;

use App\Core\Database;
use PDO;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Configuração da igreja autenticada; igreja_id nunca vem do pedido. */
final class ConfiguracaoRepository
{
    public function encontrar(int $churchId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT igreja_id, nome_exibicao, subtitulo, rodape, versiculo, valor_cota, '
            . 'periodicidade_cota, inicio_cotas, inicio_contribuicoes, moeda '
            . 'FROM configuracoes_igreja WHERE igreja_id = :igreja_id LIMIT 1'
        );
        $statement->execute([':igreja_id' => $churchId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function existemCotas(int $churchId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM cotas_diarias WHERE igreja_id = :igreja_id LIMIT 1'
        );
        $statement->execute([':igreja_id' => $churchId]);
        return $statement->fetchColumn() !== false;
    }

    public function existemContribuicoesFimAno(int $churchId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM contribuicao_fim_ano WHERE igreja_id = :igreja_id LIMIT 1'
        );
        $statement->execute([':igreja_id' => $churchId]);
        return $statement->fetchColumn() !== false;
    }

    public function actualizar(int $churchId, array $data): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE configuracoes_igreja SET nome_exibicao = :nome_exibicao, '
            . 'subtitulo = :subtitulo, rodape = :rodape, versiculo = :versiculo, valor_cota = :valor_cota, '
            . 'periodicidade_cota = :periodicidade_cota, inicio_cotas = :inicio_cotas, '
            . 'inicio_contribuicoes = :inicio_contribuicoes WHERE igreja_id = :igreja_id'
        );
        $statement->execute([
            ':nome_exibicao' => $data['nome_exibicao'],
            ':subtitulo' => $data['subtitulo'],
            ':rodape' => $data['rodape'],
            ':versiculo' => $data['versiculo'],
            ':valor_cota' => $data['valor_cota'],
            ':periodicidade_cota' => $data['periodicidade_cota'],
            ':inicio_cotas' => $data['inicio_cotas'],
            ':inicio_contribuicoes' => $data['inicio_contribuicoes'],
            ':igreja_id' => $churchId,
        ]);
        return $statement->rowCount() > 0 || $this->encontrar($churchId) !== null;
    }
}
