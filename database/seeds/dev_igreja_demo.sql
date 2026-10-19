-- ============================================================
-- SEED DE DESENVOLVIMENTO — NÃO usar em produção.
-- Cria 1 igreja de demonstração + 1 administrador + dados de exemplo
-- para testar o login de utilizador (/login) e o dashboard.
--
--   E-mail:        admin@igreja-demo.test
--   Palavra-passe: Demo@2026!
--
-- Executar UMA vez, depois de importar database/schema.sql.
-- ============================================================
USE `monana_eclesia`;

INSERT INTO `igrejas` (`id`, `nome`, `slug`, `email`, `cidade`, `provincia`, `status`)
VALUES (1, 'Igreja Demonstração', 'igreja-demo', 'demo@igreja-demo.test', 'Luanda', 'Luanda', 'activa');

INSERT INTO `users` (`id`, `igreja_id`, `nome`, `email`, `senha_hash`, `must_change_password`)
VALUES (1, 1, 'Administrador Demo', 'admin@igreja-demo.test',
        '$argon2id$v=19$m=65536,t=4,p=1$NFIwbE14ZFV6cEtRSlRVaQ$ePVLox8/z9wCXhAwBhepj+7RxupJTbbny9qyvPircZw', 0);

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES (1, 1);

INSERT INTO `members` (`igreja_id`, `codigo`, `nome`, `status`) VALUES
(1, 'M0001', 'Maria Fernandes', 'activo'),
(1, 'M0002', 'João Baptista',   'activo'),
(1, 'M0003', 'Ana Domingos',    'activo'),
(1, 'M0004', 'Pedro Kiala',     'inactivo');

INSERT INTO `departments` (`id`, `igreja_id`, `nome`, `cor`) VALUES
(1, 1, 'Louvor',    '#b8862b'),
(2, 1, 'Juventude', '#3b82a0');

INSERT INTO `department_members` (`department_id`, `member_id`)
SELECT 1, id FROM `members` WHERE `igreja_id` = 1 AND `codigo` IN ('M0001', 'M0002')
UNION ALL
SELECT 2, id FROM `members` WHERE `igreja_id` = 1 AND `codigo` = 'M0003';

INSERT INTO `cells` (`igreja_id`, `nome`) VALUES (1, 'Célula Esperança');

INSERT INTO `events` (`igreja_id`, `titulo`, `tipo`, `data`, `hora`, `local`) VALUES
(1, 'Culto de Domingo',  'culto',   DATE_ADD(CURDATE(), INTERVAL 3 DAY),  '09:00:00', 'Templo principal'),
(1, 'Vigília de Oração', 'oração',  DATE_ADD(CURDATE(), INTERVAL 10 DAY), '21:00:00', 'Templo principal');

-- Movimentos dos últimos meses (datas relativas a hoje)
INSERT INTO `transactions` (`igreja_id`, `tipo`, `descricao`, `valor_kz`, `data`) VALUES
(1, 'entrada', 'Ofertas do mês',      250000.00, CURDATE()),
(1, 'despesa', 'Água e electricidade', 45000.00, CURDATE()),
(1, 'entrada', 'Ofertas do mês',      210000.00, DATE_SUB(CURDATE(), INTERVAL 1 MONTH)),
(1, 'despesa', 'Manutenção',           80000.00, DATE_SUB(CURDATE(), INTERVAL 1 MONTH)),
(1, 'entrada', 'Ofertas do mês',      180000.00, DATE_SUB(CURDATE(), INTERVAL 2 MONTH)),
(1, 'despesa', 'Material de limpeza',  30000.00, DATE_SUB(CURDATE(), INTERVAL 2 MONTH)),
(1, 'entrada', 'Ofertas do mês',      195000.00, DATE_SUB(CURDATE(), INTERVAL 3 MONTH));
