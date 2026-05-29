<?php

namespace App\Services;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\IxcConfig;
use App\Models\IxcFinalizacaoConfig;
use Illuminate\Support\Facades\Log;

class IxcFinalizacaoAutomaticaService
{
    private const MAX_ITERACOES = 10;
    private const MAX_RECONSULTAS_SEM_CANDIDATAS = 3;
    private const INTERVALO_RECONSULTA_MICROSSEGUNDOS = 800000;

    public function __construct(
        protected IxcService $ixcService
    ) {}

    public function processarOrdemAvaliada(
        IxcConfig $ixcConfig,
        array $ordem,
        int $idChecklist,
        int $idAssuntoIxc,
        int|string|null $idColaboradorIxc = null,
        array $respostas = [],
        array $mensagensFinalizacao = []
    ): array {
        $raw = $ordem['raw'] ?? $ordem;
        $idOs = $raw['id'] ?? $ordem['id_os'] ?? null;
        $atendimento = $this->identificarAtendimento($raw);

        if (strtoupper((string) ($raw['status'] ?? '')) === 'F') {
            if (!$idColaboradorIxc) {
                return [
                    'id_atendimento' => $atendimento['id'] ?? null,
                    'campo_atendimento' => $atendimento['campo_retorno'] ?? null,
                    'fechamentos' => [],
                    'ignorado' => true,
                    'motivo' => 'O usuario avaliador nao possui id_ixc_user para finalizar a ordem no IXC.',
                ];
            }

            return $this->processarPorOrdemFinalizada(
                $ixcConfig,
                $raw,
                $idOs ? [$idOs] : [],
                $idColaboradorIxc,
                $respostas,
                $idChecklist,
                $mensagensFinalizacao
            );
        }

        $configFinalizacao = $this->buscarConfiguracao(
            $idChecklist,
            $idAssuntoIxc,
            $respostas
        );

        if (!$configFinalizacao) {
            return [
                'id_atendimento' => $atendimento['id'] ?? null,
                'campo_atendimento' => $atendimento['campo_retorno'] ?? null,
                'fechamentos' => [],
                'ignorado' => true,
                'motivo' => 'Nenhuma configuracao ativa encontrada para o assunto do checklist avaliado.',
            ];
        }

        if (!$idOs) {
            return [
                'id_atendimento' => $atendimento['id'] ?? null,
                'campo_atendimento' => $atendimento['campo_retorno'] ?? null,
                'fechamentos' => [],
                'ignorado' => true,
                'motivo' => 'A ordem retornada pelo IXC nao possui id.',
            ];
        }

        if (!$idColaboradorIxc) {
            return [
                'id_atendimento' => $atendimento['id'] ?? null,
                'campo_atendimento' => $atendimento['campo_retorno'] ?? null,
                'fechamentos' => [],
                'ignorado' => true,
                'motivo' => 'O usuario avaliador nao possui id_ixc_user para finalizar a ordem no IXC.',
            ];
        }

        $payloadTemplate = $this->montarPayloadFechamento(
            $configFinalizacao,
            $idChecklist,
            $respostas,
            $mensagensFinalizacao
        );

        if ($payloadTemplate === null) {
            return [
                'id_atendimento' => $atendimento['id'] ?? null,
                'campo_atendimento' => $atendimento['campo_retorno'] ?? null,
                'fechamentos' => [[
                    'success' => false,
                    'id_os' => $idOs,
                    'config_id' => $configFinalizacao->id,
                    'message' => 'Informe a mensagem do usuario para finalizar esta ordem no IXC.',
                ]],
                'ignorado' => false,
            ];
        }

        $resultadoFechamento = $this->ixcService->fecharOrdemServico(
            config: $ixcConfig,
            idTicket: $idOs,
            context: [
                'id_atendimento' => $atendimento['id'] ?? '',
                'id_tecnico' => $idColaboradorIxc,
                'id_equipe' => $raw['id_equipe'] ?? '',
                'data_inicio' => $raw['data_abertura'] ?? now()->format('Y-m-d H:i:s'),
                'data_final' => now()->format('Y-m-d H:i:s'),
                'data' => now()->format('Y-m-d H:i:s'),
            ],
            payloadTemplate: $payloadTemplate
        );

        $fechamentoInicial = [
            'success' => $resultadoFechamento['success'],
            'id_atendimento' => $atendimento['id'] ?? null,
            'campo_atendimento' => $atendimento['campo_retorno'] ?? null,
            'id_os' => $idOs,
            'id_assunto_ixc' => $idAssuntoIxc,
            'id_checklist_assunto' => $this->idAssuntoExecucao($configFinalizacao),
            'config_id' => $configFinalizacao->id,
            'id_item_condicao' => $configFinalizacao->id_item_condicao,
            'resposta_condicao' => $configFinalizacao->resposta_condicao,
            'origem_mensagem' => $configFinalizacao->origem_mensagem,
            'ordem_execucao' => $configFinalizacao->ordem_execucao,
            'finalizar_atendimento' => $configFinalizacao->finalizar_atendimento,
            'message' => $resultadoFechamento['message'],
            'status_confirmado' => $resultadoFechamento['data']['status_confirmado'] ?? null,
            'retorno_ixc' => $resultadoFechamento['data']['retorno_fechamento'] ?? null,
            'payload_enviado' => $resultadoFechamento['data']['payload_enviado'] ?? null,
        ];

        if (!$resultadoFechamento['success'] || !$atendimento) {
            return [
                'id_atendimento' => $atendimento['id'] ?? null,
                'campo_atendimento' => $atendimento['campo_retorno'] ?? null,
                'fechamentos' => [$fechamentoInicial],
                'ignorado' => false,
            ];
        }

        $proximas = $this->processarPorOrdemFinalizada(
            $ixcConfig,
            $raw,
            [$idOs],
            $idColaboradorIxc,
            $respostas,
            $idChecklist,
            $mensagensFinalizacao
        );
        $proximas['fechamentos'] = array_merge(
            [$fechamentoInicial],
            $proximas['fechamentos'] ?? []
        );

        return $proximas;
    }

