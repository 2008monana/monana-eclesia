# Correcções aplicadas

## O bug do login
`Database::connection()` chamava `config('database')` (sem ponto) e a função `config()` só aceitava `ficheiro.chave`,
devolvendo `null` → "Configuração da base de dados em falta".
**Correcção:** `config('database')` devolve agora o ficheiro inteiro (`app/Helpers/helpers.php`).

## Outras correcções (encontradas na análise)
- `.env` nunca era carregado (phpdotenv sem `load()`): agora carregado em `public/index.php`; `config/*.php` lêem do `.env`.
- URLs: `redirect()/url()/asset()` usam `base_url()` (APP_URL ou deduzido do pedido) — antes ficavam sem `/monana-eclesia/public`.
- `Mailer` passou a usar `config/mail.php`; sem SMTP não tenta enviar (e em debug regista o conteúdo no log).
- `Model`: falha em fechado sem igreja activa; `igreja_id` nunca vem do input; `$fillable` obrigatório; ORDER BY validado.
- `TenantMiddleware`: recusa utilizadores sem igreja e igrejas suspensas/canceladas (também no login).
- Token de recuperação guardado como hash SHA-256; sessões antigas terminadas após o reset.
- Modo debug por `APP_DEBUG` (antes `display_errors` fixo); handler de excepções com log.
- Rotas `/forgot` e `/reset` só para visitantes; `/superadmin/login` redireciona se já autenticado.
- Login: tempo de resposta igualado quando o e-mail não existe; `session_regenerate_id` também no logout.
- CSRF: sem open redirect via `Referer`.
- `DashboardStats`: 12 queries → 1 (com intervalos de datas) e correcção do salto de mês (ex.: 31 de Outubro − 1 mês).
- Dashboard: JSON dentro de `<script>` com `JSON_HEX_*` (evita injecção por nomes de departamentos).
- `composer.json`: removido `config/constants.php` de `autoload.files` (evita "Constant already defined").
- `.gitignore`: `composer.lock` passa a ser versionado.
- `.htaccess`: raiz (encaminha para `public/`), `storage/`, `public/uploads/` (sem execução de PHP) e
  `uploads/comprovativos/` (sem acesso directo); cabeçalhos de segurança em `public/`.
- `database/schema.sql` (o seu dump) e `database/seeds/dev_igreja_demo.sql` (dados de teste).

## Ainda por fazer (não era possível sem as fases seguintes)
- Superadmin: `must_change_password=1` no superadmin ainda não é imposto (o `/superadmin` é um placeholder).
- `user_sessions` é escrita mas ainda não é consultada (revogação de sessões).
- Limite de tentativas por IP; 14 controllers, `Validator`, `Audit`, `Subscription` e `PermissionMiddleware` por implementar.
