-- Migração 001 — catálogo de planos da plataforma.
-- Aplicar antes de 003_criar_igrejas_e_configuracoes.sql.
-- Idempotente: CREATE TABLE IF NOT EXISTS permite repetir sem apagar dados.
-- Reversão manual: DROP TABLE `planos`; só depois de remover as tabelas que
-- tenham uma FK para planos e de confirmar que não há dados a preservar.

CREATE TABLE IF NOT EXISTS `planos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome` VARCHAR(100) NOT NULL,
    `limite_membros` INT UNSIGNED NOT NULL DEFAULT 0,
    `limite_utilizadores` INT UNSIGNED NOT NULL DEFAULT 0,
    `preco_kz` DECIMAL(13,2) NOT NULL DEFAULT 0.00,
    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_planos_nome` (`nome`),
    KEY `idx_planos_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
