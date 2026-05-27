<?php

namespace App\Services;

use App\Models\IxcConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class IxcService
{
    private function client(IxcConfig $config, string $ixcsoft = 'listar'): PendingRequest
    {
        $base64Token = base64_encode($config->token);
        return Http::withHeaders([
            'Authorization' => 'Basic ' . $base64Token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'ixcsoft' => $ixcsoft,
        ])
        ->timeout(280)
        ->retry(2, 500);
    }

    private function consultar(IxcConfig $config, string $endpoint, array $payload, string $ixcsoft = 'listar'): array
    {
        try {
            $baseUrl = rtrim($config->base_url, '/');

            $response = $this->client($config, $ixcsoft)
                ->post($baseUrl . '/' . ltrim($endpoint, '/'), $payload);

            if ($response->failed()) {
                Log::error('Erro na API IXC', [
                    'config_id' => $config->id,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Erro ao consultar IXC.',
                    'status' => $response->status(),
                    'data' => null,
                ];
            }

            return [
                'success' => true,
                'message' => 'Consulta realizada com sucesso.',
                'status' => 200,
                'data' => $response->json(),
            ];
        } catch (Throwable $e) {
            Log::error('Falha de comunicação com IXC', [
                'config_id' => $config->id ?? null,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Falha de comunicação com IXC.',
                'status' => 500,
                'data' => null,
            ];
        }
    }

    public function listarOrdensServico(IxcConfig $config, array $filters = []): array
    {
        $payload = [
            'qtype' => $filters['qtype'] ?? 'su_oss_chamado.status',
            'query' => $filters['query'] ?? 'F',
            'oper' => $filters['oper'] ?? '=',
            'page' => $filters['page'] ?? '1',
            'rp' => $filters['rp'] ?? '20',
            'sortname' => $filters['sortname'] ?? 'su_oss_chamado.id',
            'sortorder' => $filters['sortorder'] ?? 'desc',
        ];

        return $this->consultar($config, 'su_oss_chamado', $payload);
    }

    public function buscarOrdemServicoPorId(IxcConfig $config, int|string $id): array
    {
        $payload = [
            'qtype' => 'su_oss_chamado.id',
            'query' => $id,
            'oper' => '=',
            'page' => '1',
            'rp' => '1',
            'sortname' => 'su_oss_chamado.id',
            'sortorder' => 'desc',
        ];

        return $this->consultar($config, 'su_oss_chamado', $payload);
    }

    public function listarOrdensServicoAbertasPorAtendimento(
        IxcConfig $config,
        int|string $idAtendimento,
        string $campoAtendimento = 'id_atendimento'
    ): array {
        $camposPermitidos = [
            'id_atendimento',
            'id_ticket',
            'id_su_oss_atendimento',
        ];
        $campoAtendimento = in_array($campoAtendimento, $camposPermitidos, true)
            ? $campoAtendimento
            : 'id_atendimento';
        $campoIxc = 'su_oss_chamado.' . $campoAtendimento;

        $payload = [
            'qtype' => $campoIxc,
            'query' => $idAtendimento,
            'oper' => '=',
            'page' => '1',
            'rp' => '100',
            'sortname' => 'su_oss_chamado.id',
            'sortorder' => 'asc',
            'grid_param' => json_encode([
                [
                    'TB' => $campoIxc,
                    'OP' => '=',
                    'P' => $idAtendimento,
                ],
                [
                    'TB' => 'su_oss_chamado.status',
                    'OP' => '=',
                    'P' => 'A',
                ],
            ], JSON_UNESCAPED_UNICODE),
        ];

        return $this->consultar($config, 'su_oss_chamado', $payload);
    }

    private function preencherTemplatePayload(array $payload, array $context): array
    {
        return collect($payload)->mapWithKeys(function ($value, $key) use ($context) {
            if (is_array($value)) {
                return [$key => $this->preencherTemplatePayload($value, $context)];
            }

            if (!is_string($value)) {
                return [$key => $value];
            }

            return [$key => preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($context) {
                return (string) ($context[$matches[1]] ?? '');
            }, $value)];
        })->toArray();
    }

    public function fecharOrdemServico(
        IxcConfig $config,
        int|string $idTicket,
        array $context = [],
        array $payloadTemplate = []
    ): array {
        $defaultPayload = [
            'id_chamado' => '{id_os}',
            'id_tarefa_atual' => '',
            'eh_tarefa_decisao' => '',
            'sequencia_atual' => '',
            'proxima_sequencia_forcada' => '',
            'finaliza_processo_aux' => '',
            'gera_comissao_aux' => '',
            'id_processo' => '',
            'data_inicio' => '{data_inicio}',
            'data_final' => '{data_final}',
            'id_resposta' => '',
            'mensagem' => '',
            'id_tecnico' => '{id_tecnico}',
            'id_equipe' => '{id_equipe}',
            'gera_comissao' => '',
            'status' => 'F',
            'data' => '{data}',
            'id_evento' => '',
            'id_su_diagnostico' => '',
            'justificativa_sla_atrasado' => '',
            'id_evento_status' => '',
            'id_proxima_tarefa' => '',
            'id_proxima_tarefa_aux' => '',
            'latitude' => '',
            'longitude' => '',
            'gps_time' => '',
        ];

        $context = array_merge([
            'id_os' => $idTicket,
            'id_chamado' => $idTicket,
            'id_tecnico' => $context['id_tecnico'] ?? '',
            'id_equipe' => $context['id_equipe'] ?? '',
            'data' => $context['data'] ?? $context['data_final'] ?? $context['data_finalizacao'] ?? now()->format('Y-m-d H:i:s'),
            'data_final' => $context['data_final'] ?? $context['data_finalizacao'] ?? now()->format('Y-m-d H:i:s'),
            'data_inicio' => $context['data_inicio'] ?? $context['data'] ?? now()->format('Y-m-d H:i:s'),
        ], $context);

        $payload = $this->preencherTemplatePayload(
            array_replace_recursive($defaultPayload, $payloadTemplate),
            $context
        );

        $resultadoFechamento = $this->consultar($config, 'su_oss_chamado_fechar', $payload, 'alterar');

        if (!$resultadoFechamento['success']) {
            $resultadoFechamento['data'] = [
                'retorno_fechamento' => $resultadoFechamento['data'],
                'payload_enviado' => $payload,
            ];

            return $resultadoFechamento;
        }

        $resultadoConfirmacao = $this->buscarOrdemServicoPorId($config, $idTicket);
        $ordemConfirmada = $resultadoConfirmacao['success']
            ? $this->primeiroRegistro($resultadoConfirmacao['data'])
            : null;
        $statusConfirmado = strtoupper((string) ($ordemConfirmada['status'] ?? ''));
        $finalizada = $statusConfirmado === 'F';

        if (!$finalizada) {
            Log::warning('IXC respondeu ao fechamento, mas a OS nao foi confirmada como finalizada', [
                'config_id' => $config->id,
                'id_os' => $idTicket,
                'status_confirmado' => $statusConfirmado ?: null,
                'retorno_fechamento' => $resultadoFechamento['data'],
                'payload_enviado' => $payload,
            ]);
        }

        return [
            'success' => $finalizada,
            'message' => $finalizada
                ? 'Ordem de servico finalizada e confirmada no IXC.'
                : 'O IXC respondeu ao fechamento, mas a ordem de servico nao foi confirmada com status finalizada.',
            'status' => $finalizada ? 200 : 422,
            'data' => [
                'status_confirmado' => $statusConfirmado ?: null,
                'ordem_confirmada' => $ordemConfirmada,
                'retorno_fechamento' => $resultadoFechamento['data'],
                'payload_enviado' => $payload,
            ],
        ];
    }

    public function listarArquivosPorTicket(
        IxcConfig $config,
        int|string $idTicket,
        array $filters = []
    ): array {
        $payload = [
            'qtype' => $filters['qtype'] ?? 'su_oss_chamado_arquivos.id_oss_chamado',
            'query' => $filters['query'] ?? $idTicket,
            'oper' => $filters['oper'] ?? '=',
            'page' => $filters['page'] ?? '1',
            'rp' => $filters['rp'] ?? '1000',
            'sortname' => $filters['sortname'] ?? 'su_oss_chamado_arquivos.id',
            'sortorder' => $filters['sortorder'] ?? 'desc',
        ];

        return $this->consultar($config, 'su_oss_chamado_arquivos', $payload);
    }

    public function buscarPrimeiroArquivoPorTicket(IxcConfig $config, int|string $idTicket): ?array
    {
        $result = $this->listarArquivosPorTicket($config, $idTicket);

        if (!$result['success']) {
            return null;
        }

        $registros = $this->extrairRegistros($result['data']);

        return $registros[0] ?? null;
    }

    public function listarOrdensServicoFinalizadasPorTecnico(
        IxcConfig $config,
        int|string $idTecnico,
        string $dataInicio,
        string $dataFim,
        array $filters = []
    ): array {
        $payload = [
            'qtype' => 'su_oss_chamado.id',
            'query' => '',
            'oper' => '=',
            'page' => $filters['page'] ?? '1',
            'rp' => $filters['rp'] ?? '20',
            'sortname' => 'su_oss_chamado.data_final',
            'sortorder' => 'desc',
            'grid_param' => json_encode([
                [
                    'TB' => 'su_oss_chamado.id_tecnico',
                    'OP' => '=',
                    'P' => $idTecnico,
                ],
                [
                    'TB' => 'su_oss_chamado.status',
                    'OP' => '=',
                    'P' => 'F',
                ],
                [
                    'TB' => 'su_oss_chamado.data_final',
                    'OP' => '>=',
                    'P' => $dataInicio . ' 00:00:00',
                ],
                [
                    'TB' => 'su_oss_chamado.data_final',
                    'OP' => '<=',
                    'P' => $dataFim . ' 23:59:59',
                ],
            ], JSON_UNESCAPED_UNICODE),
        ];

        return $this->consultar($config, 'su_oss_chamado', $payload);
    }

    public function listarOrdensServicoFinalizadasPorTecnicoFormatadas(
        IxcConfig $config,
        int|string $idTecnico,
        string $dataInicio,
        string $dataFim,
        array $filters = []
    ): array {
        $result = $this->listarOrdensServicoFinalizadasPorTecnico(
            config: $config,
            idTecnico: $idTecnico,
            dataInicio: $dataInicio,
            dataFim: $dataFim,
            filters: $filters
        );

        if (!$result['success']) {
            return $result;
        }

        $registros = $this->extrairRegistros($result['data']);

        $ordens = collect($registros)
            ->map(function (array $os) use ($config) {
                $ordem = $this->formatarOrdemServico($config, $os);
                $arquivo = $this->buscarPrimeiroArquivoPorTicket($config, $os['id'] ?? null);

                $ordem['arquivo_id'] = $arquivo['id'] ?? null;
                $ordem['arquivo'] = [
                    'id' => $arquivo['id'] ?? null,
                ];

                return $ordem;
            })
            ->values();

        return [
            'success' => true,
            'message' => 'Ordens de serviço finalizadas consultadas com sucesso.',
            'status' => 200,
            'data' => [
                'total' => $result['data']['total'] ?? $ordens->count(),
                'registros' => $ordens,
            ],
        ];
    }

    public function buscarClientePorId(IxcConfig $config, int|string|null $idCliente): ?array
    {
        if (!$idCliente) {
            return null;
        }

        $result = $this->consultar($config, 'cliente', [
            'qtype' => 'cliente.id',
            'query' => $idCliente,
            'oper' => '=',
            'page' => '1',
            'rp' => '1',
            'sortname' => 'cliente.id',
            'sortorder' => 'desc',
        ]);

        return $this->primeiroRegistro($result['data'] ?? null);
    }

    public function buscarAssuntoPorId(IxcConfig $config, int|string|null $idAssunto): ?array
    {
        if (!$idAssunto) {
            return null;
        }

        $result = $this->consultar($config, 'su_oss_assunto', [
            'qtype' => 'su_oss_assunto.id',
            'query' => $idAssunto,
            'oper' => '=',
            'page' => '1',
            'rp' => '1',
            'sortname' => 'su_oss_assunto.id',
            'sortorder' => 'desc',
        ]);

        return $this->primeiroRegistro($result['data'] ?? null);
    }

    public function listarOrdensServicoFormatadas(IxcConfig $config, array $filters = []): array
    {
        $result = $this->listarOrdensServico($config, $filters);

        if (!$result['success']) {
            return $result;
        }

        $registros = $this->extrairRegistros($result['data']);

        $ordens = collect($registros)
            ->map(function (array $os) use ($config) {
                return $this->formatarOrdemServico($config, $os);
            })
            ->values();

        return [
            'success' => true,
            'message' => 'Ordens de serviço formatadas com sucesso.',
            'status' => 200,
            'data' => [
                'total' => $result['data']['total'] ?? $ordens->count(),
                'registros' => $ordens,
            ],
        ];
    }

    private function formatarOrdemServico(IxcConfig $config, array $os): array
    {
        $cliente = $this->buscarClientePorId($config, $os['id_cliente'] ?? null);
        $assunto = $this->buscarAssuntoPorId($config, $os['id_assunto'] ?? null);

        return [
            'id_os' => $os['id'] ?? null,
            'status' => $this->formatarStatus($os['status'] ?? null),

            'cliente' => [
                'id' => $os['id_cliente'] ?? null,
                'nome' => $cliente['razao'] ?? $cliente['nome'] ?? $os['cliente'] ?? null,
            ],

            'assunto' => [
                'id' => $os['id_assunto'] ?? null,
                'nome' => $assunto['assunto'] ?? $assunto['descricao'] ?? null,
            ],

            'mensagem' => $os['mensagem'] ?? $os['descricao'] ?? $os['observacao'] ?? null,

            'datas' => [
                'abertura' => $os['data_abertura'] ?? null,
                'finalizacao' => $os['data_final'] ?? $os['data_finalizacao'] ?? null,
            ],

            'tecnico' => [
                'id' => $os['id_tecnico'] ?? null,
                'nome' => $os['tecnico'] ?? null,
            ],

            'raw' => $os,
        ];
    }

    private function formatarStatus(?string $status): array
    {
        return [
            'codigo' => $status,
            'nome' => match ($status) {
                'A' => 'ABERTA',
                'F' => 'FINALIZADA',
                'AG' => 'AGENDADA',
                'EX' => 'EXECUÇÃO',
                'AS' => 'ASSUMIDA',
                default => $status,
            },
        ];
    }

    private function extrairRegistros(?array $data): array
    {
        if (!$data) {
            return [];
        }

        if (isset($data['registros']) && is_array($data['registros'])) {
            return $data['registros'];
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }

        return [];
    }

    public function extrairRegistrosResposta(?array $data): array
    {
        return $this->extrairRegistros($data);
    }

    private function primeiroRegistro(?array $data): ?array
    {
        $registros = $this->extrairRegistros($data);

        return $registros[0] ?? null;
    }
}
