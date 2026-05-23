# Documentação do Ranking API

## Visão geral

O `Ranking API` é um backend Laravel 11 responsável por:
- autenticação de usuários com Laravel Sanctum;
- gestão de usuários, setores, cargos e colaboradores;
- controle de checklists, assuntos e itens de checklist;
- integração com IXC para consulta de ordens de serviço;
- cálculo de ranking diário, mensal e anual;
- histórico de avaliações e produção;
- sincronização de produção de ordens de serviço.

## Arquitetura

Os principais componentes são:
- `app/Http/Controllers/Api/V1` - controladores de API REST;
- `app/Models` - modelos Eloquent para persistência;
- `app/Services` - lógica de negócios e integrações externas;
- `app/Traits/ApiResponseTrait.php` - padrão de respostas JSON;
- `routes/api.php` - rotas da API;
- `config/` - configurações do aplicativo.

## Autenticação

A autenticação é feita via Laravel Sanctum.

### Endpoints de autenticação

- `POST /api/v1/auth/login`
  - corpo: `email_user`, `senha_user`
  - retorno: `access_token` Bearer
- `GET /api/v1/auth/me`
  - retorna dados do usuário autenticado
- `POST /api/v1/auth/logout`
  - revoga o token atual

### Exemplo de requisição de login

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email_user": "usuario@example.com",
  "senha_user": "senha123"
}
```

### Exemplo de cabeçalho autenticado

```http
Authorization: Bearer <access_token>
```

## Rotas e recursos

### Recursos CRUD

- `users`
- `sectors`
- `roles`
- `colaborators`
- `ixc-configs`
- `avaliacoes-n3`
- `checklists`
- `checklist/assuntos`
- `pontuacao-assunto`
- `ranking-configuracoes`

### Rotas de integração IXC

- `GET /api/v1/ixc/ordens-servico`
- `GET /api/v1/ixc/ordens-servico/{id}`
- `GET /api/v1/ixc/ordens-servico/finalizadas`
- `GET /api/v1/ixc/testar-conexao`

### Rotas de ajustes de pontuação

- `POST /api/v1/ajustes-pontuacao/n2`
- `POST /api/v1/ajustes-pontuacao/rh`
- `POST /api/v1/ajustes-pontuacao/estoque`

### Rotas de histórico

- `GET /api/v1/historicos/n2`
- `GET /api/v1/historicos/rh`
- `GET /api/v1/historicos/estoque`

### Rotas de ranking

- `GET /api/v1/ranking/diario`
- `GET /api/v1/ranking/mensal`
- `GET /api/v1/ranking/anual`

### Rotas de dashboard

- `GET /api/v1/dashboard/resumo`
- `GET /api/v1/dashboard/top-assuntos`
- `GET /api/v1/dashboard/producao-por-dia`

### Rotas de sincronização

- `POST /api/v1/producao-os/sync`

## Componentes principais

### `App\Services\IxcService`

Responsável por acessar a API IXC por meio de configurações salvas em `ixc-configs`.

Funcionalidades:
- consulta de ordens de serviço;
- busca de ordem de serviço por ID;
- consulta de ordens finalizadas por técnico;
- formatação da resposta retornada pelo IXC;
- resolução de cliente e assunto relacionados.

### `App\Services\RankingService`

Responsável pelos cálculos de produção e qualidade:
- produção diária com total de OS, pontos e meta;
- qualidade diária com médias de N3, N2, RH, estoque e sucesso;
- cálculo de nota de produção e percentual de meta.

## Modelos de dados importantes

### Usuário

- `id_user`
- `nome_user`
- `email_user`
- `senha_user`
- `role`
- `setor_user`

### IXC Config

- `base_url`
- `token`
- `ativo`

### Checklist

- listas de itens e assuntos vinculados a processos de avaliação.

### Ranking Configuração

- configura as regras e a configuração ativa para cálculo de ranking.

## Configuração de ambiente

Arquivo padrão disponível em `.env.example`.

Principais variáveis:

- `APP_URL`
- `APP_ENV`
- `APP_DEBUG`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `SESSION_DRIVER`
- `CACHE_STORE`

## Banco de dados

O projeto suporta configuração de banco via `.env`.

O exemplo padrão utiliza SQLite, mas pode ser migrado para MySQL ou outro driver suportado.

### Migrations

Use:

```bash
php artisan migrate
```

## Execução local

```bash
php artisan serve
npm run dev
```

## Testes

Executar:

```bash
phpunit
```

## Observações importantes

- Todas as rotas de API estão prefixadas com `/api/v1`.
- As rotas de recurso e as rotas de integração estão protegidas por `auth:sanctum`.
- `AuthController` utiliza `email_user` e `senha_user` para login.

## Licença

MIT
