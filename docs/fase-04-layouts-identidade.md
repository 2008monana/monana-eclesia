# Fase 4 — Layouts e identidade visual

**Estado:** implementação concluída para revisão; validação PHP/runtime e comparação visual dependem de um ambiente com PHP CLI.
**Âmbito:** shell MVC da aplicação, parciais, composição das vistas de email e modelo reutilizável para PDF. A Fase 5 não foi iniciada.

## Implementado

- `app/Views/partials/document-head.php` centraliza idioma `pt-AO`, metadados, CSRF, favicon, fontes e ícones. O tema `assets/css/monana-theme.css` continua **inline**, lido por `readfile()`; não foi substituído por `<link>`.
- `app/Views/layouts/app.php` é o shell autenticado comum. Usa parciais para navegação lateral, barra superior, rodapé, CSS complementar e comportamento do menu móvel. Inclui atalho de teclado para o conteúdo, `aria-current`, botão móvel com estado acessível e fecho por Escape.
- `app/Views/layouts/superadmin.php` mantém compatibilidade com os controllers da Fase 3 e delega para o shell comum com área `superadmin`, papel fixo e sem tenant. As ligações de plataforma continuam em `/superadmin`; alteração de senha e saída usam os endpoints de conta existentes.
- O painel da igreja é agora conteúdo MVC dentro do shell comum. Nome da igreja, nome do utilizador e papel são provenientes do utilizador autenticado em sessão e escapados; o identificador do tenant não é apresentado. Membros, cotas/contribuições e relatórios aparecem apenas como itens desactivados, sem `href` para módulos legados ainda sem isolamento.
- Os formulários de terminação de sessões e saída continuam `POST` e incluem CSRF. Os controllers não receberam SQL, e a autorização/tenant continuam a ser verificados antes de compor o layout.
- Os emails de activação e redefinição usam `app/Views/emails/layout.php`; conteúdo, nome da pessoa e nome da igreja são escapados. O marcador `{{LINK}}` foi preservado para a fila continuar a substituir o URL só depois de desencriptar o payload, sem guardar o token em claro.
- `app/Views/pdf/layout.php` e as parciais de identidade/cabeçalho/rodapé oferecem um modelo Dompdf sem fontes remotas, com logótipo embutido, identidade da igreja recebida como dado interno e data `dd/mm/aaaa`. O corpo é HTML de vistas internas; os metadados são escapados.
- As vistas `auth` e `superadmin` preservam o mesmo tema e passam a partilhar o cabeçalho, evitando três cópias da configuração visual.

## Limites deliberados

- Nenhum módulo de negócio foi reactivado ou migrado. `LegacyBridge` continua a devolver 503 para contas autenticadas; os itens de navegação desactivados não chamam endpoints antigos.
- O layout PDF é um componente para a migração MVC da Fase 5; nenhum endpoint PDF foi ligado nesta fase. Os geradores procedurais antigos e `includes/pdf-theme.php` não foram executados nem usados para consultar dados.
- Não houve alteração de esquema, migração de produção, importação ou movimentação de dados reais.
- Não foi possível comparar páginas no browser com a versão anterior: neste ambiente não há PHP CLI/Composer. O HTML/CSS foi preparado, mas a verificação visual deve ser feita quando o runtime web estiver disponível.

## Verificações

- Criado `tests/phase4_layout_smoke.php`, autónomo e sem MySQL, para conferir tema inline, CSRF, composição do shell, ausência de ligações a módulos pendentes, escaping de nomes, molduras de email e layout PDF.
- **Não executados:** `php -l`, `php tests/phase4_layout_smoke.php`, renderização HTTP, screenshot/comparação visual nem geração real via Dompdf, porque PHP CLI/Composer/runtime continuam indisponíveis.
- `git diff --check` passou. Tree-sitter-php analisou 92 ficheiros PHP de `app/`, `tests/`, `routes/`, `config/` e `public/`: zero erros de árvore sintáctica. Isto não equivale a `php -l`, execução PHP nem teste visual.

## Próxima fase

A Fase 5 pode migrar um módulo de cada vez, mantendo a sequência e as regras de `docs/fase-01-analise-e-mer.md`: tenant sempre obtido da sessão, consultas tenant-scoped com `igreja_id`, prepared statements, anti-IDOR, escaping por omissão e validação de regressão antes de remover cada ponte. A Fase 6 permanece fora do âmbito até Renato fornecer `SHOW CREATE TABLE` real e aprovar o mapeamento histórico.
