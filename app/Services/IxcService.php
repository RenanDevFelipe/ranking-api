<?php

namespace App\Services;

use App\Models\IxcConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class IxcService
{
    private function client(IxcConfig $config): PendingRequest
    {
        $base64Token = base64_encode($config->token);
        return Http::withHeaders([
            'Authorization' => 'Basic ' . $base64Token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'ixcsoft' => 'listar',
        ])
        ->timeout(280)
        ->retry(2, 500);
    }

    private function consultar(IxcConfig $config, string $endpoint, array $payload): array
    {
        try {
            $baseUrl = rtrim($config->base_url, '/');

            $response = $this->client($config)
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
                return $this->formatarOrdemServico($config, $os);
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

    private function primeiroRegistro(?array $data): ?array
    {
        $registros = $this->extrairRegistros($data);

        return $registros[0] ?? null;
    }
}