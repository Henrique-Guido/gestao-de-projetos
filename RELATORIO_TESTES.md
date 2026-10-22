# Relatório de Testes de Qualidade e Segurança — Publinet Gestão

**Aplicação:** `/var/www/html/publinet-gestao` (Publinet Gestão de Clientes)
**Data do teste:** 21/10/2026 (21:24 → 21:40, horário local)
**Executor:** QA automatizado (opencode) — testes HTTP reais contra o Apache em produção + testes unitários (CLI)
**Ambiente testado:** Apache 2.4.69 (Debian) + PHP 8.4.26 (mod_php/fpm) + MariaDB 11.8.6, `DocumentRoot=/var/www/html`, app em subdiretório `/publinet-gestao`
**URL base:** `http://localhost/publinet-gestao`
**Estado do banco ao final:** restaurado ao estado original (backup em `/tmp/opencode/backup/publinet_gestao_before.sql`)

---

## 1. Resumo executivo

| Severidade | Quantidade | IDs |
|---|---|---|
| 🔴 Alta | 5 | A-01 … A-05 |
| 🟠 Média | 9 | M-01 … M-09 |
| 🟡 Baixa | 10 | B-01 … B-10 |
| ⚪ Informativo | 4 | I-01 … I-04 |

**Nenhum teste encontrou SQL injection, XSS refletido/espelhado ou exposição de arquivo sensível.** O uso de *prepared statements* (`PDO::ATTR_EMULATE_PREPARES => false`) e de `htmlspecialchars()` nas views é consistente e funciona corretamente.

O problema central da aplicação é **robustência**: a ausência de uma camada de tratamento de erros/validação faz com que entradas malformadas (arrays, datas inválidas, strings longas) derrubem a aplicação com **HTTP 500 e tela em branco**, em vez de retornarem um erro controlado ao usuário. Somam-se a isso falhas de controle de acesso (sessão órfã, credenciais expostas sem permissão), um oráculo de SSRF/varredura de rede e ausência de headers de segurança.

> **Observação importante:** durante a execução dos testes o módulo **WordPress** (`WordpressController`, `Wordpress`, `app/Views/wordpress/`, rotas em `public/index.php`) foi **adicionado ao projeto** (21:35) por outra sessão de trabalho. Ele é um clone do módulo Blogs e reproduz exatamente os mesmos defeitos (A-04 coerção de tipos, M-02 revelação sem permissão, M-06 status 419→500 e M-08 logout sem CSRF). Os testes desse módulo estão na seção 2 (`t9_wordpress.sh`) e os achados na seção 5.

---

## 2. Cobertura dos testes

| Suíte | Arquivo | Casos | Roteiro |
|---|---|---|---|
| Basico / HTTP / headers / arquivos | `tests/qa/t1_basico.sh` | 36 | anônimo, login, cookies, métodos HTTP, exposição de arquivos, path traversal |
| CRUD + CSRF + XSS + SQLi | `tests/qa/t2_crud.sh` | 44 | CRUD FTP/Blog, matriz de permissões, CSRF, XSS, SQLi, LIKE |
| Segurança (sessão, FK, admin, reveal) | `tests/qa/t3_seguranca.sh` | 37 | tipos de parâmetro, sessão órfã, FK, último admin, brute force, ids malformados |
| SSRF / status HTTP / roteamento | `tests/qa/t4_ssrf.sh` | 26 | SSRF, 419→500, último admin isolado, fixação de sessão, TRACE |
| Dados / coerção / validação | `tests/qa/t6_dados.sh` | 20 | senha NULL, registros incompletos, datas, checkbox vazio, e-mail, papel |
| Extras (último admin, timing, coerção) | `tests/qa/t7_extra.sh` | 11 | último admin isolado, enumeração de usuários, coerção por campo |
| Sessão órfã / FK / headers | `tests/qa/t8_orphan.sh` | 17 | sessão de usuário removido, FK em blogs, CSRF por rota, leitura sem permissão |
| Módulo WordPress | `tests/qa/t9_wordpress.sh` | 16 | módulo WordPress (adicionado durante a sessão) |
| Testes unitários (CLI) | `tests/qa/t5_unit.php` | 32 | Crypto, status de reunião, CSRF, `Controller::input`, oráculo FTP, `Auth` |

