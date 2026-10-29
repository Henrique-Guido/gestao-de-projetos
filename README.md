# Publinet - Gestão de Acessos e Clientes

Sistema interno para cadastro de clientes, acompanhamento de reuniões e guarda
de acessos (FTP, blogs, WordPress e lojas virtuais), com controle de usuários e
permissões. Feito em PHP puro com arquitetura MVC.

## Funcionalidades

| Tela | Rota | Conteúdo |
|---|---|---|
| Clientes | `/clientes` | Cliente, site, última e próxima reunião, status calculado (ok / moderado / crítico) |
| Acessos FTP | `/ftp` | Cliente, host, usuário e senha, com teste de conexão FTP |
| Blogs | `/blogs` | Cliente, usuário e senha |
| WordPress | `/wordpress` | Cliente, usuário e senha |
| E-commerce | `/ecommerce` | Cliente, link da loja, e-mail, usuário, código e senha |
| Usuários | `/usuarios` | Gestão de usuários e permissões (somente admin) |
| Exportar Excel | `/exportar/excel` | Planilha `.xlsx` com todos os dados (somente admin) |

## Requisitos

- PHP 8.0+ com as extensões `pdo_mysql`, `openssl`, `mbstring` e `zip`. A extensão `ftp`
  só é usada pelo botão "Testar conexão".
- MySQL ou MariaDB.
- Apache com `mod_rewrite`.
- Composer, usado apenas para gerar o autoload PSR-4. O projeto não tem dependências externas.

## Estrutura

```
app/
  Config/config.php   lê o .env e define valores padrão
  Controllers/        um controller por tela + Auth, Export e Error
  Core/               router, controller base, auth, csrf, crypto, database, env,
                      flash e XlsxWriter (gerador de .xlsx sem dependências)
  Models/             acesso ao banco (PDO, métodos estáticos)
  Views/              templates PHP (layouts/, errors/ e uma pasta por tela)
database/
  schema.sql          cria o banco e todas as tabelas
  create-user.php     cria um usuário de login via CLI
public/               document root: front controller (index.php) e assets
```

As rotas ficam todas em `public/index.php`.

## Configuração (`.env`)

O ambiente é configurado pelo arquivo `.env`, que fica fora do git (cada
servidor tem o seu). O `app/Config/config.php` apenas lê esses valores e define
padrões. O modelo versionado é o `.env.example`.

| Variável | Descrição |
|---|---|
| `APP_NAME` | Nome exibido no título das páginas |
| `APP_URL` | URL base do sistema, sem barra no final (ex.: `http://localhost/publinet-gestao`). É usada em todos os links e redirecionamentos, inclusive para descobrir o subdiretório |
| `APP_ENV` | `production` ou `development` (reservado; ainda não altera o comportamento) |
| `APP_DEBUG` | `true` mostra o erro completo na tela; `false` mostra uma página 500 genérica e registra o erro no log |
| `APP_TIMEZONE` | Fuso horário (padrão `America/Sao_Paulo`). Afeta datas exibidas e o cálculo de status |
| `APP_KEY` | Chave de 64 caracteres hex usada para cifrar as senhas guardadas (AES-256-GCM) |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexão com o banco |

> **Atenção com a `APP_KEY`:** trocar a chave torna ilegíveis todas as senhas já
> cadastradas. Guarde-a com segurança e use a mesma chave sempre que o banco for
> reaproveitado.

## Instalação

1. Instale o autoload:
   ```
   composer install
   ```

2. Crie o `.env` a partir do modelo e gere a `APP_KEY`:
   ```
   cp .env.example .env
   php -r "echo bin2hex(random_bytes(32));"
   ```

3. Crie o banco e as tabelas:
   ```
   mysql -u root -p < database/schema.sql
   ```

4. Crie o primeiro usuário e promova-o a administrador (o script sempre cria
   usuários comuns, sem permissões):
   ```
   php database/create-user.php "Seu Nome" "seu@email.com" "senha-forte"
   ```
   ```sql
   UPDATE users SET role = 'admin' WHERE name = 'Seu Nome';
   ```
   A partir daí, os demais usuários são criados pela tela **Usuários**.

5. Aponte o servidor web para `public/` (recomendado). Se o `DocumentRoot` do
   Apache apontar para a pasta pai (ex.: `/var/www/html`), o `.htaccess` da raiz
   reescreve as requisições para `public/` automaticamente.

6. Acesse a `APP_URL` e entre com **nome de usuário** e senha.

Para desenvolvimento, use `APP_DEBUG=true` no `.env` local. Não há suíte de
testes automatizados; para checar a sintaxe de um arquivo, use
`php -l caminho/arquivo.php`.

### Alterações de banco

