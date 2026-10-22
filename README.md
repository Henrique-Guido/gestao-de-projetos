# Publinet - Gestão de Acessos e Clientes

Sistema interno para cadastro de clientes, controle de reuniões e consulta de
acessos FTP (host, usuário e senha), com controle de usuários e permissões,
em PHP puro com arquitetura MVC.

## Stack

- PHP 8.0+ (sem framework, autoload PSR-4 via Composer)
- MySQL / MariaDB (PDO)
- Login por **nome de usuário** + senha em **bcrypt** (`password_hash`/`password_verify`)
- Senha de FTP armazenada com criptografia reversível **AES-256-GCM** (precisa ser
  recuperada em texto puro para uso no cliente FTP — por isso não usa bcrypt, que é
  one-way)

## Estrutura

```
app/
  Config/     configuração (lê o .env)
  Controllers/
    AuthController.php       login/logout
    FtpAccountController.php cadastro de acessos FTP
    ClienteController.php    listagem de clientes e status de reunião
    UserController.php       gestão de usuários (somente admin)
  Core/       router, controller base, auth, csrf, crypto, database
  Models/
  Views/
database/
  schema.sql        cria banco e tabelas
  create-user.php   cria um usuário de login via CLI
public/             document root da aplicação (front controller + assets)
```

## Instalação

1. Instale as dependências (apenas autoload):
   ```
   composer install
   ```

2. Copie `.env.example` para `.env` e configure o banco de dados e a `APP_KEY`:
   ```
   cp .env.example .env
   php -r "echo bin2hex(random_bytes(32));"   # gere a APP_KEY
   ```

3. Crie o banco e as tabelas:
   ```
   mysql -u root -p < database/schema.sql
   ```

4. Crie o primeiro usuário de acesso ao sistema:
   ```
   php database/create-user.php "Seu Nome" "seu@email.com" "senha-forte"
   ```
   O script sempre cria o usuário como `user` comum (sem permissões). Para ter o
   primeiro administrador, promova-o manualmente após a criação:
   ```sql
   UPDATE users SET role = 'admin' WHERE name = 'Seu Nome';
   ```
   A partir daí, novos usuários (e suas permissões/papel) podem ser criados pela
   tela **Usuários** (`/usuarios`), acessível apenas a administradores.

5. Aponte o servidor web para a pasta `public/` (recomendado), ou mantenha o
   `.htaccess` na raiz do projeto para reescrever automaticamente as requisições
   para `public/` (usado quando o `DocumentRoot` do Apache aponta para a pasta pai,
   como em `/var/www/html/publinet-gestao`).

6. Acesse `APP_URL` configurada no `.env` e faça login com **nome de usuário** e senha.

## Usuários e permissões

- **role**: `admin` ou `user`. Administradores têm acesso total (incluindo a tela
  de Usuários e a exclusão de clientes/acessos FTP).
- **pode_criar_clientes** / **pode_editar_clientes**: flags concedidas a usuários
  comuns para permitir criar ou editar clientes/acessos FTP sem torná-los admin.
- Não é possível remover nem rebaixar o último administrador do sistema, nem um
  usuário excluir a própria conta.

## Clientes e status de reunião

Cada cliente tem datas de última e próxima reunião. A tela de **Clientes**
(`/clientes`) calcula automaticamente um status a partir dos meses desde a
última reunião:

- **ok**: até 2 meses (ou sem reunião ainda passada)
- **moderado**: entre 2 e 4 meses
- **crítico**: mais de 4 meses, ou sem nenhuma reunião registrada

Os limites acima (2 e 4 meses) são o padrão. Administradores podem alterá-los
em **Ajustar critérios**, na própria tela de Clientes; os valores ficam na
tabela `configuracoes` e valem para todos os usuários.

A listagem permite buscar por nome e filtrar por status.

## Acessos FTP

A tela de **Acessos FTP** (`/ftp`) mantém host, usuário e senha por cliente
(campos opcionais, já que nem todo cliente possui FTP). A senha só é exibida
em texto puro sob demanda (botão "Mostrar"/"Copiar"), via requisição
autenticada e protegida por token CSRF.

## Exportação para Excel

Administradores têm o botão **Exportar Excel** no menu lateral (`/exportar/excel`).
Ele baixa uma planilha `.xlsx` com uma aba por tela (Resumo, Clientes, Acessos
FTP, Blogs, WordPress e E-commerce), com cabeçalhos coloridos, filtros e status
destacados. **A planilha inclui todas as senhas em texto puro**: trate o arquivo
como confidencial.

## Segurança

- Login por nome de usuário; senha em bcrypt (hash irreversível).
- Senha de FTP: criptografada com AES-256-GCM usando a `APP_KEY`; só é
  descriptografada sob demanda (botão "Mostrar"/"Copiar"), via requisição
  autenticada e protegida por token CSRF.
- Todas as rotas de gestão de acessos, clientes e usuários exigem sessão
  autenticada, e as ações sensíveis (criar/editar/excluir) são checadas contra
  o papel (`role`) e as permissões do usuário.
- `.env`, `composer.json` e `composer.lock` são bloqueados via `.htaccess`.
- Todas as consultas usam PDO com *prepared statements*.
