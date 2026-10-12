# Fase 2 — Núcleo MVC, configuração e migrações base

**Estado:** implementação preparada; aguarda validação do utilizador e teste num ambiente com PHP/MySQL.
**Âmbito:** núcleo e configuração. A autenticação nova, o Superadmin e os módulos continuam para as fases seguintes.

## O que ficou implementado

- Autoload PSR‑4 `App\` para `app/`, disponível pelo autoloader próprio e declarado também em `composer.json`.
- Núcleo em `app/Core`: `Autoload`, `Config`, `Request`, `Response`, `Router`, `Controller`, `View`, `Database`, `Session`, `Validator`, `ErrorHandler` e ponte temporária `LegacyBridge`.
- `.env.example` e configuração separada para aplicação, MySQL/MariaDB e email (`config/app.php`, `config/database.php`, `config/mail.php`). A leitura de `.env` é nativa, sem dependência nova.
- Front controller único em `public/index.php`; `index.php` na raiz é apenas a entrada compatível com o `htdocs` da InfinityFree.
- `routes/web.php` centraliza rotas canónicas em português. Os caminhos ingleses antigos fazem 301 em GET/HEAD; enquanto um módulo não for migrado, a ponte inclui somente páginas antigas explicitamente permitidas, sem as servir directamente; assim, o trabalho da Fase 2 não desactiva as páginas que ainda funcionam.
- Cabeçalhos `X-Frame-Options`, `X-Content-Type-Options` e `Referrer-Policy`; suporte para forçar HTTPS por configuração; cookies de sessão HttpOnly, Secure quando aplicável, SameSite=Lax e expiração por inactividade.
- Erros técnicos registados em `storage/logs/app.log`; em produção a resposta não mostra detalhes. Os directórios internos, `.env`, SQL, logs, JSON de dependências e `vendor/` ficam bloqueados por `.htaccess`.
- Três migrações numeradas para planos, conversão segura de `cotas_diarias` para InnoDB e criação de `igrejas`/`configuracoes_igreja`. A configuração já contempla o valor, periodicidade e início manual das cotas/contribuições acordados.

## Compatibilidade preservada nesta fase

As páginas procedurais em `modules/` continuam no repositório e passam temporariamente pelo `LegacyBridge`, com uma allowlist de endpoints. Nenhuma página foi convertida ainda para repositório/serviço MVC; isso será feito módulo a módulo na Fase 5. Os controladores e o router do refactor anterior (`controllers/`, `core/`) não são carregados pelo novo router e as respectivas pastas ficam bloqueadas por HTTP.

A rota `/login` serve a página actual para o teste de passagem pelo front controller. O login ainda usa o código antigo e será substituído na Fase 3; por isso, esta fase não é uma entrega de autenticação SaaS.

## Configuração antes de testar

1. Copia `.env.example` para `.env` e preenche `APP_URL`, credenciais MySQL/MariaDB e, se estiver numa subpasta, `APP_BASE_PATH` (vazio no domínio raiz da InfinityFree).
2. Em desenvolvimento local sem HTTPS, define `APP_ENV=local` e `APP_FORCE_HTTPS=false`. Em produção, mantém HTTPS activo e `APP_DEBUG=false`.
3. Gera `vendor/` num computador com Composer e PHP compatível (`composer install --no-dev --optimize-autoloader`) e envia-o por FTP. Nesta fase não foi gerado nem incluído `vendor/`.
4. Num servidor Apache com mod_rewrite, abre `/login`: deve passar pelo front controller e mostrar a página actual. O GET do formulário não necessita de abrir a ligação PDO; submeter o login requer uma base configurada.
5. Confirma que pedidos directos a `/config/`, `/app/`, `/storage/`, `/public/index.php` e `.env` são bloqueados; os recursos públicos em `/assets/` devem continuar acessíveis.

## Migrações SQL

Consulta `database/migrations/README.md`. As migrações **não foram executadas**. Numa BD existente, faz backup e confirma primeiro a tabela `cotas_diarias`; não se adicionou `igreja_id` às tabelas antigas. O backfill da igreja 1, as FKs e a obrigatoriedade de `igreja_id` ficam para a Fase 6, depois de validar o DDL real.

## Verificações feitas aqui

- Parser tree-sitter-php: sintaxe reconhecida em **32 ficheiros PHP** do núcleo/configuração e parciais; **24** são novos ou alterados nesta fase.
- `git diff --check`: sem erros de whitespace.
- A versão do código evita construções exclusivas de PHP 8; a compatibilidade final deve ser confirmada com PHP 7.4.
- **Não foi possível executar `php -l`, servir `/login` nem importar SQL:** este ambiente não tem interpretador PHP nem MySQL. Também não há credenciais nem `SHOW CREATE TABLE` da BD de produção.

Com PHP local, valida a sintaxe com:

```bash
find app config routes public -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php -l index.php
```

## Fica para as próximas fases

- Fase 3: autenticação nova, CSRF/rate‑limit, Superadmin, provisionamento e fila de email.
- Fase 4: layouts e parciais MVC fiéis à identidade MonanaEclésia.
- Fase 5: retirar a ponte de cada módulo somente depois da migração e validação equivalentes.
- Fase 6: importar os dados reais, atribuir igreja 1 e aplicar `igreja_id` com FKs.
