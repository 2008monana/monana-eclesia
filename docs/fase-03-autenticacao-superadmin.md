# Fase 3 — Autenticação, provisionamento e Superadmin

**Estado:** implementação concluída para revisão; **não validada em runtime nem numa base MySQL** neste ambiente.
**Âmbito:** acesso SaaS, criação e administração de igrejas/planos, email de activação/redefinição e segurança da sessão. A Fase 4/5/6 não foi iniciada.

## Implementado

- Login MVC em português, mensagens que não revelam se a conta existe, contagem de tentativas malformadas e limite de 5 falhas por email / 20 por IP em 15 minutos.
- Hash Argon2id quando disponível e bcrypt como fallback PHP 7.4. As senhas novas exigem pelo menos 12 caracteres; no fallback bcrypt o limite é 72 bytes para impedir truncamento silencioso. Sessões não guardam `senha_hash`.
- Sessão HttpOnly, SameSite=Lax, Secure quando HTTPS, regeneração do ID, expiração por inactividade e registo server-side em `sessoes` com hash do ID, versão de sessão e revogação.
- CSRF para todos os métodos que alteram dados, via token de sessão. Logout e terminação de sessões são POST. As páginas antigas injectadas pela ponte recebem token em formulários/AJAX.
- Recuperação com respostas genéricas, token de redefinição de uso único (60 min) e token de activação (24 h); a base guarda apenas SHA-256. Payload do link na fila fica cifrado com AES-256-GCM, não em texto simples.
- Permissões por papel e módulo. O tenant é relido da conta autenticada e vem da sessão; nenhum controller aceita `igreja_id` do cliente para determinar acesso. Superadmin não tem tenant e as rotas de plataforma estão sob `/superadmin`.
- Provisionamento transaccional: igreja pendente, configuração vazia por tenant, administrador inactivo, permissões, token e mensagem cifrada na fila. O envio SMTP ocorre depois do commit; falha de email não desfaz a igreja.
- CRUD de planos; apagamento permitido só quando não há igrejas associadas. Nenhum preço/limite comercial foi inventado. Migração cria apenas `Plano por configurar`, inactivo, a zero, se o catálogo estiver vazio.
- Gestão de igrejas: leitura/criação/edição, suspensão/reactivação e **encerramento lógico** (`encerrada`) sem apagar dados. Suspensão/encerramento são transaccionais, revogam sessões e incrementam a versão; o encerramento bloqueia acesso e é terminal no painel, preservando histórico e auditoria.
- Fila persistente com transportes PHPMailer/SMTP e `log` (não envia), erro de entrega sem links/tokens, estado/tentativas e repetição manual pelo Superadmin para links ainda válidos. Link de activação expirado pode ser renovado no reenvio se a igreja continuar pendente.
- Ecrãs de autenticação, igreja e plataforma usam o tema existente por `readfile()` inline de `assets/css/monana-theme.css`; moeda em Kz com `1.250,00` e datas `dd/mm/aaaa`.
- O painel da igreja não consulta tabelas de negócio ainda sem `igreja_id`. A `LegacyBridge` devolve 503 nesses módulos em vez de os abrir sem isolamento.

## Rotas novas principais

- `/login`, `/sair` (POST), `/recuperar-senha`, `/resetar-senha`, `/activar-conta`, `/trocar-senha` e `/sessoes/terminar-todas` (POST).
- `/instalacao/superadmin` cria uma única conta inicial. A rota deixa de estar disponível após o registo na tabela `platform_bootstrap`.
- `/painel` é um painel temporário e seguro da igreja.
- `/superadmin`, `/superadmin/igrejas`, `/superadmin/planos` e os formulários associados.

Todas as mutações destas rotas passam pelo Router e por CSRF. Os controllers chamam Services; SQL está em repositories com prepared statements PDO.

## Migração 004 e ordem

A migração `database/migrations/004_autenticacao_superadmin.sql` depende de `001` e `003`; a `002` só se aplica à base procedural depois de backup e validação do motor de `cotas_diarias`. A `004` é idempotente para o esquema documentado: cria as tabelas novas, acrescenta colunas/índices em falta, acrescenta o estado terminal `encerrada` e não substitui nem apaga dados existentes. Consulta `database/migrations/README.md` antes de a aplicar.

