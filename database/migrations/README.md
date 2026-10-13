# Migrações base e Fase 3

As migrações preparam a plataforma sem migrar os dados de membros, cotas, despesas ou utilizadores da igreja actual. **Não foram executadas automaticamente.** Faz um backup verificável antes de qualquer alteração à base de produção.

## Ordem de aplicação

### Instalação nova

1. `001_criar_planos.sql` — cria o catálogo vazio de planos.
2. `003_criar_igrejas_e_configuracoes.sql` — cria igrejas e configuração por tenant.
3. `004_autenticacao_superadmin.sql` — cria/actualiza utilizadores e tabelas de autenticação, email e auditoria.

Não aplicar `002` numa base nova: ainda não existe `cotas_diarias` para converter.

### Base que já contém o sistema procedural

1. Exporta um backup SQL completo pelo phpMyAdmin e confirma que consegues restaurá-lo numa cópia.
2. Confirma o motor de `cotas_diarias` com `SHOW TABLE STATUS LIKE 'cotas_diarias';`. Aplica `002_converter_cotas_diarias_innodb.sql` apenas depois do backup e confirmação.
3. Aplica `001_criar_planos.sql`, depois `003_criar_igrejas_e_configuracoes.sql` e, por último, `004_autenticacao_superadmin.sql`.
4. Antes do 004, confirma que não existem emails duplicados (o login é global):

```sql
SELECT LOWER(TRIM(email)) AS email_normalizado, COUNT(*) AS quantidade
FROM usuarios
GROUP BY LOWER(TRIM(email))
HAVING COUNT(*) > 1;
```

Se a consulta devolver linhas, resolve os duplicados antes de aplicar o índice único. O 004 acrescenta as colunas novas a uma tabela `usuarios` já existente sem apagar os dados e mapeia `perfil='admin'` para `papel='admin_igreja'` e `perfil='gestor'` para `papel='tesoureiro'` (D‑05). O SQL pressupõe as colunas-base inventariadas em `docs/fase-01-analise-e-mer.md`, incluindo `perfil`; se o esquema real for diferente, pára e valida o `SHOW CREATE TABLE` antes de executar.

O 004 usa `CREATE TABLE IF NOT EXISTS` e verificações em `information_schema` + `PREPARE` para acrescentar colunas/índices apenas quando faltam. Não usa procedures nem exige SSH. A repetição é segura para a instalação preparada por estas migrações; se uma execução for interrompida a meio, lê primeiro o erro e confirma o estado antes de repetir.

## O que o 004 prepara

- `usuarios.igreja_id` (NULL para Superadmin), `papel`, `deve_trocar_senha`, `email_verificado_em` e `versao_sessao`; email único global.
- `permissoes_modulo`, `tokens_senha`, `tokens_ativacao`, `sessoes`, `tentativas_login`, `email_fila` e `platform_bootstrap`.
- `logs_auditoria.igreja_id`, deixando os logs antigos com NULL; nenhum tenant histórico é inferido.
- Estado terminal `encerrada` e `igrejas.encerrada_em` para fecho lógico sem apagar dados; a conta não pode ser reaberta pelo painel.
- Os tokens nas tabelas de tokens são guardados somente como SHA‑256. Links de activação/redefinição dentro da fila são cifrados com AES‑256-GCM usando `APP_ENCRYPTION_KEY` (32 bytes aleatórios, 64 caracteres hex); o HTML persistido contém apenas um marcador, não o token em claro.
- Se `planos` estiver vazia, cria apenas `Plano por configurar`, inactivo, com preço/limites a zero. Não é um produto comercial activo. O Superadmin define preço em Kz, limites e estado no painel.

Não foram acrescentadas ainda colunas `igreja_id` às seis tabelas de negócio — isso é Fase 6, após receber e validar o `SHOW CREATE TABLE` de cada uma. As páginas procedurais antigas não são executadas para utilizadores autenticados na Fase 3, porque ainda não filtram por tenant; são reactivadas apenas depois da migração e dos testes correspondentes.

## Primeiro Superadmin sem SSH/CLI no alojamento

1. No computador local, gera **dois valores aleatórios diferentes**, por exemplo com PHP local:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

