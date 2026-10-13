-- Migração 004 — autenticação, provisionamento e plataforma Superadmin.
-- Requer 001_criar_planos.sql e 003_criar_igrejas_e_configuracoes.sql.
-- Fazer uma cópia da base de dados antes de aplicar. A migração é repetível:
-- cria tabelas ausentes e acrescenta colunas/índices apenas quando faltam.
--
-- Para uma tabela `usuarios` legada, esta migração assume as colunas-base
-- descritas em docs/fase-01-analise-e-mer.md (`id`, `nome_completo`, `email`,
-- `senha_hash`, `perfil`, `ativo`). Verificar duplicados de email antes de
-- aplicar o índice único (consulta no README das migrações).
--
-- Reversão manual (somente após backup e confirmação de que não há dados a
-- preservar):
--   DROP TABLE `platform_bootstrap`;
--   DROP TABLE `email_fila`;
--   DROP TABLE `sessoes`;
--   DROP TABLE `tentativas_login`;
--   DROP TABLE `tokens_ativacao`;
--   DROP TABLE `tokens_senha`;
--   DROP TABLE `permissoes_modulo`;
--   -- remover `igreja_id` de logs_auditoria apenas se não existirem dependentes;
--   -- as novas colunas em usuarios não devem ser removidas se já houver logins.
-- A reversão não apaga nem altera as igrejas ou planos existentes.
-- A Fase 3 encerra igrejas por estado (soft-delete), sem apagar registos.

SET @monana_has_encerrada_status = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'igrejas' AND COLUMN_NAME = 'estado'
      AND COLUMN_TYPE LIKE '%encerrada%'
);
SET @monana_ddl = IF(
    @monana_has_encerrada_status = 0,
    'ALTER TABLE `igrejas` MODIFY COLUMN `estado` ENUM(''pendente'',''activa'',''suspensa'',''encerrada'') NOT NULL DEFAULT ''pendente''',
    'SELECT 1'
);
PREPARE monana_stmt FROM @monana_ddl; EXECUTE monana_stmt; DEALLOCATE PREPARE monana_stmt;

SET @monana_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'igrejas' AND COLUMN_NAME = 'encerrada_em') = 0,
    'ALTER TABLE `igrejas` ADD COLUMN `encerrada_em` DATETIME NULL DEFAULT NULL AFTER `suspensa_em`',
    'SELECT 1'
);
PREPARE monana_stmt FROM @monana_ddl; EXECUTE monana_stmt; DEALLOCATE PREPARE monana_stmt;

CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `igreja_id` INT UNSIGNED NULL DEFAULT NULL,
    `nome_completo` VARCHAR(180) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `senha_hash` VARCHAR(255) NOT NULL,
    `perfil` VARCHAR(32) NULL DEFAULT NULL,
    `papel` VARCHAR(32) NOT NULL DEFAULT 'admin_igreja',
    `modulos_permitidos` TEXT NULL,
    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `deve_trocar_senha` TINYINT(1) NOT NULL DEFAULT 0,
    `email_verificado_em` DATETIME NULL DEFAULT NULL,
    `versao_sessao` INT UNSIGNED NOT NULL DEFAULT 1,
    `ultimo_login` DATETIME NULL DEFAULT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_usuarios_email` (`email`),
    KEY `idx_usuarios_igreja_papel` (`igreja_id`, `papel`),
    KEY `idx_usuarios_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Acrescentar as colunas novas sem depender de `ADD COLUMN IF NOT EXISTS`
-- (não disponível em todas as versões MySQL usadas em alojamento partilhado).
SET @monana_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'igreja_id') = 0,
    'ALTER TABLE `usuarios` ADD COLUMN `igreja_id` INT UNSIGNED NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE monana_stmt FROM @monana_ddl; EXECUTE monana_stmt; DEALLOCATE PREPARE monana_stmt;

SET @monana_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'papel') = 0,
    'ALTER TABLE `usuarios` ADD COLUMN `papel` VARCHAR(32) NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE monana_stmt FROM @monana_ddl; EXECUTE monana_stmt; DEALLOCATE PREPARE monana_stmt;

