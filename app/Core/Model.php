<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use LogicException;

/**
 * Model base.
 *
 * - $table      : nome da tabela
 * - $fillable   : colunas que podem ser escritas em create/update (OBRIGATÓRIO)
 * - $tenant     : se a tabela tem `igreja_id` (multi-tenant)
 * - $softDelete : se a tabela tem `deletado_em`
 *
 * Segurança:
 *  - Em tabelas multi-tenant, se não houver igreja activa (Tenant::id() === null)
 *    o Model LANÇA excepção em vez de consultar todas as igrejas.
 *    O superadmin que precise de trabalhar sobre uma igreja chama Tenant::set($id).
 *  - `igreja_id` nunca é aceite nos dados: vem sempre do Tenant activo.
 */
abstract class Model
{
    protected string $table = '';
    protected array $fillable = [];
    protected bool $tenant = true;
    protected bool $softDelete = false;
    protected string $primaryKey = 'id';

    /** Retorna a tabela do model */
    public function table(): string
    {
        return $this->table;
    }

    /** Igreja activa; falha se o model é multi-tenant e não há igreja. */
    protected function requireTenantId(): ?int
    {
        if (!$this->tenant) {
            return null;
        }
        $igrejaId = Tenant::id();
        if ($igrejaId === null) {
            throw new LogicException(
                "Sem igreja activa ao aceder a `{$this->table}`. Defina o tenant antes (Tenant::set())."
            );
        }
        return $igrejaId;
    }

    /** Monta o filtro de tenant (sempre aplicado em tabelas multi-tenant) */
    protected function tenantClause(array &$params): string
    {
        $igrejaId = $this->requireTenantId();
        if ($igrejaId === null) {
            return '';
        }
        $params['__tenant'] = $igrejaId;
        return ' AND `igreja_id` = :__tenant';
    }

    /** WHERE de soft delete (se aplicável) */
    protected function softDeleteClause(): string
    {
        return $this->softDelete ? ' AND `deletado_em` IS NULL' : '';
    }

    public function find(int $id): ?array
    {
        $params = ['id' => $id];
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id"
             . $this->tenantClause($params)
             . $this->softDeleteClause()
             . ' LIMIT 1';
        return Database::first($sql, $params);
    }

    public function all(string $orderBy = 'id DESC', int $limit = 500): array
    {
        $params = [];
        $sql = "SELECT * FROM `{$this->table}` WHERE 1=1"
             . $this->tenantClause($params)
             . $this->softDeleteClause()
             . ' ORDER BY ' . $this->safeOrderBy($orderBy)
             . ' LIMIT ' . max(1, min($limit, 5000));
        return Database::select($sql, $params);
    }

    public function create(array $data): int
    {
        $data = $this->filterFillable($data);

        if ($this->tenant) {
            $data['igreja_id'] = $this->requireTenantId();
        }

        if (empty($data)) {
            throw new InvalidArgumentException('Nenhum campo válido para inserir.');
        }

        $columns      = array_keys($data);
        $placeholders = array_map(static fn ($c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            '`' . implode('`,`', $columns) . '`',
            implode(',', $placeholders)
        );

        return Database::insert($sql, $data);
    }

    public function update(int $id, array $data): int
    {
        $data = $this->filterFillable($data);
        unset($data['igreja_id']); // nunca se muda de igreja

        if (empty($data)) {
            return 0;
        }

        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = "`{$col}` = :{$col}";
        }

        $params       = $data;
        $params['id'] = $id;

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $sets)
             . " WHERE `{$this->primaryKey}` = :id"
             . $this->tenantClause($params)
             . $this->softDeleteClause();

        return Database::execute($sql, $params);
    }

    public function delete(int $id): int
    {
        if ($this->softDelete) {
            
            $params = ['id' => $id, 'agora' => date('Y-m-d H:i:s')];
            $sql = "UPDATE `{$this->table}` SET `deletado_em` = :agora WHERE `{$this->primaryKey}` = :id"
                 . $this->tenantClause($params)
                 . $this->softDeleteClause();
            return Database::execute($sql, $params);
        }

        $params = ['id' => $id];
        $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id"
             . $this->tenantClause($params);
        return Database::execute($sql, $params);
    }

    /**
     * Só aceita colunas declaradas em $fillable e com nome seguro.
     * Sem $fillable definido => erro (nada de "aceitar tudo" por omissão).
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            throw new LogicException("Defina \$fillable no model da tabela `{$this->table}`.");
        }

        $data = array_intersect_key($data, array_flip($this->fillable));

        foreach (array_keys($data) as $col) {
            if (!is_string($col) || !preg_match('/^[a-z_][a-z0-9_]*$/i', $col)) {
                throw new InvalidArgumentException('Nome de coluna inválido.');
            }
        }
        return $data;
    }

    /** Aceita apenas "coluna [ASC|DESC][, coluna ...]" para evitar SQL injection. */
    protected function safeOrderBy(string $orderBy): string
    {
        $re = '/^[a-z_][a-z0-9_.]*(\s+(asc|desc))?(\s*,\s*[a-z_][a-z0-9_.]*(\s+(asc|desc))?)*$/i';
        if (!preg_match($re, trim($orderBy))) {
            throw new InvalidArgumentException('ORDER BY inválido.');
        }
        return trim($orderBy);
    }
}