**Total: 239 verificações.** As falhas de suíte (19 no total) se dividem em **14 expectativas incorretas do próprio roteiro** (erro de harness, corrigidas/reconfirmadas em execução posterior) e **5 falhas que expõem defeitos reais do aplicativo**, todas reproduzidas de forma independente: `T22.3`/`T28.1`/`T56.10` (419→500), `T34.2` (reveal com senha `NULL` → 500), `T12.3`/`T12.4`/`T12.6`/`T12.8`/`T48` (erros 500 por tipo de parâmetro e por data inválida, detectadas no log do servidor).

---

## 3. Defeitos de severidade ALTA

### A-01 — Sessão órfã: usuário removido do banco mantém acesso total aos dados
**Arquivos:** `app/Core/Auth.php:33-36` (`check()`), `app/Core/Auth.php:43-54` (`user()`)
**Categoria:** Controle de acesso / sessão

`Auth::check()` valida apenas a presença de `$_SESSION['user_id']`, sem confirmar que o usuário ainda existe no banco. Ao remover o usuário, a sessão continua válida e com acesso de leitura a **todos** os módulos, incluindo a revelação de senhas em texto puro.

**Reprodução:**
```
1. login como qa_ghost2 (usuário comum)
2. DELETE FROM users WHERE id=<id do qa_ghost2>;
3. GET /ftp      -> 200  (continua autenticado)
4. GET /clientes -> 200
5. GET /blogs    -> 200
6. POST /ftp/1/reveal -> 200 {"senha":"SenhaFtp#1"}   <-- credencial em claro
```
**Observação:** as *escritas* são bloqueadas (porque `Auth::can()` lê `self::user()[$perm]`, que retorna `null` para usuário inexistente), mas a *leitura* de dados e credenciais permanece.

**Recomendação:** em `Auth::check()` (ou em `user()`), invalidar a sessão quando `User::findById()` retornar `null`:
```php
public static function check(): bool
{
    if (!isset($_SESSION['user_id'])) { return false; }
    if (self::user() === null) { self::logout(); return false; }
    return true;
}
```

---

### A-02 — Excluir usuário com registros vinculados causa HTTP 500
**Arquivos:** `app/Models/User.php:88-92` (`User::delete`), `app/Controllers/UserController.php:148`
**Categoria:** Bug funcional / integridade referencial

As tabelas `ftp_accounts`, `blogs` e `wordpress` têm `FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT`. A exclusão de um usuário que já criou clientes/blogs **viola a FK** e a `PDOException` não é tratada:

```
HTTP/1.1 500 Internal Server Error
PHP Fatal error: Uncaught PDOException: SQLSTATE[23000]: Integrity constraint violation: 1451
Cannot delete or update a parent row: a foreign key constraint fails
(`publinet_gestao`.`blogs`, CONSTRAINT `fk_blogs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`))
in /var/www/html/publinet-gestao/app/Models/User.php:91
```
**Impacto:** o administrador não consegue excluir usuários que criaram registros (situação comum); recebe tela em branco em vez de mensagem explicativa, e o erro polui o log do servidor.

**Recomendação:** antes do `DELETE`, verificar dependências e retornar mensagem amigável (ou usar `ON DELETE SET NULL` + `user_id` nullable).

---

### A-03 — Nenhum tratamento global de exceções: qualquer erro vira 500 (tela em branco)
**Arquivos:** `public/index.php` (sem `try/catch` / `set_exception_handler`), `app/Core/Controller.php:39-45`
**Categoria:** Robustez

`public/index.php` não registra handler de exceção. Com `APP_DEBUG=false` (produção), `display_errors=0` → o usuário recebe **HTTP 500 com corpo vazio** e nenhum feedback. Todos os erros abaixo (A-02, A-04, A-05, M-01) aparecem para o usuário final como "página em branco".

**Recomendação:** registrar `set_exception_handler` em `public/index.php` que registre em log e renderize uma página 500 amigável; considerar `ErrorController::serverError()`.

