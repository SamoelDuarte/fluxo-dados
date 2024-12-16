<?php

namespace App\Http\Controllers;

use App\Models\Carteira;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Carbon\Carbon;
use App\Models\Contrato;
use App\Models\Planilha;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Log;

class CronController extends Controller
{

    public function obterParcelamento(HttpRequest $request)
    {
        // Simular os dados do contrato, substitua isso com uma lógica real, como uma consulta ao banco de dados
        $pessoaCodigo = $request->input('pessoaCodigo');

        $carteiras = Carteira::all();


        foreach ($carteiras as $key => $carteira) {
            // Cria uma instância do cliente Guzzle


            $client = new Client();

            // Dados da requisição POST com as informações do contrato
            $data = [
                "codigoUsuarioCarteiraCobranca" => $carteira->codigo_usuario_cobranca, // Utilizando o relacionamento com a carteira
                "codigoCarteiraCobranca" => $carteira->id, // Obtendo o id da carteira associada ao contrato
                "pessoaCodigo" => $pessoaCodigo, // Documento do contrato (ajuste conforme necessário)
                "dataPrimeiraParcela" => Carbon::today()->toDateString(), // Utilizando a data de hoje
                "valorEntrada" => 0, // Defina o valor conforme necessário
                "chave" => "3cr1O35JfhQ8vBO", // Deixe a chave conforme necessária
                "renegociaSomenteDocumentosEmAtraso" => false // Deixe como false ou conforme necessário
            ];

            // Cabeçalhos da requisição
            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->gerarToken()
            ];

            try {
                // Envia a requisição POST com Guzzle
                $response = $client->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExternaTradicional/ObterOpcoesParcelamento', [
                    'json' => $data,
                    'headers' => $headers,
                ]);

                // Retorna o corpo da resposta
                $responseBody = $response->getBody();
                $responseData = json_decode($responseBody, true);

                // Verifica se o "parcelamento" é null
                if ($responseData[0]['parcelamento'] === null) {
                    // dd($carteira);
                    // Se "parcelamento" for null, continua para a próxima carteira
                    continue;
                }