SET @monana_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'deve_trocar_senha') = 0,
    'ALTER TABLE `usuarios` ADD COLUMN `deve_trocar_senha` TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE monana_stmt FROM @monana_ddl; EXECUTE monana_stmt; DEALLOCATE PREPARE monana_stmt;

SET @monana_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'email_verificado_em') = 0,
    'ALTER TABLE `usuarios` ADD COLUMN `email_verificado_em` DATETIME NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE monana_stmt FROM @monana_ddl; EXECUTE monana_stmt; DEALLOCATE PREPARE monana_stmt;

SET @monana_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'versao_sessao') = 0,
    'ALTER TABLE `usuarios` ADD COLUMN `versao_sessao` INT UNSIGNED NOT NULL DEFAULT 1',
    'SELECT 1'
);
PREPARE monana_stmt FROM @monana_ddl; EXECUTE monana_stmt; DEALLOCATE PREPARE monana_stmt;

-- Compatibilidade da tabela antiga e preenchimento dos papéis iniciais.
-- `admin` -> `admin_igreja`; `gestor` -> `tesoureiro` conforme D-05.
UPDATE `usuarios`
SET `papel` = CASE
    WHEN `perfil` = 'gestor' THEN 'tesoureiro'
    WHEN `perfil` = 'admin' THEN 'admin_igreja'
    ELSE 'admin_igreja'
END
WHERE `papel` IS NULL OR `papel` = '';

-- Unicidade de email global, sem duplicar um índice único já existente.
SET @monana_has_unique_email = (
    SELECT COUNT(*) FROM information_schema.STATISTICS s
    WHERE s.TABLE_SCHEMA = DATABASE() AND s.TABLE_NAME = 'usuarios'
      AND s.COLUMN_NAME = 'email' AND s.NON_UNIQUE = 0
      AND (SELECT COUNT(*) FROM information_schema.STATISTICS x
           WHERE x.TABLE_SCHEMA = s.TABLE_SCHEMA AND x.TABLE_NAME = s.TABLE_NAME
             AND x.INDEX_NAME = s.INDEX_NAME) = 1
);
SET @monana_ddl = IF(
    @monana_has_unique_email = 0,
    'ALTER TABLE `usuarios` ADD UNIQUE KEY `uq_usuarios_email` (`email`)',
    'SELECT 1'
);
PREPARE monana_stmt FROM @monana_ddl; EXECUTE monana_stmt; DEALLOCATE PREPARE monana_stmt;

SET @monana_has_tenant_idx = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios'
      AND INDEX_NAME = 'idx_usuarios_igreja_papel'
);
SET @monana_ddl = IF(
    @monana_has_tenant_idx = 0,
    'ALTER TABLE `usuarios` ADD KEY `idx_usuarios_igreja_papel` (`igreja_id`, `papel`)',
    'SELECT 1'
);
PREPARE monana_stmt FROM @monana_ddl; EXECUTE monana_stmt; DEALLOCATE PREPARE monana_stmt;