    public function processarPorOrdemFinalizada(
        IxcConfig $ixcConfig,
        array $ordem,
        array $ordensIgnoradas = [],
        int|string|null $idColaboradorIxc = null,
        array $respostas = [],
        ?int $idChecklist = null,
        array $mensagensFinalizacao = []
    ): array
    {
        $raw = $ordem['raw'] ?? $ordem;
        $atendimento = $this->identificarAtendimento($raw);
        $idAtendimento = $atendimento['id'] ?? null;

        if (!$idAtendimento) {
            return [
                'id_atendimento' => null,
                'fechamentos' => [],
                'ignorado' => true,
                'motivo' => 'A ordem de servico retornada pelo IXC nao informou o identificador do atendimento.',
                'campos_ixc' => array_keys($raw),
            ];
        }

        $fechamentos = [];
        $ordensProcessadas = array_fill_keys(array_map('strval', $ordensIgnoradas), true);
        $reconsultasSemCandidatas = 0;
        $motivoParada = null;

        for ($iteracao = 0; $iteracao < self::MAX_ITERACOES; $iteracao++) {
            $resultadoAbertas = $this->ixcService->listarOrdensServicoAbertasPorAtendimento(
                $ixcConfig,
                $idAtendimento,
                $atendimento['campo_consulta']
            );

            if (!$resultadoAbertas['success']) {
                $fechamentos[] = [
                    'success' => false,
                    'id_atendimento' => $idAtendimento,
                    'campo_atendimento' => $atendimento['campo_retorno'],
                    'message' => 'Falha ao consultar ordens abertas do atendimento.',
                ];
                break;
            }

            $ordensAbertas = collect($this->ixcService->extrairRegistrosResposta($resultadoAbertas['data']));
            $ordensSemConfiguracao = [];

            $candidatas = $ordensAbertas
                ->map(function (array $osAberta) use ($idChecklist, $respostas, $ordensProcessadas) {
                $idOs = $osAberta['id'] ?? null;
                $idAssunto = $osAberta['id_assunto'] ?? null;

                if (!$idOs || isset($ordensProcessadas[(string) $idOs]) || !$idAssunto) {
                    return null;
                }

                $configFinalizacao = $this->buscarConfiguracao(
                    $idChecklist,
                    (int) $idAssunto,
                    $respostas
                );

                if (!$configFinalizacao) {
                    return null;
                }

                return [
                    'os' => $osAberta,
                    'config' => $configFinalizacao,
                ];
            })
                ->filter()
                ->sort(function (array $a, array $b) {
                    return [
                        $a['config']->ordem_execucao ?? 1,
                        (int) ($a['os']['id'] ?? 0),
                    ] <=> [
                        $b['config']->ordem_execucao ?? 1,
                        (int) ($b['os']['id'] ?? 0),
                    ];
                })
                ->values();

            if ($candidatas->isEmpty()) {
                $ordensSemConfiguracao = $ordensAbertas
                    ->filter(fn (array $osAberta) => !isset($ordensProcessadas[(string) ($osAberta['id'] ?? '')]))
                    ->map(fn (array $osAberta) => [
                        'id_os' => $osAberta['id'] ?? null,
                        'id_assunto_ixc' => $osAberta['id_assunto'] ?? null,
                        'status' => $osAberta['status'] ?? null,
                    ])
                    ->values()
                    ->all();

                if (!empty($ordensSemConfiguracao)) {
                    return [
                        'id_atendimento' => $idAtendimento,
                        'campo_atendimento' => $atendimento['campo_retorno'],
                        'fechamentos' => [],
                        'ignorado' => false,
                        'motivo' => 'Existem ordens abertas no atendimento, mas nenhuma possui configuracao ativa vinculada ao checklist/assunto IXC.',
                        'ordens_abertas_sem_configuracao' => $ordensSemConfiguracao,
                        'id_checklist' => $idChecklist,
                    ];
                }

                if ($iteracao === 0) {
                    return [
                        'id_atendimento' => $idAtendimento,
                        'campo_atendimento' => $atendimento['campo_retorno'],
                        'fechamentos' => [],
                        'ignorado' => false,
                        'motivo' => 'Nenhuma ordem aberta foi encontrada no atendimento para finalizar automaticamente.',
                        'id_checklist' => $idChecklist,
                    ];
                }

                if ($reconsultasSemCandidatas < self::MAX_RECONSULTAS_SEM_CANDIDATAS) {
                    $reconsultasSemCandidatas++;
                    usleep(self::INTERVALO_RECONSULTA_MICROSSEGUNDOS);
                    continue;
                }

                $motivoParada = 'Nenhuma nova ordem aberta configurada apareceu no atendimento apos a ultima finalizacao.';
                break;
            }

            $reconsultasSemCandidatas = 0;

            $osAberta = $candidatas->first()['os'];
            $configFinalizacao = $candidatas->first()['config'];
            $idOs = $osAberta['id'];
            $idAssunto = $osAberta['id_assunto'];
            $payloadTemplate = $this->montarPayloadFechamento(
                $configFinalizacao,
                $idChecklist,
                $respostas,
                $mensagensFinalizacao
            );

            if ($payloadTemplate === null) {
                $fechamentos[] = [
                    'success' => false,
                    'id_atendimento' => $idAtendimento,
                    'campo_atendimento' => $atendimento['campo_retorno'],
                    'id_os' => $idOs,
                    'id_assunto_ixc' => $idAssunto,
                    'config_id' => $configFinalizacao->id,
                    'message' => 'Informe a mensagem do usuario para finalizar esta ordem no IXC.',
                ];
                break;
            }

            $resultadoFechamento = $this->ixcService->fecharOrdemServico(
                    config: $ixcConfig,
                    idTicket: $idOs,
                    context: [
                        'id_atendimento' => $idAtendimento,
                        'id_tecnico' => $idColaboradorIxc ?: ($osAberta['id_tecnico'] ?? ''),
                        'id_equipe' => $osAberta['id_equipe'] ?? '',
                        'data_inicio' => $osAberta['data_abertura'] ?? now()->format('Y-m-d H:i:s'),
                        'data_final' => now()->format('Y-m-d H:i:s'),
                        'data' => now()->format('Y-m-d H:i:s'),
                    ],
                    payloadTemplate: $payloadTemplate
                );

            $ordensProcessadas[(string) $idOs] = true;
            $fechamentos[] = [
                    'success' => $resultadoFechamento['success'],
                    'id_atendimento' => $idAtendimento,
                    'campo_atendimento' => $atendimento['campo_retorno'],
                    'id_os' => $idOs,
                    'id_assunto_ixc' => $idAssunto,
                    'id_checklist_assunto' => $this->idAssuntoExecucao($configFinalizacao),
                    'config_id' => $configFinalizacao->id,
                    'id_item_condicao' => $configFinalizacao->id_item_condicao,
                    'resposta_condicao' => $configFinalizacao->resposta_condicao,
                    'origem_mensagem' => $configFinalizacao->origem_mensagem,
                    'ordem_execucao' => $configFinalizacao->ordem_execucao,
                    'finalizar_atendimento' => $configFinalizacao->finalizar_atendimento,
                    'message' => $resultadoFechamento['message'],
                    'status_confirmado' => $resultadoFechamento['data']['status_confirmado'] ?? null,
                    'retorno_ixc' => $resultadoFechamento['data']['retorno_fechamento'] ?? null,
                    'payload_enviado' => $resultadoFechamento['data']['payload_enviado'] ?? null,
            ];

            if (!$resultadoFechamento['success']) {
                Log::warning('Falha ao finalizar automaticamente OS IXC', end($fechamentos));
                return [
                    'id_atendimento' => $idAtendimento,
                    'campo_atendimento' => $atendimento['campo_retorno'],
                    'fechamentos' => $fechamentos,
                    'ignorado' => false,
                ];
            }

            usleep(self::INTERVALO_RECONSULTA_MICROSSEGUNDOS);
        }

        $resultado = [
            'id_atendimento' => $idAtendimento,
            'campo_atendimento' => $atendimento['campo_retorno'],
            'fechamentos' => $fechamentos,
            'ignorado' => false,
        ];

        if ($motivoParada) {
            $resultado['motivo_parada'] = $motivoParada;
        }

        return $resultado;
    }

