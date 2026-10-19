-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 18-Out-2026 às 17:24
-- Versão do servidor: 10.4.32-MariaDB
-- versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `monana_eclesia`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `supervisor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `igreja_id` bigint(20) UNSIGNED DEFAULT NULL,
  `modulo` varchar(60) NOT NULL,
  `acao` varchar(60) NOT NULL,
  `entidade` varchar(60) DEFAULT NULL,
  `entidade_id` varchar(60) DEFAULT NULL,
  `dados_antes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_antes`)),
  `dados_depois` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados_depois`)),
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Acionadores `audit_logs`
--
DELIMITER $$
CREATE TRIGGER `trg_audit_no_delete` BEFORE DELETE ON `audit_logs` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs é imutável (append-only).';
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_audit_no_update` BEFORE UPDATE ON `audit_logs` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs é imutável (append-only).';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `backups`
--

CREATE TABLE `backups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tipo` enum('automatico','manual') NOT NULL DEFAULT 'automatico',
  `ficheiro` varchar(255) NOT NULL,
  `tamanho_bytes` bigint(20) UNSIGNED DEFAULT NULL,
  `hash_sha256` char(64) DEFAULT NULL,
  `estado` enum('pendente','em_curso','concluido','falhou') NOT NULL DEFAULT 'pendente',
  `mensagem` text DEFAULT NULL,
  `iniciado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `concluido_em` timestamp NULL DEFAULT NULL,
  `expira_em` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `tipo` enum('entrada','despesa') NOT NULL,
  `cor` varchar(20) DEFAULT NULL,
  `icone` varchar(40) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `cells`
--

CREATE TABLE `cells` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(150) NOT NULL,
  `lider_id` bigint(20) UNSIGNED DEFAULT NULL,
  `anfitriao_id` bigint(20) UNSIGNED DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `dia_semana` enum('seg','ter','qua','qui','sex','sab','dom') DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `cell_meetings`
--

CREATE TABLE `cell_meetings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cell_id` bigint(20) UNSIGNED NOT NULL,
  `data` date NOT NULL,
  `tema` varchar(180) DEFAULT NULL,
  `presentes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `cell_members`
--

CREATE TABLE `cell_members` (
  `cell_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `desde` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `departments`
--

CREATE TABLE `departments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `cor` varchar(20) DEFAULT NULL,
  `lider_id` bigint(20) UNSIGNED DEFAULT NULL,
  `vice_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `department_members`
--

