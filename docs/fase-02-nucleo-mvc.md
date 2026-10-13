# Fase 2 — Núcleo MVC, configuração e migrações base

**Estado:** núcleo implementado; validação runtime continua pendente por falta de PHP/MySQL neste ambiente. A implementação de autenticação/Superadmin foi acrescentada na Fase 3 (`docs/fase-03-autenticacao-superadmin.md`).
**Âmbito original:** núcleo e configuração. Este documento é o registo da Fase 2; usar a documentação da Fase 3 para o estado actual do login, ponte legada e rotas.

## O que ficou implementado

- Autoload PSR‑4 `App\` para `app/`, disponível pelo autoloader próprio e declarado também em `composer.json`.
- Núcleo em `app/Core`: `Autoload`, `Config`, `Request`, `Response`, `Router`, `Controller`, `View`, `Database`, `Session`, `Validator`, `ErrorHandler` e ponte temporária `LegacyBridge`.
- `.env.example` e configuração separada para aplicação, MySQL/MariaDB e email (`config/app.php`, `config/database.php`, `config/mail.php`). A leitura de `.env` é nativa, sem dependência nova.
- Front controller único em `public/index.php`; `index.php` na raiz é apenas a entrada compatível com o `htdocs` da InfinityFree.
- `routes/web.php` centraliza rotas canónicas em português. Os caminhos ingleses antigos fazem 301 em GET/HEAD. Na Fase 3, a ponte passou a fail-closed para módulos de igreja não migrados: autenticados recebem 503 e Superadmin é redireccionado ao painel da plataforma até à migração e isolamento por tenant.
- Cabeçalhos `X-Frame-Options`, `X-Content-Type-Options` e `Referrer-Policy`; suporte para forçar HTTPS por configuração; cookies de sessão HttpOnly, Secure quando aplicável, SameSite=Lax e expiração por inactividade.
- Erros técnicos registados em `storage/logs/app.log`; em produção a resposta não mostra detalhes. Os directórios internos, `.env`, SQL, logs, JSON de dependências e `vendor/` ficam bloqueados por `.htaccess`.
- Três migrações numeradas para planos, conversão segura de `cotas_diarias` para InnoDB e criação de `igrejas`/`configuracoes_igreja`. A configuração já contempla o valor, periodicidade e início manual das cotas/contribuições acordados.

## Estado após a Fase 3

As páginas procedurais em `modules/` continuam no repositório, mas `LegacyBridge` não as executa para contas autenticadas, porque ainda não filtram por `igreja_id`; devolve 503 até a migração segura na Fase 5. Nenhuma lógica de negócio foi migrada. Os controladores antigos em `controllers/` e o núcleo antigo em `core/` não são carregados pelo router MVC.

`/login` agora é servido pelo controlador MVC e integra autenticação, CSRF, sessões e rate-limit; os detalhes estão em `docs/fase-03-autenticacao-superadmin.md`. O painel da igreja permanece sem queries às tabelas de negócio nesta fase.

## Configuração antes de testar

1. Copia `.env.example` para `.env` e preenche `APP_URL`, credenciais MySQL/MariaDB e, se estiver numa subpasta, `APP_BASE_PATH` (vazio no domínio raiz da InfinityFree).
2. Em desenvolvimento local sem HTTPS, define `APP_ENV=local` e `APP_FORCE_HTTPS=false`. Em produção, mantém HTTPS activo e `APP_DEBUG=false`.
3. Gera `vendor/` num computador com Composer e PHP compatível (`composer install --no-dev --optimize-autoloader`) e envia-o por FTP. Nesta fase não foi gerado nem incluído `vendor/`.
4. Num servidor Apache com mod_rewrite, abre `/login`: deve passar pelo front controller e mostrar o ecrã MVC em português. O GET não necessita de consulta MySQL para uma sessão anónima; autenticação requer base configurada.
5. Confirma que pedidos directos a `/config/`, `/app/`, `/storage/`, `/database/`, `/tests/`, `vendor/` e `.env` são bloqueados; `/public/index.php` é apenas o front controller público e não deve expor ficheiros internos. Os recursos públicos em `/assets/` devem continuar acessíveis.

## Migrações SQL

Consulta `database/migrations/README.md`. As migrações **não foram executadas**. Numa BD existente, faz backup e confirma primeiro a tabela `cotas_diarias`; não se adicionou `igreja_id` às tabelas antigas. O backfill da igreja 1, as FKs e a obrigatoriedade de `igreja_id` ficam para a Fase 6, depois de validar o DDL real.

## Verificações registadas na Fase 2

- No snapshot da Fase 2, parser tree-sitter-php reconheceu **32 ficheiros PHP** do núcleo/configuração e parciais; **24** eram novos ou alterados naquela fase. Esta contagem é histórica; a validação estática actualizada da Fase 3 consta em `docs/fase-03-autenticacao-superadmin.md`.
- Na Fase 2, `git diff --check` não indicou whitespace inválido. A compatibilidade efectiva deve ser confirmada com PHP 7.4.
- **Não foi possível executar `php -l`, servir `/login` nem importar SQL:** este ambiente não tem interpretador PHP nem MySQL. Também não há credenciais nem `SHOW CREATE TABLE` da BD de produção.

Com PHP local, valida a sintaxe com:

```bash
find app config routes public -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php -l index.php
```

## Fica para as próximas fases

- Fase 3 foi implementada; ver `docs/fase-03-autenticacao-superadmin.md` para o que está e não está verificado.
- Fase 4/5: avançar apenas depois da revisão desta fase; migrar layouts/módulos progressivamente e remover a ponte por módulo após validação equivalente.
- Fase 6: importar os dados reais, atribuir tenants e aplicar `igreja_id` com FKs apenas depois do `SHOW CREATE TABLE` de produção e aprovação do mapeamento.
