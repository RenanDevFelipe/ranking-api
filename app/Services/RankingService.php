<?php

namespace App\Services;

use App\Models\AvaliacaoEstoque;
use App\Models\AvaliacaoN2;
use App\Models\AvaliacaoN3;
use App\Models\AvaliacaoRh;
use App\Models\AvaliacaoSucesso;
use App\Models\ProducaoOs;
use App\Models\RankingConfiguracao;

class RankingService
{
    public function getConfiguracaoAtiva(): RankingConfiguracao
    {
        return RankingConfiguracao::where('ativo', true)->firstOrFail();
    }

    public function calcularProducaoDiaria(
        int|string $idColaborador,
        string $data,
        float $metaDiaria
    ): array {
        $query = ProducaoOs::where('id_colaborador', $idColaborador)
            ->whereDate('data_finalizacao', $data);

        $totalOs = (clone $query)->count();
        $totalPontos = (float) (clone $query)->sum('pontos');

        return [
            'total_os' => $totalOs,
            'total_pontos' => $totalPontos,
            'meta_diaria' => $metaDiaria,
            'bateu_meta' => $totalPontos >= $metaDiaria,
            'percentual_meta' => $metaDiaria > 0
                ? round(($totalPontos / $metaDiaria) * 100, 2)
                : 0,
            'nota_producao' => $metaDiaria > 0
                ? min(10, round(($totalPontos / $metaDiaria) * 10, 2))
                : 0,
            'detalhes' => (clone $query)
                ->select([
                    'id_os',
                    'id_assunto_ixc',
                    'nome_assunto_ixc',
                    'pontos',
                    'data_finalizacao_os',
                ])
                ->orderByDesc('data_finalizacao_os')
                ->get(),
        ];
    }

    public function calcularQualidadeDiaria(
        int|string $idColaborador,
        string $data
    ): array {
        $mediaN3 = AvaliacaoN3::where('id_tecnico', $idColaborador)
            ->whereDate('data_finalizacao', $data)
            ->avg('nota_os');

        $mediaN2 = AvaliacaoN2::where('id_tecnico_n2', $idColaborador)
            ->whereDate('data_finalizacao', $data)
            ->selectRaw('AVG((ponto_finalizacao_os + ponto_lavagem_carro + organizacao_material + ponto_fardamento) / 4) as media')
            ->value('media');

        $mediaRh = AvaliacaoRh::where('id_tecnico', $idColaborador)
            ->whereDate('data_avaliacao', $data)
            ->selectRaw('AVG((pnt_ponto + pnt_atestado + pnt_falta) / 3) as media')
            ->value('media');

        $mediaEstoque = AvaliacaoEstoque::where('id_tecnico_estoque', $idColaborador)
            ->whereDate('data_finalizacao', $data)
            ->selectRaw('AVG((pnt_pedido + pnt_prazo + pnt_etiqueta + pnt_baixa_mat + pnt_troca_equip + pnt_transferencia) / 6) as media')
            ->value('media');

        $mediaSucesso = AvaliacaoSucesso::where('id_tecnico', $idColaborador)
            ->whereDate('data_avaliacao', $data)
            ->avg('ponto_sucesso');

        $medias = collect([
            'n3' => $mediaN3,
            'n2' => $mediaN2,
            'rh' => $mediaRh,
            'estoque' => $mediaEstoque,
            'sucesso' => $mediaSucesso,
        ]);

        $mediasValidas = $medias->filter(fn ($value) => !is_null($value));

        return [
            'por_setor' => [
                'n3' => round((float) ($mediaN3 ?? 0), 2),
                'n2' => round((float) ($mediaN2 ?? 0), 2),
                'rh' => round((float) ($mediaRh ?? 0), 2),
                'estoque' => round((float) ($mediaEstoque ?? 0), 2),
                'sucesso' => round((float) ($mediaSucesso ?? 0), 2),
            ],
            'media_geral' => $mediasValidas->count() > 0
                ? round((float) $mediasValidas->avg(), 2)
                : 0,
        ];
    }
}