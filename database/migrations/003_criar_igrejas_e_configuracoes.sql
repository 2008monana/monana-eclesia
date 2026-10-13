-- Migração 003 — igrejas e configuração inicial por inquilino.
-- Pré-condição: 001_criar_planos.sql aplicada e, se existir a BD antiga,
-- validar primeiro o resultado da conversão 002.
-- Idempotente para instalação: cria tabelas ausentes sem alterar dados existentes.
-- Reversão manual (apenas se as tabelas estiverem vazias e sem dependentes):
-- DROP TABLE `configuracoes_igreja`;
-- DROP TABLE `igrejas`;

CREATE TABLE IF NOT EXISTS `igrejas` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plano_id` INT UNSIGNED NOT NULL,
    `nome` VARCHAR(180) NOT NULL,
    `slug` VARCHAR(180) NOT NULL,
    `email_admin` VARCHAR(191) NOT NULL,
    `telefone` VARCHAR(40) NULL,
    `cidade` VARCHAR(120) NULL,
    `estado` ENUM('pendente','activa','suspensa') NOT NULL DEFAULT 'pendente',
    `criada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `suspensa_em` DATETIME NULL,
    `encerrada_em` DATETIME NULL,
    `motivo_suspensao` VARCHAR(500) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_igrejas_slug` (`slug`),
    KEY `idx_igrejas_plano` (`plano_id`),
    KEY `idx_igrejas_estado` (`estado`),
    CONSTRAINT `fk_igrejas_plano`
        FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `configuracoes_igreja` (
    `igreja_id` INT UNSIGNED NOT NULL,
    `nome_exibicao` VARCHAR(180) NOT NULL,
    `subtitulo` VARCHAR(255) NULL,
    `rodape` VARCHAR(500) NULL,
    `versiculo` VARCHAR(255) NULL,
    `logo_path` VARCHAR(255) NULL,
    `valor_cota` DECIMAL(13,2) NOT NULL DEFAULT 0.00,
    `periodicidade_cota` ENUM('mensal','anual') NOT NULL DEFAULT 'mensal',
    `inicio_cotas` DATE NULL,
    `inicio_contribuicoes` DATE NULL,
    `moeda` CHAR(3) NOT NULL DEFAULT 'AOA',
    `dias_expiracao_sessao` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`igreja_id`),
    CONSTRAINT `fk_configuracoes_igreja_igreja`
        FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