---

### A-04 — Coerção de tipos: parâmetros `array` causam HTTP 500 ou gravam dados corrompidos
**Arquivos:** `app/Core/Controller.php:47-52` (`input()`), `app/Models/FtpAccount.php:58`, `app/Models/Blog.php:48`, `app/Models/Wordpress.php:219`, `app/Core/Auth.php:11`
**Categoria:** Bug funcional / validação de entrada

`Controller::input()` só aplica `trim()` quando o valor é string; arrays passam intactos. Consequências observadas:

| Requisição | Resultado | Detalhe |
|---|---|---|
| `POST /login` com `name[]=x` | **500** | `TypeError: Auth::attempt(): Argument #1 ($name) must be of type string, array given` |
| `POST /login` com `password[]=x` | **500** | idem para `$password` |
| `POST /blogs` ou `/wordpress` com `senha[]=x` | **500** | `TypeError: Crypto::encrypt(): Argument #1 must be of type string, array given` |
| `POST /ftp` com `ultima_reuniao[]=x` ou `proxima_reuniao[]=x` | **500** | `Array to string conversion` + erro de SQL |
| `POST /ftp` com `cliente[]=x` | 302 | grava o **literal "Array"** no banco (dado corrompido) |
| `POST /ftp` com `senha[]=x` | 302 | grava a **senha "Array"** cifrada |

**Reprodução:**
```bash
curl -d '_token=<TOKEN>&name[]=x&password=y' http://localhost/publinet-gestao/login   # 500
curl -d '_token=<TOKEN>&cliente[]=x&host=y' http://localhost/publinet-gestao/ftp      # grava "Array"
```

**Recomendação:** normalizar no `input()`:
```php
protected function input(string $key, mixed $default = null): mixed
{
    $value = $_POST[$key] ?? $default;
    if (is_array($value)) { return $default; }        // ou lance 422
    return is_string($value) ? trim($value) : $value;
}
```

---

### A-05 — Campos de data e strings longas não validados causam HTTP 500
**Arquivos:** `app/Models/FtpAccount.php:65, 99, 114`, `app/Controllers/FtpAccountController.php:165-188` (validate), `app/Controllers/ClienteController.php:77-86`
**Categoria:** Bug funcional / validação de entrada

Não há validação de formato de data nem de comprimento de texto. Com `sql_mode=STRICT_TRANS_TABLES` (padrão do MariaDB 11), o banco rejeita a gravação e a exceção sobe:

```
HTTP 500
PDOException: SQLSTATE[22007]: Invalid datetime format: 1292 Incorrect date value:
'nao-e-data' for column `publinet_gestao`.`ftp_accounts`.`ultima_reuniao`
PDOException: SQLSTATE[22001]: String data, right truncated: 1406 Data too long for
column 'cliente' at row 1
```
Reproduzido em: `POST /ftp` (data inválida), `POST /ftp/{id}` (data inválida), `POST /clientes/{id}` (data inválida), `POST /ftp` (cliente com 5.000 caracteres).

**Observação:** o campo `ultima_reuniao` é `DATE`; o formulário usa `<input type="date">`, mas a API aceita qualquer string via `curl`.

**Recomendação:** validar com `DateTime::createFromFormat('Y-m-d', $v)` antes de gravar, limitar o tamanho (≤150 para `cliente`) e converter o erro em mensagem de validação.

---

## 4. Defeitos de severidade MÉDIA

### M-01 — SSRF / oráculo de varredura de portas internas em `POST /ftp/{id}/test`
**Arquivos:** `app/Controllers/FtpAccountController.php:143-163`, `app/Models/FtpAccount.php:132-176`
**Categoria:** SSRF (Server-Side Request Forgery) / reconhecimento de rede

O endpoint permite que **qualquer usuário autenticado, inclusive sem nenhuma permissão**, faça o servidor conectar a um host arbitrário (porta 21 fixa) e devolv mensagens distintas conforme o resultado:

| Situação | Resposta JSON |
|---|---|
| Porta fechada | `{"ok":false,"message":"Não foi possível conectar ao host \"X\" na porta 21..."}` |
| Porta aberta (login recusado) | `{"ok":false,"message":"Usuário ou senha incorretos."}` |

Isso permite enumerar hosts internos/serviços FTP na rede do servidor (e em `127.0.0.1`, `10.x`, `192.168.x`, `169.254.169.254`). O `host` também aceita URL (`ftp://host/x`), sendo normalizado por `parse_url`.

**Reprodução:**
```
POST /ftp/9/test  (host = 127.0.0.1)   -> "Não foi possível conectar ao host \"127.0.0.1\" na porta 21"
POST /ftp/9/test  (host = 10.255.255.1)-> "Não foi possível conectar ao host \"10.255.255.1\" na porta 21"
```
Com um listener FTP na porta 2121, o modelo retorna "Usuário ou senha incorretos." (confirmado em `tests/qa/t5_unit.php`, U05.1), provando a diferença de resposta.

**Recomendação:** (1) exigir `pode_editar_clientes` no endpoint `test`; (2) implementar allow-list de hosts/domínios; (3) bloquear faixas privadas/reservadas (127/8, 10/8, 172.16/12, 192.168/16, 169.254/16, ::1, fc00::/7); (4) uniformizar a mensagem de erro.

---

### M-02 — Credenciais de FTP/Blog/WordPress são reveláveis sem nenhuma permissão
**Arquivos:** `app/Controllers/FtpAccountController.php:126-141`, `app/Controllers/BlogController.php:120-135`, `app/Controllers/WordpressController.php:120-135`
**Categoria:** Controle de acesso / menor privilégio

Os endpoints `reveal` exigem apenas estar autenticado. O sistema possui as flags `pode_criar_clientes` e `pode_editar_clientes`, mas elas **não protegem a leitura de senhas**:

```
POST /ftp/1/reveal     (usuário sem nenhuma permissão) -> 200 {"senha":"SenhaFtp#1"}
POST /blogs/2/reveal   (usuário sem nenhuma permissão) -> 200 {"senha":"BlogSenha#1"}
POST /wordpress/3/reveal(usuário sem nenhuma permissão) -> 200 {"senha":"WpSenha#1"}
```
Se a intenção do negócio for que credenciais são acessíveis a todos os usuários internos, isso deve ser explícito e auditado; caso contrário, falta uma permissão específica (ex.: `pode_ver_senhas`).

---

### M-03 — Bypass de CSRF quando a sessão ainda não possui token
**Arquivos:** `app/Core/Csrf.php:21-24`
**Categoria:** CSRF

```php
public static function verify(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
```
Quando a sessão **não** possui `csrf_token`, o valor comparado é `''`. Como `hash_equals('', '')` retorna **true**, um POST com `_token=` (vazio) é aceito.

Unit test `U03.1` confirma: `Csrf::verify('')` → `true` em sessão sem token.

**Impacto prático atual:** baixo, porque qualquer página renderizada cria o token (`Csrf::field()` no layout e formulários). Porém, é uma defesa que falha silenciosamente e passa a valer se o token não for gerado em alguma página nova, ou se um fluxo for criado com sessão "virgem" (ex.: login via link).

**Recomendação:**
```php
public static function verify(?string $token): bool
{
    $expected = $_SESSION['csrf_token'] ?? '';
    return $expected !== '' && is_string($token) && hash_equals($expected, $token);
}
```

---

### M-04 — Enumeração de usuários por tempo de resposta no login
**Arquivos:** `app/Core/Auth.php:11-24`
**Categoria:** Vazamento de informação

Quando o usuário não existe, `Auth::attempt()` retorna imediatamente (sem `bcrypt`); quando existe, executa `password_verify()` (≈0,35 s). Medição real:

| Requisição | Tempo |
|---|---|
| `name=usuario_inexistente_qa` | **0,0033 s** |
| `name=qa_admin` (senha errada) | **0,3595 s** / 0,3579 s |

Diferença de ~100x permite enumerar os nomes de usuário válidos.

**Recomendação:** sempre executar um `password_verify` contra um hash "dummy" quando o usuário não for encontrado.

---