                // Caso tenha um valor válido para "parcelamento", você pode parar o loop
                // ou processar a resposta
                $planilhaData['carteira'] = $carteira->id;
                break;  // Adiciona um break se quiser parar o loop ao encontrar uma resposta válida

            } catch (\Exception $e) {
                // Lida com possíveis exceções
                Log::error('Erro ao fazer requisição Guzzle: ' . $e->getMessage());
            }
        }


        // dd($responseData);
        // Processar os dados de parcelamento
        $ultimoArray = end($responseData);


        $planilhaData['valor_atualizado'] = $ultimoArray['valorDivida'];
        $planilhaData['valorTotalOriginal'] = $ultimoArray['valorTotalOriginal'];
        $planilhaData['valor_proposta_1'] = $ultimoArray['parcelamento'][0]['valorTotal'];
        $planilhaData['data_vencimento_proposta_1'] = Carbon::now()->addDay()->format('d/m/Y');

        // Inicializa as variáveis para armazenar o penúltimo índice
        $penultimoParcela = null;
        $encontrouParcelaMenor170 = false;  // Flag para verificar se encontramos parcela menor que 170

        // foreach ($ultimoArray['parcelamento'] as $index => $item) {
        //     // Verifica se o valor da parcela é menor que 170
        //     if ($item['valorParcela'] < 170) {
        //         $encontrouParcelaMenor170 = true;
        //         $indiceParcela = array_search($item['parcelas'], array_column($ultimoArray['parcelamento'], 'parcelas'));
        //         $penultimoParcela = $ultimoArray['parcelamento'][$indiceParcela - 1];
        //         $planilhaData['quantidade_parcelas_proposta_2'] = $penultimoParcela['parcelas'];
        //         $planilhaData['valor_proposta_2'] = $penultimoParcela['valorParcela'];
        //         $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
        //         break;
        //     }
        // }


        foreach ($ultimoArray['parcelamento'] as $index => $item) {
            // Verifica se o valor da parcela é menor que 170
            if ($item['valorParcela'] < 170) {
                $encontrouParcelaMenor170 = true;

                $indiceParcela = array_search($item['parcelas'], array_column($ultimoArray['parcelamento'], 'parcelas'));

                // Verifica se existe uma parcela anterior
                if ($indiceParcela > 0) {
                    $penultimoParcela = $ultimoArray['parcelamento'][$indiceParcela - 1];

                    $planilhaData['quantidade_parcelas_proposta_2'] = $penultimoParcela['parcelas'];
                    $planilhaData['valor_proposta_2'] = $penultimoParcela['valorParcela'];
                    $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
                } else {
                    // Caso não exista parcela anterior, trate o cenário conforme a necessidade
                    $planilhaData['quantidade_parcelas_proposta_2'] = $ultimoArray['parcelamento'][0]['parcelas'];
                    $planilhaData['valor_proposta_2'] = $ultimoArray['parcelamento'][0]['valorParcela'];
                    $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
                }

                break;
            }
        }


        // Caso não tenha encontrado nenhuma parcela abaixo de 170, seleciona o penúltimo item
        if (!$encontrouParcelaMenor170 && count($ultimoArray['parcelamento']) > 1) {
            $penultimoParcela = $ultimoArray['parcelamento'][count($ultimoArray['parcelamento']) - 1]; // Penúltima parcela
            $planilhaData['quantidade_parcelas_proposta_2'] = $penultimoParcela['parcelas'];
            $planilhaData['valor_proposta_2'] = $penultimoParcela['valorParcela'];
            $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
        }


        // dd($planilhaData);
        // Adicionar o sucesso ao array de resultados
        $resultados[] = [
            'contrato_id' => $pessoaCodigo,
            'parcelamento' => 'sucess'
        ];

        // Simular os dados de parcelamento
        $dadosParcelamento = [
            [
                'valorTotalOriginal' => $planilhaData['valorTotalOriginal'],
                'valorTotalAtualizado' => $planilhaData['valor_atualizado'],
                'opcoesPagamento' => [
                    ['valorTotal' => $planilhaData['valor_proposta_1']], // Melhor opção de pagamento
                ],
            ],
        ];

        $dadosParcelamento['opcoesPagamento'] = [];
        // dd($responseData[0]);
        // Itera sobre os dados de resposta
        foreach ($responseData[0]['parcelamento'] as $opcao) {
            // Verifica se a quantidade de parcelas é menor ou igual a 12 e o valor da parcela é menor que 170
            if ($opcao['parcelas'] < 12 && $opcao['valorParcela'] > 170) {
                $dadosParcelamento['opcoesPagamento'][] = [
                    'parcelas' => $opcao['parcelas'],
                    'valorParcela' => $opcao['valorParcela'],
                    'dataVencimento' => now()->addMonth()->toDateString(), // Adiciona a data de vencimento
                    'valorTotal' => $opcao['valorTotal'],
                    'hash' => $opcao['hash'],
                ];
            }
        }

        // Caso não haja dados de parcelamento
        if (empty($dadosParcelamento)) {
            return response()->json([
                'data' => [], // Nenhuma informação disponível
                'carteira' => '1', // Fictício, ajuste conforme necessário
            ]);
        }

        // Retornar os dados no formato esperado
        return response()->json([
            'data' => $dadosParcelamento,
            'carteira' => '1', // Ajuste conforme necessário
        ]);
    }

    public function obterOpcoesParcelamento_()
    {
        // Buscar até 100 contratos que tenham 'request' igual a 0
        $contratos = Contrato::where('request', 0)
            ->limit(20)
            ->get();

        // Verificar se algum contrato foi encontrado
        if ($contratos->isEmpty()) {
            return response()->json(['error' => 'Nenhum contrato encontrado com request igual a 0'], 404);
        }

        $resultados = [];  // Array para armazenar os resultados de cada contrato
        $erros = [];  // Array para armazenar os erros de cada contrato

        // Iterar sobre os contratos
        foreach ($contratos as $contrato) {
            // Receber os dados do cliente
            $dadosCliente = $this->obterDadosCliente($contrato->documento);

            // Procurar pelo índice do array com "IdGrupo" igual a 1582
            $indice = null;
            foreach ($dadosCliente as $index => $item) {
                if (isset($item['IdGrupo']) && $item['IdGrupo'] === 1582) {
                    $indice = $index;
                    break;
                }
            }

            // Se o índice foi encontrado, processar os dados
            if (!is_null($indice)) {
                $cliente = $dadosCliente[$indice];

                // Inserir dados na tabela planilhas
                $planilhaData = [
                    'contrato_id' => $contrato->id,
                    'empresa' => 'Neocob', // Insira a empresa aqui, se necessário
                ];

                // Limitar para 10 telefones (caso existam mais)
                $telefones = array_slice($cliente['Telefones'], 0, 10);
                foreach ($telefones as $index => $telefone) {
                    $planilhaData["dddtelefone_" . ($index + 1)] = trim($telefone['Ddd']) . trim($telefone['Fone']);
                }

                // Cria uma instância do cliente Guzzle
                $client = new Client();
                // Dados da requisição POST com as informações do contrato
                $data = [
                    "codigoUsuarioCarteiraCobranca" => $contrato->carteira->codigo_usuario_cobranca, // Utilizando o relacionamento com a carteira
                    "codigoCarteiraCobranca" => $contrato->carteira_id, // Obtendo o id da carteira associada ao contrato
                    "pessoaCodigo" => $contrato->contrato, // Documento do contrato (ajuste conforme necessário)
                    "dataPrimeiraParcela" => Carbon::today()->toDateString(), // Utilizando a data de hoje
                    "valorEntrada" => 0, // Defina o valor conforme necessário
                    "chave" => "3cr1O35JfhQ8vBO", // Deixe a chave conforme necessária
                    "renegociaSomenteDocumentosEmAtraso" => false // Deixe como false ou conforme necessário
                ];

                // Cabeçalhos da requisição
                $headers = [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->gerarToken()
                ];

                try {
                    // Envia a requisição POST com Guzzle
                    $response = $client->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExternaTradicional/ObterOpcoesParcelamento', [
                        'json' => $data,
                        'headers' => $headers,
                    ]);

                    // Retorna o corpo da resposta
                    $responseBody = $response->getBody();
                    $responseData = json_decode($responseBody, true);
                    // Verificar se o "parcelamento" é null e se a mensagem de erro está presente
                    if ($responseData[0]['messagem'] != "") {
                        // Se a mensagem de erro for encontrada, atualize o contrato com erro
                        $contrato->erro = 1;
                        $contrato->request = 1;
                        $contrato->mensagem_erro = $responseData[0]['messagem'];
                        $contrato->save();
                        $erroMensagem = $responseData[0]['messagem'] ?? 'Erro desconhecido';
                        $erros[] = [
                            'contrato_id' => $contrato->id,
                            'error' => $erroMensagem,
                            'details' => $responseData
                        ];
                        continue; // Continuar com o próximo contrato
                    }

                    // Processar os dados de parcelamento
                    $ultimoArray = end($responseData);


                    $planilhaData['valor_atualizado'] = $ultimoArray['valorDivida'];
                    $planilhaData['valor_proposta_1'] = $ultimoArray['parcelamento'][0]['valorTotal'];
                    $planilhaData['data_vencimento_proposta_1'] = Carbon::now()->addDay()->format('d/m/Y');

                    // Inicializa as variáveis para armazenar o penúltimo índice
                    $penultimoParcela = null;
                    $encontrouParcelaMenor170 = false;  // Flag para verificar se encontramos parcela menor que 170

                    // foreach ($ultimoArray['parcelamento'] as $index => $item) {
                    //     // Verifica se o valor da parcela é menor que 170
                    //     if ($item['valorParcela'] < 170) {
                    //         $encontrouParcelaMenor170 = true;
                    //         $indiceParcela = array_search($item['parcelas'], array_column($ultimoArray['parcelamento'], 'parcelas'));
                    //         $penultimoParcela = $ultimoArray['parcelamento'][$indiceParcela - 1];
                    //         $planilhaData['quantidade_parcelas_proposta_2'] = $penultimoParcela['parcelas'];
                    //         $planilhaData['valor_proposta_2'] = $penultimoParcela['valorParcela'];
                    //         $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
                    //         break;
                    //     }
                    // }


                    foreach ($ultimoArray['parcelamento'] as $index => $item) {
                        // Verifica se o valor da parcela é menor que 170
                        if ($item['valorParcela'] < 170) {
                            $encontrouParcelaMenor170 = true;

                            $indiceParcela = array_search($item['parcelas'], array_column($ultimoArray['parcelamento'], 'parcelas'));

                            // Verifica se existe uma parcela anterior
                            if ($indiceParcela > 0) {
                                $penultimoParcela = $ultimoArray['parcelamento'][$indiceParcela - 1];

                                $planilhaData['quantidade_parcelas_proposta_2'] = $penultimoParcela['parcelas'];
                                $planilhaData['valor_proposta_2'] = $penultimoParcela['valorParcela'];
                                $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
                            } else {
                                // Caso não exista parcela anterior, trate o cenário conforme a necessidade
                                $planilhaData['quantidade_parcelas_proposta_2'] = $ultimoArray['parcelamento'][0]['parcelas'];
                                $planilhaData['valor_proposta_2'] = $ultimoArray['parcelamento'][0]['valorParcela'];
                                $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
                            }

                            break;
                        }
                    }


                    // Caso não tenha encontrado nenhuma parcela abaixo de 170, seleciona o penúltimo item
                    if (!$encontrouParcelaMenor170 && count($ultimoArray['parcelamento']) > 1) {
                        $penultimoParcela = $ultimoArray['parcelamento'][count($ultimoArray['parcelamento']) - 1]; // Penúltima parcela
                        $planilhaData['quantidade_parcelas_proposta_2'] = $penultimoParcela['parcelas'];
                        $planilhaData['valor_proposta_2'] = $penultimoParcela['valorParcela'];
                        $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
                    }



                    // Atualizar o contrato após o processamento
                    $contrato->request = 1;
                    $contrato->save();

                    // Criar uma nova entrada na tabela planilhas
                    Planilha::create($planilhaData);

                    // Adicionar o sucesso ao array de resultados
                    $resultados[] = [
                        'contrato_id' => $contrato->id,
                        'parcelamento' => 'sucess'
                    ];
                } catch (RequestException $e) {
                    // Caso haja erro, adicionar à lista de erros
                    $erros[] = [
                        'contrato_id' => $contrato->id,
                        'error' => 'Erro na requisição: ' . $e->getMessage()
                    ];
                }
            } else {
                $erros[] = [
                    'contrato_id' => $contrato->id,
                    'error' => 'Nenhum dado encontrado com IdGrupo 1582'
                ];
            }
        }

        // Retornar os resultados ao final
        return response()->json([
            'resultados' => $resultados,
            'erros' => $erros
        ], 200);
    }

    function gerarToken()
    {
        // Inicializa a sessão cURL
        $curl = curl_init();

        // Configurações da requisição cURL
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://cobrancaexternaauthapi.apps.havan.com.br/token', // URL do endpoint de autenticação
            CURLOPT_RETURNTRANSFER => true, // Retorna a resposta como string
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST', // Tipo da requisição
            CURLOPT_POSTFIELDS => 'grant_type=password&client_id=bd210e1b-dac2-49b0-a9c4-7c5e1b0b241f&username=THF&password=3cr1O35JfhQ8vBO', // Parâmetros do POST
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded' // Tipo de conteúdo
            ),
        ));

        // Executa a requisição e captura a resposta
        $response = curl_exec($curl);

        // Verifica se ocorreu erro na requisição cURL
        if ($response === false) {
            echo 'Erro cURL: ' . curl_error($curl);
            return null;
        }

        // Fecha a sessão cURL
        curl_close($curl);

        // Converte a resposta JSON para um array PHP
        $responseData = json_decode($response, true);

        // Verifica se a resposta contém o token
        if (isset($responseData['access_token'])) {
            return $responseData['access_token'];
        } else {
            echo 'Erro ao obter o token: ' . $response;
            return null;
        }
    }

    public function obterDadosCliente($cpfCnpj)
    {
        // Instância do Guzzle Client
        $client = new Client();

        // dd($this->geraTokenDATACOB());
        // Cabeçalhos da requisição
        $headers = [
            'apiKey' => 'PYBW+7AndDA=',
            'Authorization' => 'Bearer ' . $this->geraTokenDATACOB(),
        ];

        // URL da API com o CPF/CNPJ dinâmico
        $url = 'http://datacob.thiagofarias.adv.br/api/dados-cadastrais/v1?cpfCnpj=' . urlencode($cpfCnpj);

        try {
            // Criação do objeto Request
            $request = new Request('GET', $url, $headers);

            // Envio da requisição de forma síncrona
            $response = $client->send($request);

            // Verifica se a resposta é bem-sucedida
            if ($response->getStatusCode() === 200) {
                // Retorna o corpo da resposta como JSON decodificado
                return json_decode($response->getBody(), true);
            } else {
                // Retorna erro caso o código de status não seja 200
                return response()->json([
                    'error' => 'Erro na requisição. Código de status: ' . $response->getStatusCode(),
                    'body' => $response->getBody()->getContents(),
                ], $response->getStatusCode());
            }
        } catch (RequestException $e) {
            // Tratamento de erro com resposta
            if ($e->hasResponse()) {
                $errorResponse = $e->getResponse();
                return response()->json([
                    'error' => 'Erro na requisição: ' . $errorResponse->getBody()->getContents(),
                    'status_code' => $errorResponse->getStatusCode(),
                ], $errorResponse->getStatusCode());
            }

            // Tratamento de erro genérico
            return response()->json([
                'error' => 'Erro desconhecido ao processar a requisição.',
                'exception' => $e->getMessage(),
            ], 500);
        }
    }

    public function geraTokenDATACOB()
    {
        // Criação do cliente Guzzle
        $client = new Client();

        // Cabeçalhos da requisição
        $headers = [
            'Content-Type' => 'application/json',
        ];

        // Corpo da requisição em JSON
        $body = json_encode([
            "Login" => "api.dashboard",
            "Password" => "36810556",
            "ApiKey" => "PYBW+7AndDA=",
        ]);

        // URL da API
        $url = 'http://datacob.thiagofarias.adv.br/api/account/v1/login';

        try {
            // Criação do objeto Request
            $request = new Request('POST', $url, $headers, $body);

            // Envio da requisição de forma síncrona
            $response = $client->send($request);

            // Verificar o código de status da resposta
            if ($response->getStatusCode() === 200) {
                // Retornar o corpo da resposta como JSON decodificado
                return json_decode($response->getBody(), true)['access_token'];
            } else {
                // Retornar erro caso o código de status não seja 200
                return response()->json([
                    'error' => 'Erro na requisição. Código de status: ' . $response->getStatusCode(),
                    'body' => $response->getBody()->getContents(),
                ], $response->getStatusCode());
            }
        } catch (RequestException $e) {
            // Tratamento de erro com resposta
            if ($e->hasResponse()) {
                $errorResponse = $e->getResponse();
                return response()->json([
                    'error' => 'Erro na requisição: ' . $errorResponse->getBody()->getContents(),
                    'status_code' => $errorResponse->getStatusCode(),
                ], $errorResponse->getStatusCode());
            }

            // Tratamento de erro genérico
            return response()->json([
                'error' => 'Erro desconhecido ao processar a requisição.',
                'exception' => $e->getMessage(),
            ], 500);
        }
    }

    public function obterDadosEAtualizarContratos()
    {
        // Buscar até 100 contratos que tenham 'request' igual a 0
        $contratos = Contrato::where('request', 0)
            ->limit(500)
            ->get();

        if ($contratos->isEmpty()) {
            return response()->json(['error' => 'Nenhum contrato encontrado com request igual a 0'], 404);
        }

        $erros = [];
        foreach ($contratos as $contrato) {
            // Receber os dados do cliente
            $dadosCliente = $this->obterDadosCliente($contrato->documento);

            // Procurar pelo índice do array com "IdGrupo" igual a 1582
            $indice = null;
            foreach ($dadosCliente as $index => $item) {
                if (isset($item['IdGrupo']) && $item['IdGrupo'] === 1582) {
                    $indice = $index;
                    break;
                }
            }

            if (!is_null($indice)) {
                $cliente = $dadosCliente[$indice];
                // Salvar os dados relevantes no banco
                $planilhaData = [
                    'contrato_id' => $contrato->id,
                    'empresa' => 'Neocob',
                ];

                // Limitar para 10 telefones (caso existam mais)
                $telefones = array_slice($cliente['Telefones'], 0, 10);
                foreach ($telefones as $index => $telefone) {
                    $planilhaData["dddtelefone_" . ($index + 1)] = trim($telefone['Ddd']) . trim($telefone['Fone']);
                }

                Planilha::create($planilhaData);

                // Atualizar o contrato para indicar que os dados foram salvos
                $contrato->request = 1;
                $contrato->save();
            } else {
                $erros[] = [
                    'contrato_id' => $contrato->id,
                    'error' => 'Nenhum dado encontrado com IdGrupo 1582'
                ];
            }
        }

        return response()->json([
            'message' => 'Dados dos clientes processados.',
            'erros' => $erros
        ], 200);
    }

    public function obterOpcoesParcelamento()
    {
        // Buscar até 20 planilhas que tenham 'valor_proposta_1' como null
        // e onde os contratos relacionados tenham request == 1 e erro == 0
        $planilhas = Planilha::with('contrato')
            ->whereNull('valor_proposta_1')
            ->whereHas('contrato', function ($query) {
                $query->where('request', 1)
                    ->where('erro', 0);
            })
            ->limit(80) // Limitar para 20 registros
            ->get();

        $resultados = [];
        $erros = [];

        foreach ($planilhas as $planilha) {
            // Buscar o contrato associado à planilha
            $contrato = Contrato::find($planilha->contrato_id);
            if (!$contrato) {
                $erros[] = [
                    'planilha_id' => $planilha->id,
                    'error' => 'Contrato não encontrado para a planilha.',
                ];
                continue;
            }

            // Dados da requisição POST
            $data = [
                "codigoUsuarioCarteiraCobranca" => $contrato->carteira->codigo_usuario_cobranca,
                "codigoCarteiraCobranca" => $contrato->carteira_id,
                "pessoaCodigo" => $contrato->contrato,
                "dataPrimeiraParcela" => Carbon::today()->toDateString(),
                "valorEntrada" => 0,
                "chave" => "3cr1O35JfhQ8vBO",
                "renegociaSomenteDocumentosEmAtraso" => false,
            ];

            // Cabeçalhos da requisição
            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->gerarToken(),
            ];

            $client = new Client();

            try {
                // Enviar a requisição
                $response = $client->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExternaTradicional/ObterOpcoesParcelamento', [
                    'json' => $data,
                    'headers' => $headers,
                ]);

                $responseBody = json_decode($response->getBody(), true);

                // Verificar erros na resposta
                if (!empty($responseBody[0]['messagem'])) {
                    $contrato->update([
                        'erro' => 1,
                        'mensagem_erro' => $responseBody[0]['messagem'],
                    ]);
                    $erros[] = [
                        'contrato_id' => $contrato->id,
                        'error' => $responseBody[0]['messagem'],
                    ];
                    continue;
                }

                $ultimoArray = end($responseBody);
                $parcelamentos = $ultimoArray['parcelamento'] ?? [];
                $encontrouParcelaMenor170 = false;
                $penultimoParcela = null;

                // Garantir que o array de parcelamento não esteja vazio
                if (!empty($parcelamentos)) {
                    $parcelamentos = array_slice($parcelamentos, 0, 12);
                    foreach ($parcelamentos as $index => $item) {
                        // Verifica se o valor da parcela é menor que 170
                        if ($item['valorParcela'] < 170) {
                            $encontrouParcelaMenor170 = true;

                            // Localizar o índice da parcela atual
                            $indiceParcela = array_search($item['parcelas'], array_column($ultimoArray['parcelamento'], 'parcelas'));

                            // Garantir que o índice seja válido e acessar o penúltimo elemento
                            if ($indiceParcela !== false && $indiceParcela > 0) {
                                $penultimoParcela = $ultimoArray['parcelamento'][$indiceParcela - 1];
                                $planilhaData['quantidade_parcelas_proposta_2'] = $penultimoParcela['parcelas'];
                                $planilhaData['valor_proposta_2'] = $penultimoParcela['valorParcela'];
                                $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
                            } else {
                                // Se não houver "penúltima parcela", pegue o próprio item atual como fallback
                                $penultimoParcela = $item;
                                $planilhaData['quantidade_parcelas_proposta_2'] = $item['parcelas'];
                                $planilhaData['valor_proposta_2'] = $item['valorParcela'];
                                $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
                            }
                            break;
                        }
                    }
                    // Caso nenhuma parcela menor que 170 seja encontrada
                    if (!$encontrouParcelaMenor170) {
                        if (count($parcelamentos) > 1) {
                            $penultimoParcela = $parcelamentos[count($parcelamentos) - 1];
                        } else {
                            $penultimoParcela = $parcelamentos[0];
                        }
                    }
                    // $teste = [
                    //     'valor_atualizado' => $ultimoArray['valorDivida'],
                    //     'valor_proposta_1' => $parcelamentos[0]['valorTotal'] ?? null,
                    //     'data_vencimento_proposta_1' => Carbon::now()->addDay()->format('d/m/Y'),
                    //     'quantidade_parcelas_proposta_2' => $penultimoParcela['parcelas'] ?? null,
                    //     'valor_proposta_2' => $penultimoParcela['valorParcela'] ?? null,
                    //     'data_vencimento_proposta_2' => Carbon::now()->addDay()->format('d/m/Y'),
                    // ];
                    // dd($parcelamentos);
                    // Atualizar os dados da planilha
                    $planilha->update([
                        'valor_atualizado' => $ultimoArray['valorDivida'],
                        'valor_proposta_1' => $parcelamentos[0]['valorTotal'] ?? null,
                        'data_vencimento_proposta_1' => Carbon::now()->addDay()->format('d/m/Y'),
                        'quantidade_parcelas_proposta_2' => $penultimoParcela['parcelas'] ?? null,
                        'valor_proposta_2' => $penultimoParcela['valorParcela'] ?? null,
                        'data_vencimento_proposta_2' => Carbon::now()->addDay()->format('d/m/Y'),
                    ]);

                    $resultados[] = [
                        'contrato_id' => $contrato->id,
                        'planilha_id' => $planilha->id,
                        'parcelamento' => 'sucesso',
                    ];
                } else {
                    $erros[] = [
                        'contrato_id' => $contrato->id,
                        'planilha_id' => $planilha->id,
                        'error' => 'Array de parcelamento vazio ou inválido.',
                    ];
                }
            } catch (RequestException $e) {
                $erros[] = [
                    'contrato_id' => $contrato->id,
                    'planilha_id' => $planilha->id,
                    'error' => 'Erro na requisição: ' . $e->getMessage(),
                ];
            }
        }

        // Retornar os resultados
        return response()->json([
            'resultados' => $resultados,
            'erros' => $erros,
        ], 200);
    }

    public function obterOpcoesParcelamento2()
    {
        // Buscar até 20 planilhas que tenham 'valor_proposta_1' como null
        // e onde os contratos relacionados tenham request == 1 e erro == 0
        $planilhas = Planilha::with('contrato')
            ->whereNull('valor_proposta_1')
            ->whereHas('contrato', function ($query) {
                $query->where('request', 1)
                    ->where('erro', 0);
            })
            ->orderBy('id', 'desc') // Ordenar pela coluna 'id' em ordem decrescente
            ->limit(80) // Limitar para 80 registros
            ->get();

        $resultados = [];
        $erros = [];

        foreach ($planilhas as $planilha) {
            // Buscar o contrato associado à planilha
            $contrato = Contrato::find($planilha->contrato_id);
            if (!$contrato) {
                $erros[] = [
                    'planilha_id' => $planilha->id,
                    'error' => 'Contrato não encontrado para a planilha.',
                ];
                continue;
            }

            // Dados da requisição POST
            $data = [
                "codigoUsuarioCarteiraCobranca" => $contrato->carteira->codigo_usuario_cobranca,
                "codigoCarteiraCobranca" => $contrato->carteira_id,
                "pessoaCodigo" => $contrato->contrato,
                "dataPrimeiraParcela" => Carbon::today()->toDateString(),
                "valorEntrada" => 0,
                "chave" => "3cr1O35JfhQ8vBO",
                "renegociaSomenteDocumentosEmAtraso" => false,
            ];

            // Cabeçalhos da requisição
            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->gerarToken(),
            ];

            $client = new Client();

            try {
                // Enviar a requisição
                $response = $client->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExternaTradicional/ObterOpcoesParcelamento', [
                    'json' => $data,
                    'headers' => $headers,
                ]);

                $responseBody = json_decode($response->getBody(), true);

                // Verificar erros na resposta
                if (!empty($responseBody[0]['messagem'])) {
                    $contrato->update([
                        'erro' => 1,
                        'mensagem_erro' => $responseBody[0]['messagem'],
                    ]);
                    $erros[] = [
                        'contrato_id' => $contrato->id,
                        'error' => $responseBody[0]['messagem'],
                    ];
                    continue;
                }

                $ultimoArray = end($responseBody);
                $parcelamentos = $ultimoArray['parcelamento'] ?? [];
                $encontrouParcelaMenor170 = false;
                $penultimoParcela = null;

                // Garantir que o array de parcelamento não esteja vazio
                if (!empty($parcelamentos)) {
                    $parcelamentos = array_slice($parcelamentos, 0, 12);
                    foreach ($parcelamentos as $index => $item) {
                        // Verifica se o valor da parcela é menor que 170
                        if ($item['valorParcela'] < 170) {
                            $encontrouParcelaMenor170 = true;

                            // Localizar o índice da parcela atual
                            $indiceParcela = array_search($item['parcelas'], array_column($ultimoArray['parcelamento'], 'parcelas'));

                            // Garantir que o índice seja válido e acessar o penúltimo elemento
                            if ($indiceParcela !== false && $indiceParcela > 0) {
                                $penultimoParcela = $ultimoArray['parcelamento'][$indiceParcela - 1];
                                $planilhaData['quantidade_parcelas_proposta_2'] = $penultimoParcela['parcelas'];
                                $planilhaData['valor_proposta_2'] = $penultimoParcela['valorParcela'];
                                $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
                            } else {
                                // Se não houver "penúltima parcela", pegue o próprio item atual como fallback
                                $penultimoParcela = $item;
                                $planilhaData['quantidade_parcelas_proposta_2'] = $item['parcelas'];
                                $planilhaData['valor_proposta_2'] = $item['valorParcela'];
                                $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
                            }
                            break;
                        }
                    }

                    // Caso nenhuma parcela menor que 170 seja encontrada
                    if (!$encontrouParcelaMenor170) {
                        if (count($parcelamentos) > 1) {
                            $penultimoParcela = $parcelamentos[count($parcelamentos) - 1];
                        } else {
                            $penultimoParcela = $parcelamentos[0];
                        }
                    }
                    // $teste = [
                    //     'valor_atualizado' => $ultimoArray['valorDivida'],
                    //     'valor_proposta_1' => $parcelamentos[0]['valorTotal'] ?? null,
                    //     'data_vencimento_proposta_1' => Carbon::now()->addDay()->format('d/m/Y'),
                    //     'quantidade_parcelas_proposta_2' => $penultimoParcela['parcelas'] ?? null,
                    //     'valor_proposta_2' => $penultimoParcela['valorParcela'] ?? null,
                    //     'data_vencimento_proposta_2' => Carbon::now()->addDay()->format('d/m/Y'),
                    // ];
                    // dd($parcelamentos);
                    // Atualizar os dados da planilha
                    $planilha->update([
                        'valor_atualizado' => $ultimoArray['valorDivida'],
                        'valor_proposta_1' => $parcelamentos[0]['valorTotal'] ?? null,
                        'data_vencimento_proposta_1' => Carbon::now()->addDay()->format('d/m/Y'),
                        'quantidade_parcelas_proposta_2' => $penultimoParcela['parcelas'] ?? null,
                        'valor_proposta_2' => $penultimoParcela['valorParcela'] ?? null,
                        'data_vencimento_proposta_2' => Carbon::now()->addDay()->format('d/m/Y'),
                    ]);

                    $resultados[] = [
                        'contrato_id' => $contrato->id,
                        'planilha_id' => $planilha->id,
                        'parcelamento' => 'sucesso',
                    ];
                } else {
                    $erros[] = [
                        'contrato_id' => $contrato->id,
                        'planilha_id' => $planilha->id,
                        'error' => 'Array de parcelamento vazio ou inválido.',
                    ];
                }
            } catch (RequestException $e) {
                $erros[] = [
                    'contrato_id' => $contrato->id,
                    'planilha_id' => $planilha->id,
                    'error' => 'Erro na requisição: ' . $e->getMessage(),
                ];
            }
        }

        // Retornar os resultados
        return response()->json([
            'resultados' => $resultados,
            'erros' => $erros,
        ], 200);
    }

    public function obterOpcoesParcelamento3()
    {

        $planilhas = Planilha::with('contrato')
            ->whereNull('valor_proposta_1')
            ->whereHas('contrato', function ($query) {
                $query->where('request', 1)
                    ->where('erro', 0);
            })
            ->orderBy('id', 'desc') // Ordenar pela coluna 'id' em ordem decrescente
            ->get(); // Sem limite para pegar todos os IDs

        // Obtendo o maior e o menor ID
        $maiorId = $planilhas->max('id');
        $menorId = $planilhas->min('id');

        // Calculando a metade
        $metadeId = intval(($maiorId + $menorId) / 2);


        $planilhasAcima = Planilha::with('contrato')
            ->whereNull('valor_proposta_1')
            ->where('id', '>', $metadeId) // IDs acima da metade
            ->whereHas('contrato', function ($query) {
                $query->where('request', 1)
                    ->where('erro', 0);
            })
            ->orderBy('id', 'asc') // Ordem crescente
            ->limit(80) // Limitar para 80 registros
            ->get();

        $resultados = [];
        $erros = [];

        foreach ($planilhasAcima as $planilha) {
            // Buscar o contrato associado à planilha
            $contrato = Contrato::find($planilha->contrato_id);
            if (!$contrato) {
                $erros[] = [
                    'planilha_id' => $planilha->id,
                    'error' => 'Contrato não encontrado para a planilha.',
                ];
                continue;
            }

            // Dados da requisição POST
            $data = [
                "codigoUsuarioCarteiraCobranca" => $contrato->carteira->codigo_usuario_cobranca,
                "codigoCarteiraCobranca" => $contrato->carteira_id,
                "pessoaCodigo" => $contrato->contrato,
                "dataPrimeiraParcela" => Carbon::today()->toDateString(),
                "valorEntrada" => 0,
                "chave" => "3cr1O35JfhQ8vBO",
                "renegociaSomenteDocumentosEmAtraso" => false,
            ];

            // Cabeçalhos da requisição
            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->gerarToken(),
            ];

            $client = new Client();

            try {
                // Enviar a requisição
                $response = $client->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExternaTradicional/ObterOpcoesParcelamento', [
                    'json' => $data,
                    'headers' => $headers,
                ]);

                $responseBody = json_decode($response->getBody(), true);

                // Verificar erros na resposta
                if (!empty($responseBody[0]['messagem'])) {
                    $contrato->update([
                        'erro' => 1,
                        'mensagem_erro' => $responseBody[0]['messagem'],
                    ]);
                    $erros[] = [
                        'contrato_id' => $contrato->id,
                        'error' => $responseBody[0]['messagem'],
                    ];
                    continue;
                }

                $ultimoArray = end($responseBody);
                $parcelamentos = $ultimoArray['parcelamento'] ?? [];
                $encontrouParcelaMenor170 = false;
                $penultimoParcela = null;

                // Garantir que o array de parcelamento não esteja vazio
                if (!empty($parcelamentos)) {
                    $parcelamentos = array_slice($parcelamentos, 0, 12);
                    foreach ($parcelamentos as $index => $item) {
                        // Verifica se o valor da parcela é menor que 170
                        if ($item['valorParcela'] < 170) {
                            $encontrouParcelaMenor170 = true;

                            // Localizar o índice da parcela atual
                            $indiceParcela = array_search($item['parcelas'], array_column($ultimoArray['parcelamento'], 'parcelas'));

                            // Garantir que o índice seja válido e acessar o penúltimo elemento
                            if ($indiceParcela !== false && $indiceParcela > 0) {
                                $penultimoParcela = $ultimoArray['parcelamento'][$indiceParcela - 1];
                                $planilhaData['quantidade_parcelas_proposta_2'] = $penultimoParcela['parcelas'];
                                $planilhaData['valor_proposta_2'] = $penultimoParcela['valorParcela'];
                                $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
                            } else {
                                // Se não houver "penúltima parcela", pegue o próprio item atual como fallback
                                $penultimoParcela = $item;
                                $planilhaData['quantidade_parcelas_proposta_2'] = $item['parcelas'];
                                $planilhaData['valor_proposta_2'] = $item['valorParcela'];
                                $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
                            }
                            break;
                        }
                    }

                    // Caso nenhuma parcela menor que 170 seja encontrada
                    if (!$encontrouParcelaMenor170) {
                        if (count($parcelamentos) > 1) {
                            $penultimoParcela = $parcelamentos[count($parcelamentos) - 1];
                        } else {
                            $penultimoParcela = $parcelamentos[0];
                        }
                    }
                    // $teste = [
                    //     'valor_atualizado' => $ultimoArray['valorDivida'],
                    //     'valor_proposta_1' => $parcelamentos[0]['valorTotal'] ?? null,
                    //     'data_vencimento_proposta_1' => Carbon::now()->addDay()->format('d/m/Y'),
                    //     'quantidade_parcelas_proposta_2' => $penultimoParcela['parcelas'] ?? null,
                    //     'valor_proposta_2' => $penultimoParcela['valorParcela'] ?? null,
                    //     'data_vencimento_proposta_2' => Carbon::now()->addDay()->format('d/m/Y'),
                    // ];
                    // dd($parcelamentos);
                    // Atualizar os dados da planilha
                    $planilha->update([
                        'valor_atualizado' => $ultimoArray['valorDivida'],
                        'valor_proposta_1' => $parcelamentos[0]['valorTotal'] ?? null,
                        'data_vencimento_proposta_1' => Carbon::now()->addDay()->format('d/m/Y'),
                        'quantidade_parcelas_proposta_2' => $penultimoParcela['parcelas'] ?? null,
                        'valor_proposta_2' => $penultimoParcela['valorParcela'] ?? null,
                        'data_vencimento_proposta_2' => Carbon::now()->addDay()->format('d/m/Y'),
                    ]);

                    $resultados[] = [
                        'contrato_id' => $contrato->id,
                        'planilha_id' => $planilha->id,
                        'parcelamento' => 'sucesso',
                    ];
                } else {
                    $erros[] = [
                        'contrato_id' => $contrato->id,
                        'planilha_id' => $planilha->id,
                        'error' => 'Array de parcelamento vazio ou inválido.',
                    ];
                }
            } catch (RequestException $e) {
                $erros[] = [
                    'contrato_id' => $contrato->id,
                    'planilha_id' => $planilha->id,
                    'error' => 'Erro na requisição: ' . $e->getMessage(),
                ];
            }
        }

        // Retornar os resultados
        return response()->json([
            'resultados' => $resultados,
            'erros' => $erros,
        ], 200);
    }

    public function obterOpcoesParcelamento4()
    {

        $planilhas = Planilha::with('contrato')
            ->whereNull('valor_proposta_1')
            ->whereHas('contrato', function ($query) {
                $query->where('request', 1)
                    ->where('erro', 0);
            })
            ->orderBy('id', 'desc') // Ordenar pela coluna 'id' em ordem decrescente
            ->get(); // Sem limite para pegar todos os IDs

        // Obtendo o maior e o menor ID
        $maiorId = $planilhas->max('id');
        $menorId = $planilhas->min('id');

        // Calculando a metade
        $metadeId = intval(($maiorId + $menorId) / 2);


        $planilhasAbaixo = Planilha::with('contrato')
            ->whereNull('valor_proposta_1')
            ->where('id', '<', $metadeId) // IDs abaixo da metade
            ->whereHas('contrato', function ($query) {
                $query->where('request', 1)
                    ->where('erro', 0);
            })
            ->orderBy('id', 'desc') // Ordem decrescente
            ->limit(80) // Limitar para 80 registros
            ->get();


        $resultados = [];
        $erros = [];

        foreach ($planilhasAbaixo as $planilha) {
            // Buscar o contrato associado à planilha
            $contrato = Contrato::find($planilha->contrato_id);
            if (!$contrato) {
                $erros[] = [
                    'planilha_id' => $planilha->id,
                    'error' => 'Contrato não encontrado para a planilha.',
                ];
                continue;
            }

            // Dados da requisição POST
            $data = [
                "codigoUsuarioCarteiraCobranca" => $contrato->carteira->codigo_usuario_cobranca,
                "codigoCarteiraCobranca" => $contrato->carteira_id,
                "pessoaCodigo" => $contrato->contrato,
                "dataPrimeiraParcela" => Carbon::today()->toDateString(),
                "valorEntrada" => 0,
                "chave" => "3cr1O35JfhQ8vBO",
                "renegociaSomenteDocumentosEmAtraso" => false,
            ];

            // Cabeçalhos da requisição
            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->gerarToken(),
            ];

            $client = new Client();

            try {
                // Enviar a requisição
                $response = $client->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExternaTradicional/ObterOpcoesParcelamento', [
                    'json' => $data,
                    'headers' => $headers,
                ]);

                $responseBody = json_decode($response->getBody(), true);

                // Verificar erros na resposta
                if (!empty($responseBody[0]['messagem'])) {
                    $contrato->update([
                        'erro' => 1,
                        'mensagem_erro' => $responseBody[0]['messagem'],
                    ]);
                    $erros[] = [
                        'contrato_id' => $contrato->id,
                        'error' => $responseBody[0]['messagem'],
                    ];
                    continue;
                }

                $ultimoArray = end($responseBody);
                $parcelamentos = $ultimoArray['parcelamento'] ?? [];
                $encontrouParcelaMenor170 = false;
                $penultimoParcela = null;

                // Garantir que o array de parcelamento não esteja vazio
                if (!empty($parcelamentos)) {
                    $parcelamentos = array_slice($parcelamentos, 0, 12);
                    foreach ($parcelamentos as $index => $item) {
                        // Verifica se o valor da parcela é menor que 170
                        if ($item['valorParcela'] < 170) {
                            $encontrouParcelaMenor170 = true;

                            // Localizar o índice da parcela atual
                            $indiceParcela = array_search($item['parcelas'], array_column($ultimoArray['parcelamento'], 'parcelas'));

                            // Garantir que o índice seja válido e acessar o penúltimo elemento
                            if ($indiceParcela !== false && $indiceParcela > 0) {
                                $penultimoParcela = $ultimoArray['parcelamento'][$indiceParcela - 1];
                                $planilhaData['quantidade_parcelas_proposta_2'] = $penultimoParcela['parcelas'];
                                $planilhaData['valor_proposta_2'] = $penultimoParcela['valorParcela'];
                                $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
                            } else {
                                // Se não houver "penúltima parcela", pegue o próprio item atual como fallback
                                $penultimoParcela = $item;
                                $planilhaData['quantidade_parcelas_proposta_2'] = $item['parcelas'];
                                $planilhaData['valor_proposta_2'] = $item['valorParcela'];
                                $planilhaData['data_vencimento_proposta_2'] = Carbon::now()->addDay()->format('d/m/Y');
                            }
                            break;
                        }
                    }

                    // Caso nenhuma parcela menor que 170 seja encontrada
                    if (!$encontrouParcelaMenor170) {
                        if (count($parcelamentos) > 1) {
                            $penultimoParcela = $parcelamentos[count($parcelamentos) - 1];
                        } else {
                            $penultimoParcela = $parcelamentos[0];
                        }
                    }
                    // $teste = [
                    //     'valor_atualizado' => $ultimoArray['valorDivida'],
                    //     'valor_proposta_1' => $parcelamentos[0]['valorTotal'] ?? null,
                    //     'data_vencimento_proposta_1' => Carbon::now()->addDay()->format('d/m/Y'),
                    //     'quantidade_parcelas_proposta_2' => $penultimoParcela['parcelas'] ?? null,
                    //     'valor_proposta_2' => $penultimoParcela['valorParcela'] ?? null,
                    //     'data_vencimento_proposta_2' => Carbon::now()->addDay()->format('d/m/Y'),
                    // ];
                    // dd($parcelamentos);
                    // Atualizar os dados da planilha
                    $planilha->update([
                        'valor_atualizado' => $ultimoArray['valorDivida'],
                        'valor_proposta_1' => $parcelamentos[0]['valorTotal'] ?? null,
                        'data_vencimento_proposta_1' => Carbon::now()->addDay()->format('d/m/Y'),
                        'quantidade_parcelas_proposta_2' => $penultimoParcela['parcelas'] ?? null,
                        'valor_proposta_2' => $penultimoParcela['valorParcela'] ?? null,
                        'data_vencimento_proposta_2' => Carbon::now()->addDay()->format('d/m/Y'),
                    ]);

                    $resultados[] = [
                        'contrato_id' => $contrato->id,
                        'planilha_id' => $planilha->id,
                        'parcelamento' => 'sucesso',
                    ];
                } else {
                    $erros[] = [
                        'contrato_id' => $contrato->id,
                        'planilha_id' => $planilha->id,
                        'error' => 'Array de parcelamento vazio ou inválido.',
                    ];
                }
            } catch (RequestException $e) {
                $erros[] = [
                    'contrato_id' => $contrato->id,
                    'planilha_id' => $planilha->id,
                    'error' => 'Erro na requisição: ' . $e->getMessage(),
                ];
            }
        }

        // Retornar os resultados
        return response()->json([
            'resultados' => $resultados,
            'erros' => $erros,
        ], 200);
    }
}