Antes da `004` numa base existente, resolve emails duplicados e confirma a estrutura de `usuarios`/`logs_auditoria`; o SQL espera as colunas-base reconstruídas em `docs/fase-01-analise-e-mer.md`. Migrações **não foram executadas**. Não são adicionadas colunas tenant às seis tabelas de negócio; isso fica para a Fase 6, depois do `SHOW CREATE TABLE` real.

## Configuração e FTP

1. Num ambiente local, copia `.env.example` para `.env`. Preenche `APP_URL`, `APP_BASE_PATH` quando necessário e credenciais MySQL/MariaDB. Em produção, usa HTTPS e `APP_DEBUG=false`.
2. Gera um `APP_ENCRYPTION_KEY` aleatório de **32 bytes / 64 caracteres hexadecimais**. Gera separadamente `SUPERADMIN_BOOTSTRAP_TOKEN` (pelo menos 32 bytes); nunca reutilizes a chave de cifragem.
3. Com PHP 7.4+ e Composer, executa `composer install --no-dev --optimize-autoloader` no computador local; envia o projecto e `vendor/` por FTP. Não é necessário Composer/SSH no alojamento.
4. Aplica as migrações primeiro numa cópia/staging da mesma versão de MySQL/MariaDB. Segue a sequência e as instruções de backup/reversão no README da pasta.
5. Abre `/instalacao/superadmin`, cria as credenciais e remove logo `SUPERADMIN_BOOTSTRAP_TOKEN` do `.env`.
6. Configura o SMTP TLS/SSL de um remetente verificado em `MAIL_*`. `MAIL_MAILER=log` não envia; deixa as mensagens com falha para reenvio posterior no painel. Não coloques segredos no Git, email ou chat.
7. Confirma HTTPS, bloqueio HTTP de `.env`, `/app`, `/config`, `/database`, `/tests`, `vendor/` e logs, cookies Secure e envio/recepção real em staging.

Dependências Composer permitidas nesta etapa: PHPMailer e Dompdf já existente. Não há framework.

## Verificações e limites

- `tests/phase3_smoke.php` foi criado para exercitar Validator, hash, CSRF, cifra, tenant da sessão e RBAC **sem MySQL**.
- Tree-sitter-php analisou estaticamente 79 ficheiros PHP de `app/`, `routes/`, `config/`, `tests/` e `public/`: zero erros de árvore sintáctica. `git diff --check` também passou. Este parser **não substitui** `php -l` nem execução PHP.
- Neste ambiente não está disponível PHP CLI/Composer/MySQL nem DDL ou credenciais de produção. Portanto, o smoke test, `php -l`, integração HTTP, migrações, SMTP, transacções e compatibilidade efectiva com PHP 7.4 **não foram verificados**. Nenhuma migração foi executada.
- O SQL existente foi reconstruído por leitura do código, não por `SHOW CREATE TABLE`. Não usar estas migrações em produção até fazer backup restaurável, conferir a pré-condição e validar numa cópia.

## O que Renato deve fornecer/configurar antes de produção/Fase 6

- Para aplicar a migração 004 numa base já existente: a estrutura real de `usuarios` e `logs_auditoria`, além da confirmação de índices/email duplicado. Não é preciso enviar senhas de BD neste chat; preencher no ambiente de deploy.
- Para Fase 6: `SHOW CREATE TABLE` actual de cada tabela de negócio (`membros`, `cotas_diarias`, `contribuicao_fim_ano`, `saidas`, `usuarios`, `logs_auditoria`), versão MySQL/MariaDB, e mapeamento aprovado dos utilizadores/igreja histórica. Não inferir nem mover dados de produção antes disso.
- Valores comerciais aprovados para planos (nome, limites, preço em Kz e estado); o catálogo actual não activa nem precifica um plano por conta própria.
- Dados SMTP oficiais da conta, incluindo host, porta, TLS/SSL, remetente verificado e credenciais introduzidas directamente no `.env`/painel do alojamento. Não partilhar a senha SMTP em chat.
- Domínio/caminho final e confirmação de suporte do alojamento para PHP 7.4+, PDO MySQL, OpenSSL e conexões SMTP de saída.

**Próxima fase:** Fase 4, sem migrar módulos de negócio até cumprir a sequência definida. A actual Fase 3 termina neste ponto.
