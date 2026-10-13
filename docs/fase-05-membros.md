# Fase 5 — Migração módulo a módulo

**Estado:** código MVC da sequência da Fase 5 implementado para revisão; todos os módulos continuam **desactivados por omissão** e protegidos por feature gates. A activação depende da Fase 6, da confirmação do esquema real e de testes de isolamento em staging. **Não foram executadas migrations nem migrações/backfills de dados.**

## Regras transversais

- Controllers coordenam pedidos e respostas; não contêm SQL. A persistência fica em repositories e a validação/regras de negócio em services.
- As consultas usam PDO preparado. O `igreja_id` vem do utilizador revalidado em sessão (`AuthMiddleware` + `TenantMiddleware`), nunca de query, formulário ou rota. Lookups por ID combinam o ID com o tenant na mesma consulta; os joins financeiros repetem o escopo da igreja.
- As vistas escapam dados com `e()` por omissão e formulários POST incluem CSRF. Superadmin é redireccionado para `/superadmin` e não recebe tenant.
- A Fase 5 não acrescenta colunas tenant às tabelas de negócio, não presume DDL de produção e não altera dados históricos. As rotas procedurais não servem de fallback: a `LegacyBridge` continua fail-closed.

## Sequência e entregáveis

| Ordem | Módulo | Implementação MVC nesta fase | Gate por omissão |
|---:|---|---|---|
| 1 | Membros | Pesquisa, filtros, paginação, estatísticas, criação, edição, detalhe, activação/desactivação e remoção segura; com histórico financeiro, desactiva em vez de apagar. | `MEMBERS_MVC_ENABLED=false` |
| 2 | Cotas | Registo diário, edição/remoção apenas no dia corrente, referência automática, listas de membros activos e PDF do dia. A periodicidade anual é um ciclo móvel de 12 meses, ancorado na data inicial manual da igreja, sem reinício no ano civil (D‑06). | `COTAS_MVC_ENABLED=false` |
| 3 | Listas | Grelha dos domingos com filtros por mês, ano e categoria, saldos/quitação e PDF em paisagem. O cálculo respeita início manual e ciclos anuais móveis. | `LISTS_MVC_ENABLED=false` |
| 4 | Saídas | Registo, edição, remoção e saldo separado por origem (`cotas` ou `contribuicoes`); não há transferência entre fundos. | `EXPENSES_MVC_ENABLED=false` |
| 5 | Fim de Ano | Contribuições por membro/ano, limite anual de 5.000,00 Kz, início manual, consulta de saldo e PDF. | `YEAR_END_MVC_ENABLED=false` |
| 6 | Relatórios/PDF | Resumos por período e ano, saídas por categoria, PDF financeiro e de despesas e CSV UTF‑8 compatível com folhas de cálculo. O CSV neutraliza fórmulas. | `REPORTS_MVC_ENABLED=false` |
| 7 | Utilizadores | Gestão de contas locais, papéis e permissões por módulo; activação/redefinição por link de uso único em fila cifrada. A interface não cria nem edita Superadmin/admin da igreja, não expõe senhas e não permite alterar a própria conta por esta área. | `USERS_MVC_ENABLED=false` |
| 8 | Auditoria | Consulta tenant-scoped com filtros e PDF; visível apenas ao admin da igreja. | `AUDIT_MVC_ENABLED=false` |
| 9 | Backups | Exportação JSON tenant-scoped com limite de 50.000 registos, sem hashes/senhas, tokens, sessões ou payloads de email; apenas admin da igreja. Não há SQL executável nem restauro pela interface. | `BACKUPS_MVC_ENABLED=false` |
| 10 | Perfil | Consulta/edição do próprio nome; email e papel são só de leitura. O nome é sincronizado na sessão sem incrementar `versao_sessao`, mantendo a sessão actual ligada. | `PROFILE_MVC_ENABLED=false` |
| 11 | Dashboard | Métricas, actividade e atalhos só consultam módulos cuja permissão e gate estejam activos. Sem gates activos, o painel não consulta tabelas de negócio. | Métricas/atalhos condicionais |
| 12 | Configurações | Identidade visual e valores/data inicial manual das cotas e contribuições, apenas por admin da igreja. Datas e montantes são validados no servidor; regras financeiras ficam protegidas contra alteração depois do primeiro pagamento. | `SETTINGS_MVC_ENABLED=false` |

Ficheiros centrais: `app/Controllers/Igreja/`, `app/Services/`, `app/Repositories/` e `app/Views/igreja/`. As rotas canónicas estão em `routes/web.php`. Os PDFs usam o layout comum `app/Views/pdf/layout.php`, com fontes remotas desligadas.

## Limites deliberados e rotas ainda bloqueadas

- O histórico completo de pagamentos de um membro (`/membros/historico-pagamentos`) permanece 503: depende da integração e validação conjunta dos módulos financeiros e não tem fallback procedural.
- O recálculo das referências antigas (`/cotas/recalcular-referencias`) permanece 503. Reescreve histórico financeiro e precisa de uma pré-visualização tenant-scoped, protocolo de reversão e exemplos de teste aprovados antes de poder ser exposto. A decisão D‑02 proíbe fragmentar um pagamento real; nenhuma correcção foi executada nos dados.
- Backups ficam em JSON deliberadamente; SQL executável e restauro não são disponibilizados como exportação web multi-tenant.
- A rota de PDF de saídas é `/relatorios/pdf-despesas`, a mesma registada em `routes/web.php` e usada pela vista.
- Mesmo os módulos já preparados continuam fechados. A Fase 6 terá de confirmar `SHOW CREATE TABLE` e atribuição tenant antes de activar qualquer gate, bem como validar transacções, índices, permissões e isolamento entre igrejas em staging.

## Validação desta entrega

- `git diff --check`: passou.
- Tree-sitter-PHP analisou 143 ficheiros PHP de `app/`, `routes/`, `config/`, `public/` e `tests/`: zero erros de árvore sintáctica. Foi também verificada a correspondência dos 126 handlers de controller registados nas rotas com métodos existentes; zero referências por resolver. Estas verificações estáticas não substituem o interpretador PHP.
- PHP CLI, Composer e MySQL não estão disponíveis neste ambiente: `php -l`, os smoke tests PHP, execução das consultas PDO, testes HTTP/CSRF/IDOR, renderização Dompdf e validação contra o DDL real não foram executados.
- Não foram executados `SHOW CREATE TABLE`, migrations, migração de dados, consultas de produção nem testes de staging. Não alterar os gates para `true` antes desses passos.

## Continuação

A Fase 5 termina aqui. A próxima etapa é a Fase 6, **fora do âmbito desta entrega**: Renato deve fornecer `SHOW CREATE TABLE` real das tabelas de negócio e aprovar o mapeamento histórico. Só então se pode desenhar/aplicar migração tenant, testar isolamento e activar os módulos um a um. Nenhuma migration ou migração de dados da Fase 6 foi executada nesta fase.
