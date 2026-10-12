-- Migração 002 — converte a tabela legada MyISAM para InnoDB.
-- Pré-condição: confirmar com SHOW TABLE STATUS que `cotas_diarias` existe e
-- fazer um backup SQL completo antes de importar este ficheiro.
-- A conversão preserva as linhas e permite transacções/FKs nas fases seguintes.
-- É seguro voltar a executar; não acrescenta nem remove colunas.
-- Não adicionar ainda `igreja_id`: o preenchimento e a FK entram na Fase 6,
-- depois de confirmada a estrutura real das seis tabelas existentes.

ALTER TABLE `cotas_diarias` ENGINE=InnoDB;

-- Reversão (apenas antes de adicionar FKs e após backup):
-- ALTER TABLE `cotas_diarias` ENGINE=MyISAM;