### M-05 — Ausência de rate limiting / bloqueio no login
**Arquivos:** `app/Controllers/AuthController.php:21-42`
**Categoria:** Força bruta

25 tentativas de login com senhas erradas foram executadas em sequência: **nenhuma foi bloqueada** e nenhuma delay foi aplicada. Não há contador, back-off, CAPTCHA nem registro de tentativas.

**Recomendação:** implementar back-off exponencial por IP/nome e alertas para tentativas repetidas.

---

### M-06 — Código HTTP 419 (não padronizado) é convertido em 500 pelo Apache
**Arquivos:** `app/Core/Controller.php:39-45` (`json()`), `FtpAccountController.php:129, 146`, `BlogController.php:123`, `WordpressController.php:123`
**Categoria:** Bug funcional / observabilidade

O app usa `419` para "token inválido / sessão expirada". O Apache não conhece esse código e o converte em **500 Internal Server Error**:

| Código enviado pelo PHP | Resposta real no Apache | Resposta no servidor embutido do PHP |
|---|---|---|
| 418 | **500** | 418 |
| 419 | **500** | 419 |
| 400/401/403/404/422/429 | igual | igual |

Impacto: respostas de rotina ("sessão expirada") viram erro de servidor, poluem o `error.log`, disparam alertas de monitoramento e o front-end (que espera 419) não consegue diferenciar "sessão expirada" de "erro no servidor".

**Recomendação:** usar código padrão (ex.: `403 Forbidden` ou `422 Unprocessable Entity`) ou `419` + `ErrorDocument`/mapeamento no Apache.

---

### M-07 — Ausência de headers de segurança
**Arquivos:** `public/index.php`
**Categoria:** Configuração / hardening

Nenhum header de segurança é enviado (verificado em todas as respostas):

- `Content-Security-Policy` — **ausente** (risco de XSS/CSS injection)
- `X-Frame-Options` / `frame-ancestors` — **ausente** (clickjacking: as telas de exclusão usam `<dialog>`, mas o layout inteiro é embutível em iframe)
- `X-Content-Type-Options: nosniff` — **ausente**
- `Referrer-Policy` — **ausente**
- `Strict-Transport-Security` — **ausente** (app servido por HTTP)
- `Permissions-Policy` — **ausente**

**Recomendação:** adicionar via `.htaccess` ou em `public/index.php`.

---

### M-08 — Logout não valida token CSRF
**Arquivos:** `app/Controllers/AuthController.php:44-48`, rota em `public/index.php:35`
**Categoria:** CSRF

O formulário de logout envia `Csrf::field()`, mas o controller **não valida** o token. Um site externo pode forçar o logout do usuário (denial of service de sessão / irritação).

**Recomendação:** validar o token no `logout()`.

---

### M-09 — Senhas são aparadas (`trim`), corrompendo credenciais
**Arquivos:** `app/Core/Controller.php:47-52` (`input()`), `app/Models/FtpAccount.php:58`, `app/Models/User.php:45`
**Categoria:** Bug funcional

`Controller::input()` aplica `trim()` em **todos** os campos, inclusive senhas:

- `POST /ftp` com `senha="  abc  "` grava a senha `"abc"`;
- `POST /login` com senha `"  espaco123  "` → usuário **não consegue autenticar** (o `trim` remove os espaços antes do `password_verify`).

**Recomendação:** não aplicar `trim` em campos de senha (ou usar uma função de input específica, ex.: `inputRaw()`).

---

## 5. Defeitos de severidade BAIXA