CREATE TABLE `department_members` (
  `department_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `funcao` varchar(80) DEFAULT NULL,
  `desde` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `ebd_classes`
--

CREATE TABLE `ebd_classes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(120) NOT NULL,
  `faixa_etaria` varchar(60) DEFAULT NULL,
  `sala` varchar(60) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `ebd_students`
--

CREATE TABLE `ebd_students` (
  `class_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `desde` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `ebd_teachers`
--

CREATE TABLE `ebd_teachers` (
  `class_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `desde` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `events`
--

CREATE TABLE `events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `descricao` text DEFAULT NULL,
  `tipo` varchar(60) DEFAULT NULL,
  `data` date NOT NULL,
  `hora` time DEFAULT NULL,
  `local` varchar(180) DEFAULT NULL,
  `responsavel_id` bigint(20) UNSIGNED DEFAULT NULL,
  `criado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `event_attendances`
--

CREATE TABLE `event_attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `presente` tinyint(1) NOT NULL DEFAULT 1,
  `registado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `igrejas`
--

CREATE TABLE `igrejas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(180) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `nif` varchar(30) DEFAULT NULL,
  `email` varchar(180) NOT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `cidade` varchar(80) DEFAULT NULL,
  `provincia` varchar(80) DEFAULT NULL,
  `pais` varchar(80) NOT NULL DEFAULT 'Angola',
  `logo` varchar(255) DEFAULT NULL,
  `fuso_horario` varchar(60) NOT NULL DEFAULT 'Africa/Luanda',
  `moeda` char(3) NOT NULL DEFAULT 'AOA',
  `plano_id` int(10) UNSIGNED DEFAULT NULL,
  `igreja_pai_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('em_teste','activa','suspensa','cancelada') NOT NULL DEFAULT 'em_teste',
  `criado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deletado_em` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `members`
--

CREATE TABLE `members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nome` varchar(180) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `email` varchar(180) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `genero` enum('M','F','outro') DEFAULT NULL,
  `estado_civil` enum('solteiro','casado','divorciado','viuvo','uniao_facto') DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `cidade` varchar(80) DEFAULT NULL,
  `provincia` varchar(80) DEFAULT NULL,
  `cargo` varchar(80) DEFAULT NULL,
  `status` enum('activo','inactivo','transferido','falecido') NOT NULL DEFAULT 'activo',
  `data_conversao` date DEFAULT NULL,
  `data_baptismo` date DEFAULT NULL,
  `membro_desde` date DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deletado_em` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `member_families`
--

CREATE TABLE `member_families` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `parente_id` bigint(20) UNSIGNED NOT NULL,
  `relacao` enum('conjuge','filho','pai','mae','irmao','outro') NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `monthly_closures`
--

CREATE TABLE `monthly_closures` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED NOT NULL,
  `ano` smallint(5) UNSIGNED NOT NULL,
  `mes` tinyint(3) UNSIGNED NOT NULL,
  `total_entradas` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_despesas` decimal(14,2) NOT NULL DEFAULT 0.00,
  `saldo` decimal(14,2) NOT NULL DEFAULT 0.00,
  `fechado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `fechado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `subscription_id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED NOT NULL,
  `valor_kz` decimal(12,2) NOT NULL,
  `metodo` enum('transferencia','multicaixa','deposito','dinheiro','outro') DEFAULT NULL,
  `referencia` varchar(80) DEFAULT NULL,
  `comprovativo` varchar(255) DEFAULT NULL,
  `estado` enum('pendente','confirmado','rejeitado','reembolsado') NOT NULL DEFAULT 'pendente',
  `pago_em` timestamp NULL DEFAULT NULL,
  `confirmado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `permissions`
--

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `modulo` varchar(60) NOT NULL,
  `acao` varchar(60) NOT NULL,
  `descricao` varchar(180) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `permissions`
--

INSERT INTO `permissions` (`id`, `modulo`, `acao`, `descricao`, `criado_em`) VALUES
(1, 'membros', 'ver', 'Ver lista de membros', '2026-10-17 07:28:56'),
(2, 'membros', 'criar', 'Criar membros', '2026-10-17 07:28:56'),
(3, 'membros', 'editar', 'Editar membros', '2026-10-17 07:28:56'),
(4, 'membros', 'eliminar', 'Eliminar membros', '2026-10-17 07:28:56'),
(5, 'membros', 'exportar', 'Exportar membros', '2026-10-17 07:28:56'),
(6, 'departamentos', 'ver', 'Ver departamentos', '2026-10-17 07:28:56'),
(7, 'departamentos', 'criar', 'Criar departamentos', '2026-10-17 07:28:56'),
(8, 'departamentos', 'editar', 'Editar departamentos', '2026-10-17 07:28:56'),
(9, 'departamentos', 'eliminar', 'Eliminar departamentos', '2026-10-17 07:28:56'),
(10, 'celulas', 'ver', 'Ver células', '2026-10-17 07:28:56'),
(11, 'celulas', 'criar', 'Criar células', '2026-10-17 07:28:56'),
(12, 'celulas', 'editar', 'Editar células', '2026-10-17 07:28:56'),
(13, 'celulas', 'eliminar', 'Eliminar células', '2026-10-17 07:28:56'),
(14, 'eventos', 'ver', 'Ver eventos', '2026-10-17 07:28:56'),
(15, 'eventos', 'criar', 'Criar eventos', '2026-10-17 07:28:56'),
(16, 'eventos', 'editar', 'Editar eventos', '2026-10-17 07:28:56'),
(17, 'eventos', 'eliminar', 'Eliminar eventos', '2026-10-17 07:28:56'),
(18, 'eventos', 'registar_presenca', 'Registar presença', '2026-10-17 07:28:56'),
(19, 'ebd', 'ver', 'Ver EBD', '2026-10-17 07:28:56'),
(20, 'ebd', 'criar', 'Criar classes EBD', '2026-10-17 07:28:56'),
(21, 'ebd', 'editar', 'Editar EBD', '2026-10-17 07:28:56'),
(22, 'ebd', 'eliminar', 'Eliminar EBD', '2026-10-17 07:28:56'),
(23, 'financeiro', 'ver', 'Ver financeiro', '2026-10-17 07:28:56'),
(24, 'financeiro', 'criar', 'Criar lançamentos', '2026-10-17 07:28:56'),
(25, 'financeiro', 'editar', 'Editar lançamentos', '2026-10-17 07:28:56'),
(26, 'financeiro', 'eliminar', 'Eliminar lançamentos', '2026-10-17 07:28:56'),
(27, 'financeiro', 'ver_relatorios', 'Ver relatórios financeiros', '2026-10-17 07:28:56'),
(28, 'financeiro', 'fechar_mes', 'Fechar o mês', '2026-10-17 07:28:56'),
(29, 'relatorios', 'ver', 'Ver relatórios', '2026-10-17 07:28:56'),
(30, 'relatorios', 'exportar', 'Exportar relatórios', '2026-10-17 07:28:56'),
(31, 'utilizadores', 'ver', 'Ver utilizadores', '2026-10-17 07:28:56'),
(32, 'utilizadores', 'criar', 'Criar utilizadores', '2026-10-17 07:28:56'),
(33, 'utilizadores', 'editar', 'Editar utilizadores', '2026-10-17 07:28:56'),
(34, 'utilizadores', 'eliminar', 'Eliminar utilizadores', '2026-10-17 07:28:56'),
(35, 'utilizadores', 'atribuir_permissoes', 'Atribuir permissões', '2026-10-17 07:28:56'),
(36, 'configuracoes', 'ver', 'Ver configurações', '2026-10-17 07:28:56'),
(37, 'configuracoes', 'editar', 'Editar configurações', '2026-10-17 07:28:56'),
(38, 'assinaturas', 'ver', 'Ver assinatura da igreja', '2026-10-17 07:28:56'),
(39, 'assinaturas', 'solicitar_alteracao', 'Solicitar alteração de plano', '2026-10-17 07:28:56'),
(40, 'backup', 'ver', 'Ver backups', '2026-10-17 07:28:56'),
(41, 'backup', 'executar', 'Executar backup', '2026-10-17 07:28:56'),
(42, 'backup', 'restaurar', 'Restaurar backup', '2026-10-17 07:28:56'),
(43, 'auditoria', 'ver', 'Ver auditoria', '2026-10-17 07:28:56'),
(44, 'auditoria', 'exportar', 'Exportar auditoria', '2026-10-17 07:28:56');

-- --------------------------------------------------------

--
-- Estrutura da tabela `planos`
--

CREATE TABLE `planos` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `preco_kz` decimal(12,2) NOT NULL DEFAULT 0.00,
  `periodicidade_dias` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `limite_membros` int(10) UNSIGNED DEFAULT NULL,
  `limite_users` int(10) UNSIGNED DEFAULT NULL,
  `limite_storage_mb` int(10) UNSIGNED DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ordem` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `planos`
--

INSERT INTO `planos` (`id`, `nome`, `slug`, `descricao`, `preco_kz`, `periodicidade_dias`, `limite_membros`, `limite_users`, `limite_storage_mb`, `ativo`, `ordem`, `criado_em`, `actualizado_em`) VALUES
(1, 'Gratuito', 'gratuito', 'Plano de entrada com limites básicos', 0.00, 0, 50, 3, NULL, 1, 1, '2026-10-17 07:28:54', '2026-10-17 07:28:54'),
(2, 'Mensal', 'mensal', 'Pagamento mensal', 20000.00, 30, 300, 10, NULL, 1, 2, '2026-10-17 07:28:54', '2026-10-17 07:28:54'),
(3, 'Trimestral', 'trimestral', 'Pagamento a cada 3 meses (poupança de ~16%)', 50000.00, 90, 500, 20, NULL, 1, 3, '2026-10-17 07:28:54', '2026-10-17 07:28:54'),
(4, 'Semestral', 'semestral', 'Pagamento a cada 6 meses (poupança de ~33%)', 100000.00, 180, 1000, 40, NULL, 1, 4, '2026-10-17 07:28:54', '2026-10-17 07:28:54'),
(5, 'Anual', 'anual', 'Melhor custo-benefício (12 meses)', 200000.00, 365, NULL, NULL, NULL, 1, 5, '2026-10-17 07:28:54', '2026-10-17 07:28:54');

-- --------------------------------------------------------

--
-- Estrutura da tabela `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED DEFAULT NULL,
  `nome` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `sistema` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `roles`
--

INSERT INTO `roles` (`id`, `igreja_id`, `nome`, `slug`, `descricao`, `sistema`, `criado_em`, `actualizado_em`) VALUES
(1, NULL, 'Administrador da Igreja', 'admin_igreja', 'Acesso total à sua igreja', 1, '2026-10-17 07:28:56', '2026-10-17 07:28:56'),
(2, NULL, 'Pastor', 'pastor', 'Acesso pastoral (leitura + alguns módulos)', 1, '2026-10-17 07:28:56', '2026-10-17 07:28:56'),
(3, NULL, 'Líder', 'lider', 'Acesso aos módulos dos seus departamentos', 1, '2026-10-17 07:28:56', '2026-10-17 07:28:56'),
(4, NULL, 'Financeiro', 'financeiro', 'Acesso ao módulo financeiro', 1, '2026-10-17 07:28:56', '2026-10-17 07:28:56'),
(5, NULL, 'Secretaria', 'secretaria', 'Acesso a membros e eventos', 1, '2026-10-17 07:28:56', '2026-10-17 07:28:56'),
(6, NULL, 'Membro', 'membro', 'Acesso restrito ao próprio perfil', 1, '2026-10-17 07:28:56', '2026-10-17 07:28:56');

-- --------------------------------------------------------

--
-- Estrutura da tabela `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(1, 22),
(1, 23),
(1, 24),
(1, 25),
(1, 26),
(1, 27),
(1, 28),
(1, 29),
(1, 30),
(1, 31),
(1, 32),
(1, 33),
(1, 34),
(1, 35),
(1, 36),
(1, 37),
(1, 38),
(1, 39),
(1, 40),
(1, 41),
(1, 42),
(1, 43),
(1, 44),
(6, 14);

-- --------------------------------------------------------

--
-- Estrutura da tabela `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED DEFAULT NULL,
  `chave` varchar(80) NOT NULL,
  `valor` text DEFAULT NULL,
  `tipo` enum('texto','numero','booleano','json','ficheiro') NOT NULL DEFAULT 'texto',
  `actualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `settings`
--

INSERT INTO `settings` (`id`, `igreja_id`, `chave`, `valor`, `tipo`, `actualizado_em`) VALUES
(1, NULL, 'app_nome', 'Monana Eclesia', 'texto', '2026-10-17 07:28:57'),
(2, NULL, 'app_moeda', 'AOA', 'texto', '2026-10-17 07:28:57'),
(3, NULL, 'app_idioma', 'pt-AO', 'texto', '2026-10-17 07:28:57'),
(4, NULL, 'app_fuso', 'Africa/Luanda', 'texto', '2026-10-17 07:28:57'),
(5, NULL, 'assinatura_tolerancia_horas', '24', 'numero', '2026-10-17 07:28:57'),
(6, NULL, 'assinatura_aviso_horas', '48', 'numero', '2026-10-17 07:28:57'),
(7, NULL, 'backup_retencao_dias', '30', 'numero', '2026-10-17 07:28:57');

-- --------------------------------------------------------

--
-- Estrutura da tabela `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED NOT NULL,
  `plano_id` int(10) UNSIGNED NOT NULL,
  `inicio` date NOT NULL,
  `expiracao` date DEFAULT NULL,
  `status` enum('trial','activa','expirando','expirada','em_tolerancia','suspensa','cancelada') NOT NULL DEFAULT 'trial',
  `renovacao_automatica` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `subscription_notifications`
--

CREATE TABLE `subscription_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED NOT NULL,
  `subscription_id` bigint(20) UNSIGNED NOT NULL,
  `tipo` enum('48h_antes','expirada','tolerancia','suspensa','reativada') NOT NULL,
  `canal` enum('email','sms','sistema') NOT NULL DEFAULT 'email',
  `enviado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('enviado','falhou','lido') NOT NULL DEFAULT 'enviado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `supervisores`
--

CREATE TABLE `supervisores` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(150) NOT NULL,
  `email` varchar(180) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `nivel` enum('superadmin','supervisor') NOT NULL DEFAULT 'supervisor',
  `telefone` varchar(30) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `tentativas_login` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_ate` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `supervisores`
--

INSERT INTO `supervisores` (`id`, `nome`, `email`, `senha_hash`, `nivel`, `telefone`, `foto`, `ativo`, `must_change_password`, `ultimo_login`, `tentativas_login`, `bloqueado_ate`, `criado_em`, `actualizado_em`) VALUES
(1, 'Super Administrador', 'superadmin@monana.app', '$argon2id$v=19$m=65536,t=4,p=1$ZXAyM3o2VTdUUDlkMWMvRQ$TgMJfrtY/nGxgM5HMS0zr3NC2a1oEC08MjTti+R31uk', 'superadmin', NULL, NULL, 1, 1, NULL, 0, NULL, '2026-10-17 07:28:57', '2026-10-17 12:41:58');

-- --------------------------------------------------------

--
-- Estrutura da tabela `supervisor_igrejas`
--

CREATE TABLE `supervisor_igrejas` (
  `supervisor_id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED NOT NULL,
  `atribuido_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED NOT NULL,
  `tipo` enum('entrada','despesa') NOT NULL,
  `categoria_id` bigint(20) UNSIGNED DEFAULT NULL,
  `member_id` bigint(20) UNSIGNED DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor_kz` decimal(14,2) NOT NULL,
  `data` date NOT NULL,
  `metodo` enum('dinheiro','transferencia','multicaixa','deposito','outro') DEFAULT NULL,
  `referencia` varchar(80) DEFAULT NULL,
  `criado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `igreja_id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(150) NOT NULL,
  `email` varchar(180) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_login` timestamp NULL DEFAULT NULL,
  `tentativas_login` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_ate` timestamp NULL DEFAULT NULL,
  `token_reset` varchar(120) DEFAULT NULL,
  `token_reset_expira` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deletado_em` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('grant','deny') NOT NULL,
  `atribuido_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_profiles`
--

CREATE TABLE `user_profiles` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `data_nascimento` date DEFAULT NULL,
  `genero` enum('M','F','outro') DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `cidade` varchar(80) DEFAULT NULL,
  `provincia` varchar(80) DEFAULT NULL,
  `pais` varchar(80) NOT NULL DEFAULT 'Angola',
  `idioma` varchar(10) NOT NULL DEFAULT 'pt-AO',
  `fuso_horario` varchar(60) NOT NULL DEFAULT 'Africa/Luanda',
  `notif_email` tinyint(1) NOT NULL DEFAULT 1,
  `notif_sms` tinyint(1) NOT NULL DEFAULT 0,
  `mfa_ativo` tinyint(1) NOT NULL DEFAULT 0,
  `mfa_secret` varchar(120) DEFAULT NULL,
  `actualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `atribuido_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token` varchar(120) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `expira_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_al_sup` (`supervisor_id`),
  ADD KEY `idx_al_user` (`user_id`),
  ADD KEY `idx_al_igreja` (`igreja_id`),
  ADD KEY `idx_al_modulo_acao` (`modulo`,`acao`),
  ADD KEY `idx_al_data` (`criado_em`);

--
-- Índices para tabela `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bkp_igreja` (`igreja_id`),
  ADD KEY `idx_bkp_estado` (`estado`),
  ADD KEY `idx_bkp_data` (`iniciado_em`);

--
-- Índices para tabela `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cat_igreja_nome_tipo` (`igreja_id`,`nome`,`tipo`),
  ADD KEY `idx_cat_tipo` (`tipo`);

--
-- Índices para tabela `cells`
--
ALTER TABLE `cells`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cells_lider` (`lider_id`),
  ADD KEY `fk_cells_anfitriao` (`anfitriao_id`),
  ADD KEY `idx_cells_igreja` (`igreja_id`);

--
-- Índices para tabela `cell_meetings`
--
ALTER TABLE `cell_meetings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cm_meet` (`cell_id`),
  ADD KEY `idx_cm_meet_data` (`data`);

--
-- Índices para tabela `cell_members`
--
ALTER TABLE `cell_members`
  ADD PRIMARY KEY (`cell_id`,`member_id`),
  ADD KEY `fk_cm_member` (`member_id`);

--
-- Índices para tabela `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dept_igreja_nome` (`igreja_id`,`nome`),
  ADD KEY `fk_dept_lider` (`lider_id`),
  ADD KEY `fk_dept_vice` (`vice_id`),
  ADD KEY `idx_dept_igreja` (`igreja_id`);

--
-- Índices para tabela `department_members`
--
ALTER TABLE `department_members`
  ADD PRIMARY KEY (`department_id`,`member_id`),
  ADD KEY `fk_dm_member` (`member_id`);

--
-- Índices para tabela `ebd_classes`
--
ALTER TABLE `ebd_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ebd_igreja` (`igreja_id`);

--
-- Índices para tabela `ebd_students`
--
ALTER TABLE `ebd_students`
  ADD PRIMARY KEY (`class_id`,`member_id`),
  ADD KEY `fk_ebd_s_member` (`member_id`);

--
-- Índices para tabela `ebd_teachers`
--
ALTER TABLE `ebd_teachers`
  ADD PRIMARY KEY (`class_id`,`member_id`),
  ADD KEY `fk_ebd_t_member` (`member_id`);

--
-- Índices para tabela `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ev_resp` (`responsavel_id`),
  ADD KEY `fk_ev_user` (`criado_por`),
  ADD KEY `idx_ev_igreja_data` (`igreja_id`,`data`);

--
-- Índices para tabela `event_attendances`
--
ALTER TABLE `event_attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ea` (`event_id`,`member_id`),
  ADD KEY `fk_ea_member` (`member_id`);

--
-- Índices para tabela `igrejas`
--
ALTER TABLE `igrejas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `nif` (`nif`),
  ADD KEY `fk_igrejas_criador` (`criado_por`),
  ADD KEY `idx_igrejas_status` (`status`),
  ADD KEY `idx_igrejas_plano` (`plano_id`),
  ADD KEY `idx_igrejas_pai` (`igreja_pai_id`);

--
-- Índices para tabela `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_members_igreja_codigo` (`igreja_id`,`codigo`),
  ADD KEY `fk_members_user` (`criado_por`),
  ADD KEY `idx_members_igreja` (`igreja_id`),
  ADD KEY `idx_members_status` (`status`),
  ADD KEY `idx_members_nome` (`nome`);

--
-- Índices para tabela `member_families`
--
ALTER TABLE `member_families`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mf` (`member_id`,`parente_id`),
  ADD KEY `fk_mf_igreja` (`igreja_id`),
  ADD KEY `fk_mf_parente` (`parente_id`);

--
-- Índices para tabela `monthly_closures`
--
ALTER TABLE `monthly_closures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mc` (`igreja_id`,`ano`,`mes`),
  ADD KEY `fk_mc_user` (`fechado_por`);

--
-- Índices para tabela `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pay_sub` (`subscription_id`),
  ADD KEY `fk_pay_sup` (`confirmado_por`),
  ADD KEY `idx_pay_estado` (`estado`),
  ADD KEY `idx_pay_igreja` (`igreja_id`);

--
-- Índices para tabela `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_perm_modulo_acao` (`modulo`,`acao`),
  ADD KEY `idx_perm_modulo` (`modulo`);

--
-- Índices para tabela `planos`
--
ALTER TABLE `planos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_planos_ativo` (`ativo`),
  ADD KEY `idx_planos_ordem` (`ordem`);

--
-- Índices para tabela `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_igreja_slug` (`igreja_id`,`slug`),
  ADD KEY `idx_roles_sistema` (`sistema`);

--
-- Índices para tabela `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_rp_perm` (`permission_id`);

--
-- Índices para tabela `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_set_igreja_chave` (`igreja_id`,`chave`),
  ADD KEY `idx_set_chave` (`chave`);

--
-- Índices para tabela `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sub_plano` (`plano_id`),
  ADD KEY `idx_sub_igreja` (`igreja_id`),
  ADD KEY `idx_sub_status` (`status`),
  ADD KEY `idx_sub_expira` (`expiracao`);

--
-- Índices para tabela `subscription_notifications`
--
ALTER TABLE `subscription_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sn_sub` (`subscription_id`),
  ADD KEY `idx_sn_tipo` (`tipo`),
  ADD KEY `idx_sn_igreja` (`igreja_id`);

--
-- Índices para tabela `supervisores`
--
ALTER TABLE `supervisores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_supervisores_nivel` (`nivel`),
  ADD KEY `idx_supervisores_ativo` (`ativo`);

--
-- Índices para tabela `supervisor_igrejas`
--
ALTER TABLE `supervisor_igrejas`
  ADD PRIMARY KEY (`supervisor_id`,`igreja_id`),
  ADD KEY `fk_supig_igreja` (`igreja_id`);

--
-- Índices para tabela `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tr_cat` (`categoria_id`),
  ADD KEY `fk_tr_member` (`member_id`),
  ADD KEY `fk_tr_user` (`criado_por`),
  ADD KEY `idx_tr_igreja_data` (`igreja_id`,`data`),
  ADD KEY `idx_tr_tipo` (`tipo`);

--
-- Índices para tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email_igreja` (`igreja_id`,`email`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_ativo` (`ativo`),
  ADD KEY `idx_users_token` (`token_reset`);

--
-- Índices para tabela `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`user_id`,`permission_id`),
  ADD KEY `fk_up_perm` (`permission_id`);

--
-- Índices para tabela `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Índices para tabela `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `fk_ur_role` (`role_id`);

--
-- Índices para tabela `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_us_user` (`user_id`),
  ADD KEY `idx_us_expira` (`expira_em`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `backups`
--
ALTER TABLE `backups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cells`
--
ALTER TABLE `cells`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cell_meetings`
--
ALTER TABLE `cell_meetings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `departments`
--
ALTER TABLE `departments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `ebd_classes`
--
ALTER TABLE `ebd_classes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `events`
--
ALTER TABLE `events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `event_attendances`
--
ALTER TABLE `event_attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `igrejas`
--
ALTER TABLE `igrejas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `members`
--
ALTER TABLE `members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `member_families`
--
ALTER TABLE `member_families`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `monthly_closures`
--
ALTER TABLE `monthly_closures`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT de tabela `planos`
--
ALTER TABLE `planos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `subscription_notifications`
--
ALTER TABLE `subscription_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `supervisores`
--
ALTER TABLE `supervisores`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_al_igr` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_al_sup` FOREIGN KEY (`supervisor_id`) REFERENCES `supervisores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `backups`
--
ALTER TABLE `backups`
  ADD CONSTRAINT `fk_bkp_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `fk_cat_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `cells`
--
ALTER TABLE `cells`
  ADD CONSTRAINT `fk_cells_anfitriao` FOREIGN KEY (`anfitriao_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cells_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cells_lider` FOREIGN KEY (`lider_id`) REFERENCES `members` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `cell_meetings`
--
ALTER TABLE `cell_meetings`
  ADD CONSTRAINT `fk_cm_meet` FOREIGN KEY (`cell_id`) REFERENCES `cells` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `cell_members`
--
ALTER TABLE `cell_members`
  ADD CONSTRAINT `fk_cm_cell` FOREIGN KEY (`cell_id`) REFERENCES `cells` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cm_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_dept_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dept_lider` FOREIGN KEY (`lider_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dept_vice` FOREIGN KEY (`vice_id`) REFERENCES `members` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `department_members`
--
ALTER TABLE `department_members`
  ADD CONSTRAINT `fk_dm_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dm_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `ebd_classes`
--
ALTER TABLE `ebd_classes`
  ADD CONSTRAINT `fk_ebd_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `ebd_students`
--
ALTER TABLE `ebd_students`
  ADD CONSTRAINT `fk_ebd_s_class` FOREIGN KEY (`class_id`) REFERENCES `ebd_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ebd_s_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `ebd_teachers`
--
ALTER TABLE `ebd_teachers`
  ADD CONSTRAINT `fk_ebd_t_class` FOREIGN KEY (`class_id`) REFERENCES `ebd_classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ebd_t_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_ev_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ev_resp` FOREIGN KEY (`responsavel_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ev_user` FOREIGN KEY (`criado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `event_attendances`
--
ALTER TABLE `event_attendances`
  ADD CONSTRAINT `fk_ea_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ea_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `igrejas`
--
ALTER TABLE `igrejas`
  ADD CONSTRAINT `fk_igrejas_criador` FOREIGN KEY (`criado_por`) REFERENCES `supervisores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_igrejas_pai` FOREIGN KEY (`igreja_pai_id`) REFERENCES `igrejas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_igrejas_plano` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `fk_members_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_members_user` FOREIGN KEY (`criado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `member_families`
--
ALTER TABLE `member_families`
  ADD CONSTRAINT `fk_mf_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mf_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mf_parente` FOREIGN KEY (`parente_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `monthly_closures`
--
ALTER TABLE `monthly_closures`
  ADD CONSTRAINT `fk_mc_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mc_user` FOREIGN KEY (`fechado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_pay_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pay_sub` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pay_sup` FOREIGN KEY (`confirmado_por`) REFERENCES `supervisores` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `fk_roles_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `settings`
--
ALTER TABLE `settings`
  ADD CONSTRAINT `fk_set_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `fk_sub_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sub_plano` FOREIGN KEY (`plano_id`) REFERENCES `planos` (`id`);

--
-- Limitadores para a tabela `subscription_notifications`
--
ALTER TABLE `subscription_notifications`
  ADD CONSTRAINT `fk_sn_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sn_sub` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `supervisor_igrejas`
--
ALTER TABLE `supervisor_igrejas`
  ADD CONSTRAINT `fk_supig_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_supig_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `supervisores` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_tr_cat` FOREIGN KEY (`categoria_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tr_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tr_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tr_user` FOREIGN KEY (`criado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_igreja` FOREIGN KEY (`igreja_id`) REFERENCES `igrejas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `fk_up_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `fk_up_profile` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_us_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