CREATE TABLE IF NOT EXISTS `permissoes_modulo` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` INT UNSIGNED NOT NULL,
    `igreja_id` INT UNSIGNED NOT NULL,
    `modulo` VARCHAR(80) NOT NULL,
    `permitido` TINYINT(1) NOT NULL DEFAULT 1,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permissoes_usuario_modulo` (`usuario_id`, `modulo`),
    KEY `idx_permissoes_igreja_modulo` (`igreja_id`, `modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tokens_senha` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `expira_em` DATETIME NOT NULL,
    `usado_em` DATETIME NULL DEFAULT NULL,
    `ip` VARCHAR(45) NULL DEFAULT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tokens_senha_hash` (`token_hash`),
    KEY `idx_tokens_senha_usuario` (`usuario_id`, `expira_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tokens_ativacao` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `expira_em` DATETIME NOT NULL,
    `usado_em` DATETIME NULL DEFAULT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tokens_ativacao_hash` (`token_hash`),
    KEY `idx_tokens_ativacao_usuario` (`usuario_id`, `expira_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessoes` (
    `id` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `usuario_id` INT UNSIGNED NOT NULL,
    `igreja_id` INT UNSIGNED NULL DEFAULT NULL,
    `versao_sessao` INT UNSIGNED NOT NULL,
    `ip` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` VARCHAR(500) NULL DEFAULT NULL,
    `ultima_actividade` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expira_em` DATETIME NOT NULL,
    `revogada_em` DATETIME NULL DEFAULT NULL,
    `criada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sessoes_usuario_activas` (`usuario_id`, `revogada_em`, `expira_em`),
    KEY `idx_sessoes_igreja` (`igreja_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tentativas_login` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(191) NOT NULL DEFAULT '',
    `ip` VARCHAR(45) NOT NULL DEFAULT '',
    `sucesso` TINYINT(1) NOT NULL DEFAULT 0,
    `tentada_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tentativas_email_data` (`email`, `tentada_em`),
    KEY `idx_tentativas_ip_data` (`ip`, `tentada_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_fila` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `igreja_id` INT UNSIGNED NULL DEFAULT NULL,
    `usuario_id` INT UNSIGNED NULL DEFAULT NULL,
    `destinatario` VARCHAR(191) NOT NULL,
    `assunto` VARCHAR(255) NOT NULL,
    `corpo_html` LONGTEXT NOT NULL,
    `corpo_texto` LONGTEXT NULL,
    `template` VARCHAR(80) NOT NULL DEFAULT 'generico',
    `dados_cifrados` TEXT NULL,
    `estado` ENUM('pendente','enviado','falhou') NOT NULL DEFAULT 'pendente',
    `tentativas` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `erro` TEXT NULL,
    `agendado_para` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `enviado_em` DATETIME NULL DEFAULT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email_fila_estado_agendamento` (`estado`, `agendado_para`),
    KEY `idx_email_fila_igreja` (`igreja_id`, `criado_em`),
    KEY `idx_email_fila_usuario` (`usuario_id`, `criado_em`),
    KEY `idx_email_fila_destinatario` (`destinatario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A fila existente de auditoria recebe igreja_id, mantendo os registos antigos
-- globais com NULL; nenhum dado histórico é inferido nem movido nesta fase.
CREATE TABLE IF NOT EXISTS `logs_auditoria` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `igreja_id` INT UNSIGNED NULL DEFAULT NULL,
    `usuario_id` INT NULL DEFAULT NULL,
    `usuario_nome` VARCHAR(180) NOT NULL DEFAULT 'Sistema',
    `acao` VARCHAR(80) NOT NULL,
    `modulo` VARCHAR(80) NOT NULL,
    `descricao` TEXT NULL,
    `dados_anteriores` LONGTEXT NULL,
    `dados_novos` LONGTEXT NULL,
    `ip` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` VARCHAR(500) NULL DEFAULT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logs_igreja_data` (`igreja_id`, `criado_em`),
    KEY `idx_logs_usuario_data` (`usuario_id`, `criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @monana_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'logs_auditoria' AND COLUMN_NAME = 'igreja_id') = 0,
    'ALTER TABLE `logs_auditoria` ADD COLUMN `igreja_id` INT UNSIGNED NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE monana_stmt FROM @monana_ddl; EXECUTE monana_stmt; DEALLOCATE PREPARE monana_stmt;

CREATE TABLE IF NOT EXISTS `platform_bootstrap` (
    `id` TINYINT UNSIGNED NOT NULL,
    `superadmin_criado_em` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `platform_bootstrap` (`id`, `superadmin_criado_em`)
VALUES (1, NULL)
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- Não inventar preço, limites ou nome comercial. O único registo, quando a
-- tabela está vazia, é um rascunho inactivo que o Superadmin deve configurar.
INSERT INTO `planos` (`nome`, `limite_membros`, `limite_utilizadores`, `preco_kz`, `ativo`)
SELECT 'Plano por configurar', 0, 0, 0.00, 0
WHERE NOT EXISTS (SELECT 1 FROM `planos`);