| ID | Descrição | Arquivo | Evidência |
|---|---|---|---|
| **B-01** | Senha mínima de 8 caracteres é validada **apenas na criação** de usuário; na edição, senha de 1 caractere é aceita. | `app/Controllers/UserController.php:42` vs `:84-130` | `POST /usuarios/{id}` com `password=abc` → gravado com hash bcrypt válido |
| **B-02** | Checkbox enviado com **valor vazio** (`pode_criar_clientes=`) concede a permissão, pois a checagem é `!== null`. | `UserController.php:64`, `:124` | `input()` devolve `''` (string) → `'' !== null` → `true` |
| **B-03** | E-mail sem validação de formato (apenas "não vazio"). | `UserController::validate()` `:154-171` | `email=nao-e-um-email` aceito e gravado |
| **B-04** | Busca com `LIKE` não escapa os curingas `%` e `_`: o usuário pode forçar padrões e listar tudo (`.` →匹配). | `FtpAccount.php:26`, `Blog.php:20`, `Wordpress.php:193` | `GET /ftp?q=%` retorna todos os registros |
| **B-05** | Troca de senha / rebaixamento de admin **não invalida** as sessões já abertas do usuário afetado. | `app/Models/User.php:64-86` | Após trocar a senha, a sessão antiga continua `200` em `/ftp` |
| **B-06** | Exclusão de id inexistente retorna **"Acesso FTP removido."** (falso sucesso). | `FtpAccountController.php:120-123`, `BlogController.php:114-117` | `POST /ftp/999999/delete` → toast de sucesso |
| **B-07** | Padrões de rota não são escapados com `preg_quote()`, permitindo metacaracteres de regex em rotas futuras (hoje inerte). | `app/Core/Router.php:34` | — |
| **B-08** | `Controller::view()` usa `extract($data)`: chaves de dados podem sobrescrever `$appName`/`$appUrl` dentro da closure de renderização. | `app/Core/Controller.php:10, 24-27` | — |
| **B-09** | Listagens sem paginação: `SELECT *` sem `LIMIT` (todos os clientes, blogs e wordpress em memória). | `FtpAccount.php:29`, `Blog.php:23`, `Wordpress.php:194` | — |
| **B-10** | Cookie de sessão sem flag `Secure` quando a app está atrás de proxy TLS (não considera `X-Forwarded-Proto`). | `public/index.php:14-20` | `secure` depende apenas de `$_SERVER['HTTPS']` |

---

## 6. Informativos (sem ação imediata)

| ID | Item | Detalhe |
|---|---|---|
| **I-01** | **Módulo WordPress adicionado durante a sessão de testes** (21:35) | É um clone do módulo Blogs: repete A-04, M-02, M-06, M-07 e o bypass de CSRF. A tabela `wordpress` foi criada no banco **sem** a cláusula `ON DELETE RESTRICT` presente em `database/schema.sql` (drift de schema). |
| **I-02** | Drift entre `database/schema.sql` e o banco real | `SHOW CREATE TABLE blogs` e `wordpress` mostram `FOREIGN KEY (user_id) REFERENCES users (id)` **sem** `ON DELETE RESTRICT`, embora o `schema.sql` declare a cláusula. O comportamento é o mesmo (RESTRICT é o default), mas uma instalação nova diverge da atual. |
| **I-03** | Exposição de arquivos sensibles: **OK** | `.env` → 403, `composer.json/lock` → 403, `database/`, `app/`, `vendor/`, `README.md` → 404 (o *rewrite* do `.htaccess` bloqueia). Path traversal → 404/403. |
| **I-04** | `.env` usa `DB_USERNAME=root` / `DB_PASSWORD=root` | A aplicação conecta no MySQL como **root**; um comprometimento da app dá acesso total ao banco. Recomenda-se um usuário com privilégios mínimos. |

---

## 7. Testes que passaram sem achados relevantes

Os itens abaixo foram testados **e passaram**, sendo mantidos como regressão:

