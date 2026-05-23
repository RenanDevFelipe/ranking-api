# Ranking API

API backend para gerenciamento de ranking, avaliações e integração com IXC.

## Visão geral

Este projeto é uma API construída com Laravel 11 para suportar:
- autenticação via Laravel Sanctum;
- gestão de usuários, setores, cargos e colaboradores;
- controle de checklists e pontuações por assunto;
- integração com o sistema IXC para consulta de ordens de serviço;
- cálculo e exibição de resultados de ranking diário, mensal e anual;
- histórico de avaliações de qualidade e produção;
- sincronização de produção de ordens de serviço.

## Tecnologias

- PHP 8.2+
- Laravel 11
- Laravel Sanctum
- Vite
- Axios
- SQLite / MySQL (configurável)

## Recursos principais

- Autenticação JWT via Sanctum
- CRUD de usuários, setores, cargos, colaboradores e configurações IXC
- Gestão de checklists, assuntos e itens de checklist
- Ajustes de pontuação para N2, RH e Estoque
- Consultas de histórico por setor
- Rankings diário, mensal e anual
- Dashboards com resumo, top assuntos e produção por dia
- Integração com IXC para ordens de serviço e conexão

## Requisitos

- PHP 8.2+
- Composer
- Node.js 18+ / npm
- Extensões PHP: cURL, PDO, OpenSSL, Mbstring, Tokenizer, XML

## Instalação

1. Copie o arquivo de ambiente:

   ```powershell
   cp .env.example .env
   ```

2. Instale dependências PHP:

   ```powershell
   composer install
   ```

3. Instale dependências front-end:

   ```powershell
   npm install
   ```

4. Gere a chave de aplicação Laravel:

   ```powershell
   php artisan key:generate
   ```

5. Configure o banco de dados em `.env`.

6. Execute as migrations:

   ```powershell
   php artisan migrate
   ```

## Configuração

Ajuste as variáveis de ambiente em `.env`, especialmente:

- `APP_URL`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

Para produção, defina `APP_ENV=production` e `APP_DEBUG=false`.

## Como executar

```powershell
php artisan serve
npm run dev
```

A API ficará disponível em `http://127.0.0.1:8000`.

## Autenticação

- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`

### Exemplo de login

```json
{
  "email_user": "usuario@example.com",
  "senha_user": "senha123"
}
```

### Cabeçalho de autorização

```
Authorization: Bearer <access_token>
```

## Rotas principais

- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`
- `GET|POST|PUT|DELETE /api/v1/users`
- `GET|POST|PUT|DELETE /api/v1/sectors`
- `GET|POST|PUT|DELETE /api/v1/roles`
- `GET|POST|PUT|DELETE /api/v1/colaborators`
- `GET|POST|PUT|DELETE /api/v1/ixc-configs`
- `GET /api/v1/ixc/ordens-servico`
- `GET /api/v1/ixc/ordens-servico/{id}`
- `GET /api/v1/ixc/ordens-servico/finalizadas`
- `GET /api/v1/ixc/testar-conexao`
- `GET|POST|PUT|DELETE /api/v1/avaliacoes-n3`
- `GET /api/v1/avaliacoes-n3/verificar-os/{idOs}`
- `GET|POST|PUT|DELETE /api/v1/checklists`
- `GET|POST|PUT|DELETE /api/v1/checklist/assuntos`
- `GET /api/v1/checklist/assuntos/ixc/{idAssuntoIxc}`
- `POST /api/v1/checklist-itens`
- `GET|POST|PUT|DELETE /api/v1/pontuacao-assunto`
- `POST /api/v1/ajustes-pontuacao/n2`
- `POST /api/v1/ajustes-pontuacao/rh`
- `POST /api/v1/ajustes-pontuacao/estoque`
- `GET /api/v1/historicos/n2`
- `GET /api/v1/historicos/rh`
- `GET /api/v1/historicos/estoque`
- `GET /api/v1/ranking-configuracoes/ativa`
- `GET|POST|PUT|DELETE /api/v1/ranking-configuracoes`
- `GET /api/v1/ranking/diario`
- `GET /api/v1/ranking/mensal`
- `GET /api/v1/ranking/anual`
- `GET /api/v1/dashboard/resumo`
- `GET /api/v1/dashboard/top-assuntos`
- `GET /api/v1/dashboard/producao-por-dia`
- `POST /api/v1/producao-os/sync`

## Estrutura do projeto

- `app/Http/Controllers/Api/V1` - controladores da API
- `app/Models` - modelos Eloquent
- `app/Services` - lógica de integração IXC e ranking
- `app/Traits` - trait de respostas JSON
- `config/` - configurações do Laravel
- `routes/api.php` - rotas da API

## Documentação completa

Veja `DOCUMENTATION.md` para uma descrição detalhada do modelo de dados, rotas, integrações e fluxo de autenticação.

## Testes

Execute os testes com:

```powershell
phpunit
```

## Licença

MIT