    private function buscarConfiguracao(
        ?int $idChecklist,
        int $idAssuntoIxc,
        array $respostas
    ): ?IxcFinalizacaoConfig {
        $configs = IxcFinalizacaoConfig::with('assuntos')
            ->where('id_assunto_ixc', $idAssuntoIxc)
            ->whereHas('assuntos', function ($query) use ($idChecklist) {
                if ($idChecklist) {
                    $query->where('id_checklist', $idChecklist);
                }
            })
            ->where('ativo', true)
            ->get();

        $configs->each(function (IxcFinalizacaoConfig $config) use ($idChecklist) {
            $assunto = $config->assuntos->first(function ($assunto) use ($idChecklist) {
                return !$idChecklist || (int) $assunto->id_checklist === $idChecklist;
            });

            if ($assunto) {
                $config->setAttribute('id_checklist_assunto_execucao', $assunto->id);
                $config->setRelation('assuntoExecucao', $assunto);
            }
        });

        foreach ($configs->whereNotNull('id_item_condicao') as $config) {
            $resposta = collect($respostas)->first(
                fn (array $item) => (int) ($item['id_item'] ?? 0) === (int) $config->id_item_condicao
            );

            if (
                $resposta
                && $this->normalizarResposta($resposta['resposta'] ?? null)
                    === $this->normalizarResposta($config->resposta_condicao)
            ) {
                return $config;
            }
        }

        return $configs->first(fn (IxcFinalizacaoConfig $config) => !$config->id_item_condicao);
    }

