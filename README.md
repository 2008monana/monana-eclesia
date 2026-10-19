# Monana Eclesia — SaaS Multi-Igreja

PHP 8.1+ (MVC próprio) · MySQL/MariaDB · PHPMailer · phpdotenv

## Instalação local (XAMPP)

1. Copie a pasta para `C:\xampp\htdocs\monana-eclesia`.
2. No phpMyAdmin crie a base `monana_eclesia` (utf8mb4_unicode_ci) e importe `database/schema.sql`.
3. Confirme o `.env` (por omissão: `root` sem palavra-passe, `APP_URL=http://localhost/monana-eclesia/public`).
4. Abra `http://localhost/monana-eclesia/public/`.

### Acessos

| Área | URL | Credenciais |
|---|---|---|
| Superadmin (plataforma) | `/superadmin/login` | `superadmin@monana.app` + a palavra-passe definida na criação |
| Utilizador de igreja | `/login` | só existem depois de o superadmin criar uma igreja (Fase 2) |

> O superadmin **não** entra em `/login`: esse formulário usa a tabela `users` (utilizadores de igrejas).
> Para testar já o painel de uma igreja, importe **uma vez** `database/seeds/dev_igreja_demo.sql`
> (`admin@igreja-demo.test` / `Demo@2026!`). É só para desenvolvimento.

## Configuração (.env)

Toda a configuração vem do `.env` (carregado em `public/index.php`). `config/*.php` apenas lê essas variáveis.
`APP_DEBUG=true` mostra erros no ecrã; em produção use `false` (os erros ficam em `storage/logs/`).
E-mail: preencha `MAIL_*`; com `MAIL_HOST` vazio nada é enviado (em debug o conteúdo fica no log).

## Produção

- Aponte o DocumentRoot para `public/` (ou use o `.htaccess` da raiz como alternativa).
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` com https.
- Nunca publique `.env`, `.git/`, `storage/` nem `vendor/` na pasta web.
