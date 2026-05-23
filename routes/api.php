<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvaliacaoN3Controller;
use App\Http\Controllers\Api\V1\ChecklistAssuntoController;
use App\Http\Controllers\Api\V1\ChecklistController;
use App\Http\Controllers\Api\V1\ChecklistItemController;
use App\Http\Controllers\Api\V1\ColaboradorController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\IxcConfigController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SetorController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\IxcController;
use App\Http\Controllers\Api\V1\AjustePontuacaoController;
use App\Http\Controllers\Api\V1\HistoricoController;
use App\Http\Controllers\Api\V1\PontuacaoAssuntoController;
use App\Http\Controllers\Api\V1\RankingConfiguracaoController;
use App\Http\Controllers\Api\V1\RankingController;
use App\Models\PontuacaoAssunto;
use App\Http\Controllers\Api\V1\ProducaoOsSyncController;

Route::prefix('v1')->group(function () {

    // Rota de auth
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });


    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('sectors', SetorController::class);
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('colaborators', ColaboradorController::class);
        Route::apiResource('ixc-configs', IxcConfigController::class);

        Route::prefix('ixc')
            ->group(function () {
                Route::get('/ordens-servico', [IxcController::class, 'ordensServico']);

                Route::get('/ordens-servico/finalizadas', [
                    IxcController::class,
                    'ordensServicoFinalizadasPorTecnico'
                ]);

                Route::get('/ordens-servico/{id}', [IxcController::class, 'ordemServico']);

                Route::get('/testar-conexao', [IxcController::class, 'testarConexao']);
            });

        Route::apiResource('avaliacoes-n3', AvaliacaoN3Controller::class);
        Route::get('avaliacoes-n3/verificar-os/{idOs}', [AvaliacaoN3Controller::class, 'verificarOsAvaliada']);

        Route::apiResource('checklists', ChecklistController::class);
        Route::apiResource('checklist-itens', ChecklistItemController::class)->except(['index']);

        Route::prefix('checklist')
            ->group(function () {
                Route::apiResource('/assuntos', ChecklistAssuntoController::class);
                Route::get('assuntos/ixc/{idAssuntoIxc}', [ChecklistAssuntoController::class, 'buscarPorAssuntoIxc']);
            });
        Route::apiResource('pontuacao-assunto', PontuacaoAssuntoController::class);

        Route::prefix('ajustes-pontuacao')
            ->group(function () {
                Route::post('/n2', [AjustePontuacaoController::class, 'ajustarN2']);
                Route::post('/rh', [AjustePontuacaoController::class, 'ajustarRh']);
                Route::post('/estoque', [AjustePontuacaoController::class, 'ajustarEstoque']);
            });

        Route::prefix('historicos')
            ->group(function () {
                Route::get('/n2', [HistoricoController::class, 'n2']);
                Route::get('/rh', [HistoricoController::class, 'rh']);
                Route::get('estoque', [HistoricoController::class, 'estoque']);
            });

        Route::get('ranking-configuracoes/ativa', [RankingConfiguracaoController::class, 'ativa']);
        Route::apiResource('ranking-configuracoes', RankingConfiguracaoController::class);

        Route::prefix('ranking')
            ->group(function () {
                Route::get('/diario', [RankingController::class, 'diario']);
                Route::get('/mensal', [RankingController::class, 'mensal']);
                Route::get('/anual', [RankingController::class, 'anual']);
            });

        Route::prefix('dashboard')
            ->middleware('auth:sanctum')
            ->group(function () {
                Route::get('/resumo', [DashboardController::class, 'resumo']);
                Route::get('/top-assuntos', [DashboardController::class, 'topAssuntos']);
                Route::get('/producao-por-dia', [DashboardController::class, 'producaoPorDia']);
            });

        Route::prefix('producao-os')
            ->group(function () {
                Route::post('/sync', [ProducaoOsSyncController::class, 'sync']);
            });
    });
});