    private function normalizarResposta(mixed $resposta): string
    {
        if (is_bool($resposta)) {
            return $resposta ? 'true' : 'false';
        }

        if (is_array($resposta)) {
            return json_encode($resposta, JSON_UNESCAPED_UNICODE) ?: '';
        }

        return strtolower(trim((string) $resposta));
    }

    private function montarPayloadFechamento(
        IxcFinalizacaoConfig $config,
        ?int $idChecklist,
        array $respostas,
        array $mensagensFinalizacao
    ): ?array {
        $payload = $config->payloadFechamento();

        if ($config->origem_mensagem === 'usuario') {
            $idChecklistAssunto = $this->idAssuntoExecucao($config);
            $mensagem = collect($mensagensFinalizacao)->first(
                fn (array $item) => (int) ($item['id_checklist_assunto'] ?? 0) === (int) $idChecklistAssunto
            )['mensagem'] ?? null;

            return $mensagem ? array_replace($payload, ['mensagem' => $mensagem]) : null;
        }

        if ($config->origem_mensagem === 'checklist') {
            return array_replace($payload, [
                'mensagem' => $this->montarMensagemChecklist($idChecklist, $config, $respostas),
            ]);
        }

        return $payload;
    }

    private function montarMensagemChecklist(
        ?int $idChecklist,
        IxcFinalizacaoConfig $config,
        array $respostas
    ): string {
        $idChecklist ??= $config->relationLoaded('assuntoExecucao')
            ? $config->getRelation('assuntoExecucao')?->id_checklist
            : $config->assuntos()->value('id_checklist');
        $checklist = $idChecklist ? Checklist::find($idChecklist) : null;
        $itens = $idChecklist
            ? ChecklistItem::where('id_checklist', $idChecklist)->get()->keyBy('id_item')
            : collect();

        $linhas = collect($respostas)->map(function (array $resposta) use ($itens) {
            $pergunta = $itens->get($resposta['id_item'] ?? null)?->pergunta ?? ('Item ' . ($resposta['id_item'] ?? ''));
            $valor = $resposta['resposta'] ?? null;

            if (is_bool($valor)) {
                $valor = $valor ? 'Sim' : 'Nao';
            } elseif (is_array($valor)) {
                $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
            }

            return $pergunta . ': ' . $valor;
        })->implode("\n");

        return trim("Checklist: " . ($checklist?->nome_checklist ?? 'Avaliacao') . "\n" . $linhas);
    }

    private function idAssuntoExecucao(IxcFinalizacaoConfig $config): ?int
    {
        return $config->getAttribute('id_checklist_assunto_execucao')
            ?? $config->id_checklist_assunto;
    }

    private function identificarAtendimento(array $ordem): ?array
    {
        foreach ([
            'id_atendimento' => 'id_atendimento',
            'id_ticket' => 'id_ticket',
            'id_su_oss_atendimento' => 'id_su_oss_atendimento',
        ] as $campoRetorno => $campoConsulta) {
            if (!empty($ordem[$campoRetorno])) {
                return [
                    'id' => $ordem[$campoRetorno],
                    'campo_retorno' => $campoRetorno,
                    'campo_consulta' => $campoConsulta,
                ];
            }
        }

        if (!empty($ordem['atendimento']['id'])) {
            return [
                'id' => $ordem['atendimento']['id'],
                'campo_retorno' => 'atendimento.id',
                'campo_consulta' => 'id_atendimento',
            ];
        }

        return null;
    }
}
