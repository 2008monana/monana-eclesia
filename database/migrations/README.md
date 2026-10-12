# Migrações base (Fase 2)

As migrações desta fase preparam apenas a fundação da plataforma; **não** migram membros, cotas, despesas ou utilizadores da igreja actual.

## Ordem de aplicação

1. `001_criar_planos.sql` — cria o catálogo de planos.
2. `002_converter_cotas_diarias_innodb.sql` — **só** numa base de dados que já tenha a tabela `cotas_diarias`. Antes, exporta um backup e confirma o motor com `SHOW TABLE STATUS LIKE 'cotas_diarias';`. A conversão pode bloquear a tabela enquanto decorre.
3. `003_criar_igrejas_e_configuracoes.sql` — cria as tabelas `igrejas` e `configuracoes_igreja`.

Numa instalação nova, importa 001 e 003; omite 002 porque ainda não existe a tabela antiga. Numa base existente, importa pela ordem acima através do phpMyAdmin (não é necessário SSH). Os ficheiros 001 e 003 usam `CREATE TABLE IF NOT EXISTS`; 002 pode ser repetida, mas não se deve executar sem confirmar a tabela e ter backup.

## Limites desta fase

- As migrações **não foram executadas** neste ambiente, que não tem PHP CLI nem MySQL e não possui as credenciais nem o DDL real da base de produção.
- Ainda não adicionam `igreja_id` às seis tabelas existentes. O backfill da igreja 1, as FKs e os índices ficam para a Fase 6, depois de validar o `SHOW CREATE TABLE` real.
- A tabela `planos` não recebe plano fictício nem limites comerciais inventados; os planos serão configurados pelo Superadmin na Fase 3.
- Ao reverter, faz backup e remove primeiro as tabelas dependentes (`configuracoes_igreja`, depois `igrejas`, depois `planos`). Só converte `cotas_diarias` de volta para MyISAM se ainda não tiver FKs e se essa reversão tiver sido aprovada.