2. Copia `.env.example` para `.env`; preenche `APP_URL`, dados MySQL e `APP_ENCRYPTION_KEY` (64 caracteres hex). Guarda o segundo segredo em `SUPERADMIN_BOOTSTRAP_TOKEN`.
3. Envia os ficheiros e `vendor/` por FTP. Abre `/instalacao/superadmin`, informa a chave configurada e escolhe nome, email e senha (mínimo 12 caracteres).
4. Depois da confirmação, **remove `SUPERADMIN_BOOTSTRAP_TOKEN` do `.env`**. A tabela `platform_bootstrap` também fecha a criação após o primeiro Superadmin.
5. Entra por `/login`. O seed não contém email nem senha hard-coded; a senha escolhida é gravada com Argon2id se disponível, senão bcrypt.

Nunca coloques os segredos no repositório nem os envies por email/chat. O `.htaccess` bloqueia acesso HTTP ao `.env` e às pastas internas; confirma também em Apache real.

## Composer, FTP e email

No computador local, com PHP 7.4+ e Composer, executa:

```bash
composer install --no-dev --optimize-autoloader
```

Envia o directório `vendor/` por FTP; `phpmailer/phpmailer` é a única nova dependência desta fase, além do Dompdf já declarado. Não há comandos Composer necessários no servidor.

`MAIL_MAILER=log` **não envia**: regista uma falha sem guardar endereço, conteúdo ou token nos logs. A mensagem e o seu payload cifrado ficam em `email_fila`, com estado, tentativas e erro. O Superadmin pode reenviar convites; se o link de activação expirou, é emitido outro token de uso único. Para SMTP, configura `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION=tls|ssl` e remetente. PHPMailer usa SMTP TLS/SSL e não desactiva validação de certificados.

Em alojamento gratuito, o SMTP externo pode ser bloqueado/limitado. Se a InfinityFree não permitir a entrega, testa um relay SMTP transaccional como Brevo ou Mailgun com a configuração oficial da conta. A fila permite reenviar após a troca; nenhuma falha de envio desfaz a criação da igreja. Links de activação expiram em 24 horas; links de redefinição expiram em 60 minutos.

## Reversão

- 004: fazer backup; remover primeiro as tabelas dependentes listadas no cabeçalho de `004_autenticacao_superadmin.sql`. Não remover colunas de `usuarios` nem logs se já houver utilizadores ou eventos novos. Se ainda não existir nenhuma igreja `encerrada`, a reversão da extensão de estado pode ser feita manualmente com `ALTER TABLE igrejas MODIFY estado ENUM('pendente','activa','suspensa') NOT NULL DEFAULT 'pendente';` e, se `encerrada_em` estiver vazia, `ALTER TABLE igrejas DROP COLUMN encerrada_em;`. Se houver igrejas encerradas, preserva a extensão e planeia a reversão; não as converte silenciosamente. A reversão de uma base já utilizada deve ser planeada manualmente.
- 003: só remover `configuracoes_igreja` e `igrejas` quando vazias e sem dependentes.
- 001: só remover `planos` depois de remover as igrejas que referenciam planos.
- 002: só converter de volta para MyISAM, após backup, se não houver FKs e a reversão tiver sido aprovada.

## Limites de validação deste ambiente

Este ambiente não tem PHP CLI nem MySQL e não dispõe das credenciais ou do DDL real da produção. Por isso, nenhuma migração foi executada aqui. As verificações de SQL, índices, SMTP e restauração devem ser feitas primeiro numa cópia/staging com a mesma versão de MySQL/MariaDB do alojamento.

## Gate de activação da Fase 5 — todos os módulos

A sequência MVC completa está preparada, mas depende de atribuição tenant e de confirmação do esquema real. Mantém **todos** os gates `*_MVC_ENABLED=false` no `.env` — incluindo Membros, Cotas, Listas, Saídas, Fim de Ano, Relatórios, Utilizadores, Auditoria, Backups, Perfil e Configurações — até à Fase 6 e à aprovação dos testes correspondentes. As rotas devolvem 503 antes de consultar dados do módulo enquanto o gate está desligado; a ponte procedural continua fail-closed e não é fallback.

Antes de activar qualquer gate, obtém `SHOW CREATE TABLE` real, aprova/aplica a migração tenant necessária e passa testes de isolamento/IDOR, CSRF e consistência em staging. A Fase 5 não executou migrations nem alterou dados reais; não fazer essas operações com base em DDL inferido do código legado. O histórico financeiro de membros e a ferramenta de recálculo de referências antigas continuam deliberadamente bloqueados até haver fluxo de revisão e reversão seguro. Ver também `docs/fase-05-membros.md`.