- **SQL injection:** payloads em `cliente`, `usuario`, `host`, `q` (busca) e no `id` da URL — todos parameterized, sem vazamento ou erro. Nenhum registro duplicado, nenhuma coluna exposta.
- **XSS:** `<script>alert(1)</script>` e `"><img src=x onerror=alert(1)>` armazenados e exibidos — sempre escapados por `htmlspecialchars()`; payload na query string da busca também escapado. Nenhum `<script>` executável no HTML.
- **CSRF:** todas as rotas POST (`/ftp`, `/blogs`, `/wordpress`, `/clientes/{id}`, `/usuarios`, deletes) rejeitam requisição sem token ou com token inválido — **exceto** quando o token é vazio e a sessão não possui token (M-03) e o logout, que não valida token (M-08).
- **Matriz de permissões:** `pode_criar_clientes` e `pode_editar_clientes` são respeitadas em `create/store/edit/update` de FTP, Blogs e WordPress; `/usuarios` é exclusivo de admin; exclusão de FTP/Blog/WordPress é exclusiva de admin. (Exceções: M-02 e A-01.)
- **Proteção do último administrador:** rebaixar ou excluir o único admin é bloqueado (testado em cenário isolado, `T44b`).
- **Criptografia:** AES-256-GCM com IV aleatório, round-trip correto, `decrypt` retorna `null` para base64 inválido, dados curtos ou adulterados (tag GCM verificada).
- **Status de reunião:** limites `ok`/2, `moderado`/4, `critico`/`>4` conferem exatamente nas fronteiras (0, 1, 2, 3, 4, 5 meses, sem reunião e data futura).
- **Sessão:** `session_regenerate_id(true)` no login (fixação de sessão prevenida), cookie com `HttpOnly` e `SameSite=Lax`, respostas com `Cache-Control: no-store`.
- **Exposição de arquivos e path traversal:** nada exposto (ver I-03).
- **Métodos HTTP:** `PUT/DELETE/PATCH/OPTIONS/PROPFIND` → 404; `TRACE` → 405 (tratado pelo Apache).
- **Erros de domínio:** 404 para recursos inexistentes em todas as listagens, com redirecionamento e mensagem amigável.

---

## 8. Recomendações priorizadas

1. **Imediato (quebra de funcionalidade):** implementar tratamento global de exceções (A-03) + validação de tipos/datas/tamanhos (A-04, A-05) + corrigir a exclusão de usuários com vínculos (A-02) + `reveal` com senha `NULL` (M-01 do A-04/M-07: `FtpAccount::decryptPassword(null)` → `TypeError` → 500, `app/Models/FtpAccount.php:123`).
2. **Alto (segurança):** invalidar sessão de usuário removido (A-01); restringir `reveal` e `test` por permissão (M-02, M-01); corrigir `Csrf::verify` (M-03); adicionar rate limiting (M-05) e hash dummy no login (M-04).
3. **Médio (operacional):** substituir o 419 por código padrão (M-06); adicionar headers de segurança (M-07); validar CSRF no logout (M-08); remover `trim` de senhas (M-09).
4. **Baixo:** itens B-01 … B-10 e a revisão do schema (I-01, I-02) e do `.env` (I-04).

---

## 9. Como reproduzir

```bash
# ambiente
cd /var/www/html/publinet-gestao
composer install
mysql -u root -p < database/schema.sql

# criar usuários de teste
php database/create-user.php "qa_admin" "qa_admin@publinet.test" "Admin@Test123"
mysql -u root -p -e "UPDATE users SET role='admin' WHERE name='qa_admin'"

# executar
bash tests/qa/t1_basico.sh
bash tests/qa/t2_crud.sh
bash tests/qa/t3_seguranca.sh
bash tests/qa/t4_ssrf.sh
php  tests/qa/t5_unit.php
bash tests/qa/t6_dados.sh
bash tests/qa/t7_extra.sh
bash tests/qa/t8_orphan.sh
bash tests/qa/t9_wordpress.sh
```

Os scripts gravam o resultado de cada verificação em `/tmp/opencode/results.txt` e imprimem `PASS`/`FAIL`/`INFO` no console.
**Os scripts de teste gravam dados no banco de execução** — usar apenas em ambiente de teste.

---

## 10. Observações finais

- O banco de dados foi **restaurado ao estado original** ao final dos testes (backup: `/tmp/opencode/backup/publinet_gestao_before.sql`). Única diferença: o `updated_at` do usuário `admin` (id 1) foi atualizado pela manipulação de papel durante o teste T-44b, e o `AUTO_INCREMENT` das tabelas avançou. O registro `wordpress` do cliente "Cedusp" (criado em outra sessão, durante os testes) foi **preservado**.
- Nenhum arquivo do projeto foi modificado durante a auditoria; este relatório e a pasta `tests/qa/` são as únicas adições.