Não há migrations: o `database/schema.sql` é a fonte única do schema. Ao criar
ou alterar tabelas, atualize o arquivo e aplique o SQL correspondente
manualmente nos bancos já existentes.

## Usuários e permissões

- **Papel (`role`)**: `admin` ou `user`. Administradores têm acesso total: tela
  de Usuários, exclusão de registros, ajuste dos critérios de status e
  exportação para Excel.
- **`pode_criar_clientes` / `pode_editar_clientes`**: permitem a usuários comuns
  criar ou editar registros em Clientes, FTP, Blogs, WordPress e E-commerce sem
  torná-los admin.
- Qualquer usuário logado pode visualizar as listagens e revelar/copiar senhas.
- Não é possível remover nem rebaixar o último administrador, nem excluir a
  própria conta. Usuários que já cadastraram registros não podem ser excluídos.

## Clientes e status de reunião

Clientes e Acessos FTP compartilham a mesma tabela (`ftp_accounts`): cada
cliente pode ou não ter dados de FTP. A tela de Clientes calcula o status a
partir dos meses desde a última reunião:

- **Ok**: até X meses, ou reunião com data futura
- **Moderado**: até Y meses
- **Crítico**: mais de Y meses, ou nenhuma reunião registrada

O padrão é X = 2 e Y = 4. Administradores podem mudar esses limites em
**Ajustar critérios**, na própria tela de Clientes. Os valores ficam na tabela
`configuracoes` e valem para todos os usuários. A listagem permite buscar por
nome e filtrar por status.

### Verificar tags (Google Analytics e Tag Manager)

Clientes com site cadastrado têm o botão **Verificar tags**. O servidor acessa
a página inicial do site e informa se encontrou o **Google Tag Manager**
(`GTM-…`) e o **Google Analytics 4** (`G-…`). Ele também abre o contêiner do
Tag Manager, porque o GA4 configurado lá dentro não aparece no HTML. Se o site
ainda tiver o Universal Analytics (`UA-…`), versão antiga e descontinuada, isso
também é avisado.

Limitações:
- A verificação lê o HTML da página. Tags inseridas por JavaScript depois que a
  página carrega (comum em sites feitos em React/Next.js) ou só após o aceite de
  cookies podem não ser detectadas.
- Sites com proteção contra robôs (ex.: Cloudflare) podem recusar o acesso.
- Por segurança, só são acessados endereços públicos nas portas 80 e 443.

## Acessos (FTP, Blogs, WordPress e E-commerce)

- As senhas ficam cifradas no banco e nunca vão para o HTML. Elas são buscadas
  sob demanda pelos botões **Mostrar** e **Copiar**.
- Na edição, deixar a senha em branco mantém a atual.
- Em **Acessos FTP**, host, usuário e senha são opcionais, e o botão **Testar
  conexão** tenta logar no servidor FTP (porta 21).
- Em **E-commerce**, o link da loja recebe `https://` automaticamente se for
  digitado sem protocolo.

## Exportação para Excel

O botão **Exportar Excel**, no menu lateral (somente admin), baixa uma planilha
`.xlsx` com as abas Resumo, Clientes, Acessos FTP, Blogs, WordPress e
E-commerce. Cada aba tem cor própria, cabeçalho fixo, filtros e, em Clientes, o
status destacado em cores.

**A planilha inclui todas as senhas em texto puro**: trate o arquivo como
confidencial.

## Segurança

- Login por nome de usuário com senha em bcrypt. O tempo de resposta é o mesmo
  para usuários existentes e inexistentes.
- Senhas de acessos cifradas com AES-256-GCM usando a `APP_KEY`.
- Todas as rotas exigem sessão autenticada, e a sessão é encerrada se o usuário
  for removido do banco. Ações de criar, editar e excluir são checadas contra o
  papel e as permissões.
- Token CSRF em todos os formulários e requisições POST, incluindo o logout.
- Entradas validadas (tipos, tamanhos, datas, e-mails e URLs) e consultas com
  *prepared statements*.
- Headers de segurança (CSP, `X-Frame-Options`, `X-Content-Type-Options` e
  `Referrer-Policy`) e cookie de sessão `HttpOnly` / `SameSite=Lax`.
- Erros inesperados exibem uma página 500 genérica e são registrados no log.
- `.env`, `composer.json` e `composer.lock` são bloqueados via `.htaccess`.

## Deploy em produção

- [ ] `.env` com `APP_DEBUG=false` e `APP_URL` com o endereço real
- [ ] `APP_KEY` definida (a mesma do banco de origem, se os dados forem migrados)
- [ ] Usuário do MySQL com acesso apenas ao banco do sistema (não usar `root`)
- [ ] `database/schema.sql` aplicado
- [ ] HTTPS configurado no servidor (o cookie de sessão passa a ser `Secure` automaticamente)
- [ ] Primeiro administrador criado
