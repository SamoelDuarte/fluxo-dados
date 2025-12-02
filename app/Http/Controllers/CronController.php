<?php

namespace App\Http\Controllers;

use App\Models\Carteira;
use App\Models\Contato;
use App\Models\ContatoDados;
use App\Models\Campanha;
use App\Models\ImagemCampanha;
use App\Models\WhatsappSession;
use App\Models\WhatsappContact;
use App\Models\Acordo;
use DB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Carbon\Carbon;
use App\Models\Contrato;
use App\Models\Planilha;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class CronController extends Controller
{

    public function getDadoHavan(HttpRequest $request)
    {

        $contato = ContatoDados::where('telefone', $request->input('telefone'))->first();


        // Simular os dados do contrato, substitua isso com uma lógica real, como uma consulta ao banco de dados
        $pessoaCodigo = $contato->cod_cliente;

        $planilhaData = [];

        switch ($contato->carteira) {
            case 875:
                $codigoUsuarioCarteiraCobranca = 30;
                break;

            case 874:
                $codigoUsuarioCarteiraCobranca = 24;
                break;

            case 873:
                $codigoUsuarioCarteiraCobranca = 24;
                break;

            case 872:
                $codigoUsuarioCarteiraCobranca = 24;
                break;

            case 871:
                $codigoUsuarioCarteiraCobranca = 24;
                break;

            case 870:
                $codigoUsuarioCarteiraCobranca = 24;
                break;

            default:
                $codigoUsuarioCarteiraCobranca = null; // ou outro valor padrão, se quiser
                break;
        }





        // Cria uma instância do cliente Guzzle
        $client = new Client();

        // Dados da requisição POST com as informações do contrato
        $data = [
            "codigoUsuarioCarteiraCobranca" => (string) $codigoUsuarioCarteiraCobranca, // Utilizando o relacionamento com a carteira
            "codigoCarteiraCobranca" => (string) $contato->carteira, // Obtendo o id da carteira associada ao contrato
            "pessoaCodigo" => $pessoaCodigo, // Documento do contrato (ajuste conforme necessário)
            "dataPrimeiraParcela" => Carbon::today()->toDateString(), // Utilizando a data de hoje
            "valorEntrada" => 0, // Defina o valor conforme necessário
            "chave" => "3cr1O35JfhQ8vBO", // Deixe a chave conforme necessária
            "renegociaSomenteDocumentosEmAtraso" => false // Deixe como false ou conforme necessário
        ];

        // dd($data);

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

            $responseData = json_decode($responseBody, associative: true);

            // Verifica se há mensagem de erro
            if (isset($responseData[0]['messagem']) && !empty($responseData[0]['messagem'])) {
                return response()->json(['error' => $responseData[0]['messagem']], 400);
            }

            // Verifica se o "parcelamento" é válido
            if (!isset($responseData[0]['parcelamento']) || $responseData[0]['parcelamento'] === null || empty($responseData[0]['parcelamento'])) {
                return response()->json(['error' => 'Nenhuma opção de parcelamento disponível para este contrato.'], 204);
            }

            $planilhaData['carteira'] = '869'; // fixed for now

        } catch (\Exception $e) {
            if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
                $response = $e->getResponse();
                dd('Erro na requisição: ' . $response->getStatusCode() . ' - ' . $response->getBody()->getContents());
            } else {
                dd('Erro geral: ' . $e->getMessage());
            }
            // Lida com possíveis exceções
            Log::error('Erro ao fazer requisição Guzzle: ' . $e->getMessage());
        }


        // dd($responseData);
        // Processar os dados de parcelamento
        $ultimoArray = end($responseData);
        if (!$ultimoArray || !isset($ultimoArray['parcelamento']) || !is_array($ultimoArray['parcelamento']) || empty($ultimoArray['parcelamento'])) {
            return response()->json(['error' => 'Nenhuma opção de parcelamento disponível para este contrato.'], 204);
        }

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
        foreach ($ultimoArray['parcelamento'] as $opcao) {
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
            'payload' => $data,
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

            // Se obterDadosCliente retornar um array com error or null, registre e continue
            if (is_null($dadosCliente) || (is_array($dadosCliente) && isset($dadosCliente['error']))) {
                $erroMensagem = is_array($dadosCliente) ? ($dadosCliente['error'] ?? 'Erro ao obter dados') : 'Erro ao obter dados (null)';
                $contrato->erro = 1;
                $contrato->request = 1;
                $contrato->mensagem_erro = $erroMensagem;
                $contrato->save();
                $erros[] = [
                    'contrato_id' => $contrato->id,
                    'error' => $erroMensagem,
                    'details' => $dadosCliente,
                ];
                continue;
            }

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
            echo 'Erro ao obter o token: ' . $responseData;
            return null;
        }
    }

    public function obterParcelamento(HttpRequest $request): JsonResponse
    {
        // Simular os dados do contrato, substitua isso com uma lógica real, como uma consulta ao banco de dados
        $pessoaCodigo = '12790584';

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
                dd('Erro ao fazer requisição Guzzle: ' . $e->getMessage());
                Log::error('Erro ao fazer requisição Guzzle: ' . $e->getMessage());
            }
        }



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
        foreach ($ultimoArray['parcelamento'] as $opcao) {
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

    public function obterDadosCliente($cpfCnpj)
    {
        // Instância do Guzzle Client
        $client = new Client();


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
                // Retorna array de erro (não um JsonResponse) para o chamador tratar
                return [
                    'error' => 'Erro na requisição. Código de status: ' . $response->getStatusCode(),
                    'body' => $response->getBody()->getContents(),
                    'status_code' => $response->getStatusCode(),
                ];
            }
        } catch (RequestException $e) {
            // Tratamento de erro com resposta
            if ($e->hasResponse()) {
                $errorResponse = $e->getResponse();
                return [
                    'error' => 'Erro na requisição: ' . $errorResponse->getBody()->getContents(),
                    'status_code' => $errorResponse->getStatusCode(),
                ];
            }

            // Tratamento de erro genérico
            return [
                'error' => 'Erro desconhecido ao processar a requisição.',
                'exception' => $e->getMessage(),
            ];
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
                // Retornar null para indicar falha ao obter token
                return null;
            }
        } catch (RequestException $e) {
            // Tratamento de erro com resposta
            if ($e->hasResponse()) {
                $errorResponse = $e->getResponse();
                return null;
            }

            // Tratamento de erro genérico
            return null;
        }
    }

    public function obterDadosEAtualizarContratos()
    {
        // Buscar até 100 contratos que tenham 'request' igual a 0
        $contratos = Contrato::where('request', 0)
            ->limit(50)
            ->get();

        if ($contratos->isEmpty()) {
            return response()->json(['error' => 'Nenhum contrato encontrado com request igual a 0'], 404);
        }

        $erros = [];
        foreach ($contratos as $contrato) {
            // Receber os dados do cliente
            $dadosCliente = $this->obterDadosCliente($contrato->documento);

            // If API returned an error structure or null, mark contrato and continue
            if (is_null($dadosCliente) && isset($dadosCliente['error'])) {
                $erroMensagem = is_array($dadosCliente) ? ($dadosCliente['error'] ?? 'Erro ao obter dados') : 'Erro ao obter dados (null)';
                $contrato->erro = 1;
                $contrato->request = 1;
                $contrato->mensagem_erro = $erroMensagem;
                $contrato->save();
                $erros[] = [
                    'contrato_id' => $contrato->id,
                    'error' => $erroMensagem,
                    'details' => $dadosCliente,
                ];
                continue;
            }
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
            ->limit(value: 80) // Limitar para 20 registros
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
        $planilhas = Planilha::with(relations: 'contrato')
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

    public function envioEmMassa()
    {
        $now = Carbon::now('America/Sao_Paulo');

        $daysOfWeek = [
            0 => 'domingo',
            1 => 'segunda',
            2 => 'terça',
            3 => 'quarta',
            4 => 'quinta',
            5 => 'sexta',
            6 => 'sábado',
        ];
        $dayOfWeek = $daysOfWeek[$now->dayOfWeek];
        $currentTime = $now->format('H:i:s');

        // Verifica horário disponível
        $exists = DB::table('available_slots')
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<=', $currentTime)
            ->where('end_time', '>=', $currentTime)
            ->exists();

        if (!$exists) {
            echo 'Fora de Data de Agendamento: ' . $currentTime;
            return;
        }

        // Busca o token da tabela whatsapp
        $whatsappConfig = DB::table('whatsapp')->first();
        if (!$whatsappConfig || !$whatsappConfig->access_token) {
            echo 'Token WhatsApp não configurado no banco de dados';
            return;
        }

        // Limpa espaços em branco do token
        $whatsappConfig->access_token = trim($whatsappConfig->access_token);
        Log::info('Token length: ' . strlen($whatsappConfig->access_token) . ' characters');

        // Pega campanhas ativas (status = 'playing')
        $campanhas = Campanha::where('status', 'playing')->get();

        if ($campanhas->isEmpty()) {
            echo 'Nenhuma campanha ativa encontrada';
            return;
        }

        $totalEnviados = 0;
        $totalErros = 0;

        // LOOP 1: Campanhas
        foreach ($campanhas as $campanha) {
            Log::info('=== Processando campanha: ' . $campanha->id . ' ===');

            // Verifica se a campanha tem template_name configurado
            if (!$campanha->template_name) {
                Log::error('Campanha ID ' . $campanha->id . ' sem template_name configurado');
                $totalErros++;
                continue;
            }

            // Pega os phone_number_ids da campanha (nova estrutura)
            $phoneNumberIds = $campanha->phoneNumbers();

            if ($phoneNumberIds->isEmpty()) {
                Log::warning('Campanha ID ' . $campanha->id . ' sem phone_number_ids configurados');
                continue;
            }

            // LOOP 2: Phone Numbers da campanha
            foreach ($phoneNumberIds as $phoneNumberId) {
                Log::info('--- Processando phone_number_id: ' . $phoneNumberId . ' ---');

                // Pega 10 contatos da campanha que ainda não foram enviados para este telefone
                $contatos = DB::table('contato_dados')
                    ->whereIn('contato_id', $campanha->contatos->pluck('id'))
                    ->where('send', 0)
                    ->limit(30)
                    ->get();

                if ($contatos->isEmpty()) {
                    Log::info('Nenhum contato para enviar neste phone_number_id');
                    continue;
                }

                Log::info('Total de contatos para enviar: ' . $contatos->count());

                // LOOP 3: Contatos para este phone_number_id
                foreach ($contatos as $contatoDado) {

                    try {
                        // Formata o número do contato
                        $numeroContato = preg_replace('/[^0-9]/', '', $contatoDado->telefone);

                        // Extrai primeiro nome do contato
                        $nomeCompleto = $contatoDado->nome ?? 'Cliente';
                        $primeiroNome = explode(' ', trim($nomeCompleto))[0];

                        Log::info('Enviando para contato: ' . $numeroContato . ' (' . $primeiroNome . ') url img: ' . $this->getImageUrl());

                        // Enviar template
                        $client = new Client();

                        // $data = [
                        //     'messaging_product' => 'whatsapp',
                        //     'to' => $numeroContato,
                        //     'type' => 'template',
                        //     'template' => [
                        //         'name' => $campanha->template_name, // Usar o nome do template da campanha
                        //         'language' => [
                        //             'code' => 'pt_BR',
                        //         ],
                        //         'components' => [
                        //              [
                        //                 'type' => 'header',
                        //                 'parameters' => [
                        //                     [
                        //                         'type' => 'image',
                        //                         'image' => [
                        //                             'link' => 'https://fluxo-neocob.betasolucao.com.br/storage/campaign-images/campanha.jpg'
                        //                         ]
                        //                     ]
                        //                 ]
                        //             ],
                        //             [
                        //                 'type' => 'body',
                        //                 'parameters' => [
                        //                     [
                        //                         'type' => 'text',
                        //                         'text' => $primeiroNome
                        //                     ]
                        //                 ]
                        //             ]
                        //         ]
                        //     ]
                        // ];

                        $data = [
                            'messaging_product' => 'whatsapp',
                            'to' => $numeroContato,
                            'type' => 'template',
                            'template' => [
                                'name' => $campanha->template_name, // Usar o nome do template da campanha
                                'language' => [
                                    'code' => 'pt_BR',
                                ],
                                'components' => [
                                    [
                                        'type' => 'body',
                                        'parameters' => [
                                            [
                                                'type' => 'text',
                                                'text' => $primeiroNome
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ];


                        // Headers para WhatsApp Business API
                        $headers = [
                            'Authorization' => 'Bearer ' . $whatsappConfig->access_token,
                            'Content-Type' => 'application/json',
                        ];

                        Log::info('URL: https://graph.facebook.com/v23.0/' . $phoneNumberId . '/messages');
                        Log::info('Payload: ' . json_encode($data));

                        $response = $client->post(
                            'https://graph.facebook.com/v23.0/' . $phoneNumberId . '/messages',
                            [
                                'json' => $data,
                                'headers' => $headers,
                            ]
                        );

                        $responseBody = $response->getBody()->getContents();
                        Log::info('Resposta da API: ' . $responseBody);

                        $responseData = json_decode($responseBody, true);

                        // Se envio bem-sucedido
                        if (isset($responseData['messages'][0]['id'])) {
                            Log::info('✓ Mensagem interativa enviada com sucesso! ID: ' . $responseData['messages'][0]['id']);

                            // Marca como enviado
                            DB::table('contato_dados')
                                ->where('id', $contatoDado->id)
                                ->update([
                                    'send' => 1,
                                    'telefone_id' => null,
                                    'updated_at' => now(),
                                ]);

                            $totalEnviados++;
                        } else {
                            Log::error('✗ Resposta sem mensagem ID: ' . json_encode($responseData));
                            $totalErros++;
                        }
                    } catch (\Exception $e) {
                        $totalErros++;
                        Log::error('✗ ERRO ao enviar mensagem interativa: ' . $e->getMessage());
                        Log::error('Stack: ' . $e->getTraceAsString());
                    }
                }
            }
        }

        return response()->json([
            'mensagem' => 'Envio em massa concluído',
            'total_enviados' => $totalEnviados,
            'total_erros' => $totalErros,
        ]);
    }

    private function getImageUrl()
    {
        // Se for localhost, usa URL de nuvem; caso contrário, usa URL local
        $isLocal = \Str::contains(config('app.url'), ['localhost', '127.0.0.1']);

        if ($isLocal) {
            // URL de nuvem (altere conforme sua URL de produção)
            return 'https://www.gstatic.com/webp/gallery/1.png';
        }

        // URL local em produção - caminho correto do storage público
        return asset('storage/campaign-images/campanha-2.jpg');
    }

    /**
     * Verifica inatividade nas sessões e envia alertas automáticos
     * - Alerta 1: Após 60 minutos (1 hora) de inatividade - qtde_alerta = 0 → 1
     * - Alerta 2: Após 120 minutos (2 horas) de inatividade - qtde_alerta = 1 → 2
     * - Alerta 3: Após 180 minutos (3 horas) de inatividade - qtde_alerta = 2 → 3 e encerra
     * 
     * Lógica: Busca 10 de cada categoria por vez, envia mensagem específica e atualiza
     */
    public function verificarInatividade()
    {
        try {
            $agora = now();
            $horaAtual = $agora->hour;

            // Verifica se está entre 8h da manhã e 8h da noite (20h)
            if ($horaAtual < 8 || $horaAtual >= 20) {
                Log::info("Verificação de inatividade bloqueada: Fora do horário de funcionamento (08:00 - 20:00). Hora atual: {$horaAtual}:00");
                return response()->json([
                    'mensagem' => 'Verificação de inatividade bloqueada',
                    'motivo' => 'Fora do horário de funcionamento (08:00 - 20:00)',
                    'hora_atual' => $horaAtual . ':00'
                ]);
            }

            $totalProcessadas = 0;
            $totalAlertas = 0;
            $erros = [];

            // ===== ALERTA 1: Busca 10 sessões com 60+ minutos inativo e qtde_alerta = 0 =====
            $todasSessoes1 = WhatsappSession::where('current_step', '!=', 'encerrada')
                ->where('qtde_alerta', 0)
                ->get();

            // Filtra em PHP as que têm 60+ minutos de inatividade
            $sessoes1 = $todasSessoes1->filter(function($sessao) {
                $minutosInativo = $sessao->updated_at->diffInMinutes(now());
                return $minutosInativo >= 60;
            })->values();

            $count1 = $sessoes1->count();
            Log::info("ALERTA 1: Encontradas {$count1} sessões");



            if ($count1 > 0) {
                Log::info("Processando {$count1} sessões para ALERTA 1");

                $ids1 = [];
                foreach ($sessoes1 as $sessao) {
                    try {
                        $contato = $sessao->contact;
                        if (!$contato)
                            continue;

                        $this->enviarMensagemAlerta(
                            $contato->wa_id,
                            $contato->name . ", percebemos que você está ocupado neste momento.\n\nVocê deseja continuar o seu atendimento?",
                            [
                                ['id' => 'btn_nao', 'title' => 'Não'],
                                ['id' => 'btn_sim', 'title' => 'Sim']
                            ]
                        );

                        $ids1[] = $sessao->id;
                        $totalAlertas++;
                        Log::info("✓ Alerta 1 enviado para sessão {$sessao->id}");

                    } catch (\Exception $e) {
                        $erros[] = "Erro ao enviar alerta 1 para sessão {$sessao->id}: " . $e->getMessage();
                        Log::error($erros[count($erros) - 1]);
                    }
                }

                // Atualiza todas de uma vez
                if (!empty($ids1)) {
                    DB::table('whatsapp_sessions')
                        ->whereIn('id', $ids1)
                        ->update([
                            'qtde_alerta' => 1,
                            'updated_at' => $agora
                        ]);
                    $totalProcessadas += count($ids1);
                }
            }

            if ($count1 > 10) {
                Log::warning("ALERTA 1 possui {$count1} sessões (> 10). Interrompendo execução dos alertas 2 e 3.");
                return response()->json([
                    'mensagem' => 'Verificação interrompida',
                    'motivo' => 'ALERTA 1 possui mais de 10 sessões',
                    'sessoes_alerta_1' => $count1,
                    'sessoes_processadas' => 0,
                    'alertas_enviados' => 0,
                    'erros' => $erros
                ]);
            }

            // ===== ALERTA 2: Busca 10 sessões com 120+ minutos inativo e qtde_alerta = 1 =====
            $todasSessoes2 = WhatsappSession::where('current_step', '!=', 'encerrada')
                ->where('qtde_alerta', 1)
                ->get();

            // Filtra em PHP as que têm 120+ minutos de inatividade
            $sessoes2 = $todasSessoes2->filter(function($sessao) {
                $minutosInativo = $sessao->updated_at->diffInMinutes(now());
                return $minutosInativo >= 120;
            })->values();

            $count2 = $sessoes2->count();
            Log::info("ALERTA 2: Encontradas {$count2} sessões");

            

            if ($count2 > 0) {
                Log::info("Processando {$count2} sessões para ALERTA 2");

                $ids2 = [];
                foreach ($sessoes2 as $sessao) {
                    try {
                        $contato = $sessao->contact;
                        if (!$contato)
                            continue;

                        $this->enviarMensagemAlerta(
                            $contato->wa_id,
                            "Olá novamente! Ainda está aí? 👋\n\nEntendo que você ainda pode estar ocupado.\n\nVocê deseja continuar o seu atendimento?",
                            [
                                ['id' => 'btn_nao', 'title' => 'Não'],
                                ['id' => 'btn_sim', 'title' => 'Sim']
                            ]
                        );

                        $ids2[] = $sessao->id;
                        $totalAlertas++;
                        Log::info("✓ Alerta 2 enviado para sessão {$sessao->id}");

                    } catch (\Exception $e) {
                        $erros[] = "Erro ao enviar alerta 2 para sessão {$sessao->id}: " . $e->getMessage();
                        Log::error($erros[count($erros) - 1]);
                    }
                }

                // Atualiza todas de uma vez
                if (!empty($ids2)) {
                    DB::table('whatsapp_sessions')
                        ->whereIn('id', $ids2)
                        ->update([
                            'qtde_alerta' => 2,
                            'updated_at' => $agora
                        ]);
                    $totalProcessadas += count($ids2);
                }
            }

            if ($count2 > 10) {
                Log::warning("ALERTA 2 possui {$count2} sessões (> 10). Interrompendo execução do alerta 3.");
                return response()->json([
                    'mensagem' => 'Verificação interrompida',
                    'motivo' => 'ALERTA 2 possui mais de 10 sessões',
                    'sessoes_alerta_1' => $count1,
                    'sessoes_alerta_2' => $count2,
                    'sessoes_processadas' => $totalProcessadas,
                    'alertas_enviados' => $totalAlertas,
                    'erros' => $erros
                ]);
            }

            // ===== ALERTA 3: Busca 10 sessões com 180+ minutos inativo e qtde_alerta = 2 =====
            $todasSessoes3 = WhatsappSession::where('current_step', '!=', 'encerrada')
                ->where('qtde_alerta', 2)
                ->get();

            // Filtra em PHP as que têm 180+ minutos de inatividade
            $sessoes3 = $todasSessoes3->filter(function($sessao) {
                $minutosInativo = $sessao->updated_at->diffInMinutes(now());
                return $minutosInativo >= 180;
            })->values();

            $count3 = $sessoes3->count();
            Log::info("ALERTA 3: Encontradas {$count3} sessões");

            

            if ($count3 > 0) {
                Log::info("Processando {$count3} sessões para ALERTA 3");

                $ids3 = [];
                foreach ($sessoes3 as $sessao) {
                    try {
                        $contato = $sessao->contact;
                        if (!$contato)
                            continue;

                        $nomeContato = $contato->name ?? 'Cliente';
                        $primeiroNome = explode(' ', trim($nomeContato))[0];

                        // Mensagem final de encerramento
                        $mensagemFinal = "Muito obrigado {$primeiroNome}!\n\n";
                        $mensagemFinal .= "Lojas Havan está sempre à disposição quando precisar.\n\n";
                        $mensagemFinal .= "Horário de atendimento:\n";
                        $mensagemFinal .= "Segunda a Sexta de 08:00 às 18:00";

                        $this->enviarMensagemTexto($contato->wa_id, $mensagemFinal);

                        $ids3[] = $sessao->id;
                        $totalAlertas++;
                        Log::info("✓ Alerta 3 e encerramento enviados para sessão {$sessao->id}");

                    } catch (\Exception $e) {
                        $erros[] = "Erro ao enviar alerta 3 para sessão {$sessao->id}: " . $e->getMessage();
                        Log::error($erros[count($erros) - 1]);
                    }
                }

                // Atualiza todas de uma vez
                if (!empty($ids3)) {
                    DB::table('whatsapp_sessions')
                        ->whereIn('id', $ids3)
                        ->update([
                            'qtde_alerta' => 3,
                            'current_step' => 'encerrada',
                            'updated_at' => $agora
                        ]);
                    $totalProcessadas += count($ids3);
                }
            }

            Log::info("Verificação de inatividade concluída: {$totalProcessadas} sessões processadas, {$totalAlertas} alertas enviados");

            return response()->json([
                'mensagem' => 'Verificação de inatividade concluída',
                'sessoes_alerta_1' => $count1,
                'sessoes_alerta_2' => $count2,
                'sessoes_alerta_3' => $count3,
                'sessoes_processadas' => $totalProcessadas,
                'alertas_enviados' => $totalAlertas,
                'erros' => $erros
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao verificar inatividade: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erro ao verificar inatividade',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envia mensagem com botões de alerta
     */
    private function enviarMensagemAlerta($wa_id, $texto, $opcoes)
    {
        try {
            // Busca o contato e sua sessão para pegar phone_number_id
            $contato = WhatsappContact::where('wa_id', $wa_id)->first();
            if (!$contato) {
                Log::error('Contato não encontrado: ' . $wa_id);
                return false;
            }

            $sessao = WhatsappSession::where('contact_id', $contato->id)->where('current_step', '!=', 'encerrada')->first();
            if (!$sessao || !$sessao->phone_number_id) {
                Log::error('Sessão ou phone_number_id não encontrado para contato: ' . $wa_id);
                return false;
            }

            $config = DB::table('whatsapp')->first();
            $token = $config->access_token ?? env('WHATSAPP_TOKEN');
            $phoneNumberId = $sessao->phone_number_id; // ✅ Usar phone_number_id da sessão

            if (empty($token) || empty($phoneNumberId)) {
                Log::error('Token WhatsApp ou phone_number_id não encontrados');
                return false;
            }

            // Normaliza options
            $normalized = array_map(function ($o) {
                if (is_array($o))
                    return $o;
                return ['id' => (string) $o, 'title' => (string) $o];
            }, $opcoes);

            $rows = [];
            foreach ($normalized as $opt) {
                $rows[] = [
                    'id' => $opt['id'],
                    'title' => $opt['title'],
                    'description' => ''
                ];
            }

            $section = [
                'title' => 'Escolha uma opção',
                'rows' => $rows
            ];

            $body = [
                'messaging_product' => 'whatsapp',
                'to' => $wa_id,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'list',
                    'header' => [
                        'type' => 'text',
                        'text' => ''
                    ],
                    'body' => [
                        'text' => $texto
                    ],
                    'action' => [
                        'button' => 'Responder',
                        'sections' => [$section]
                    ]
                ]
            ];

            $url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";
            $res = Http::withToken(token: $token)->post($url, $body);

            if ($res->successful()) {
                Log::info('✓ Mensagem de alerta enviada para ' . $wa_id);
                return true;
            } else {
                Log::error('Erro ao enviar mensagem de alerta: ' . $res->body());
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Exceção ao enviar mensagem de alerta: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envia mensagem de texto simples
     */
    private function enviarMensagemTexto($wa_id, $texto)
    {
        try {
            // Busca o contato e sua sessão para pegar phone_number_id
            $contato = WhatsappContact::where('wa_id', $wa_id)->first();
            if (!$contato) {
                Log::error('Contato não encontrado: ' . $wa_id);
                return false;
            }

            $sessao = WhatsappSession::where('contact_id', $contato->id)->where('current_step', '!=', 'encerrada')->first();
            if (!$sessao || !$sessao->phone_number_id) {
                Log::error('Sessão ou phone_number_id não encontrado para contato: ' . $wa_id);
                return false;
            }

            $config = DB::table('whatsapp')->first();
            $token = $config->access_token ?? env('WHATSAPP_TOKEN');
            $phoneNumberId = $sessao->phone_number_id; // ✅ Usar phone_number_id da sessão

            if (empty($token) || empty($phoneNumberId)) {
                Log::error('Token WhatsApp ou phone_number_id não encontrados');
                return false;
            }

            $data = [
                "messaging_product" => "whatsapp",
                "to" => $wa_id,
                "type" => "text",
                "text" => ["body" => $texto]
            ];

            $res = Http::withToken(token: $token)
                ->post("https://graph.facebook.com/v18.0/{$phoneNumberId}/messages", $data);

            if ($res->successful()) {
                Log::info('✓ Mensagem de texto enviada para ' . $wa_id);
                return true;
            } else {
                Log::error('Erro ao enviar mensagem de texto: ' . $res->body());
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Exceção ao enviar mensagem de texto: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envia acordos pendentes para Datacob API (direto, sem fila)
     * GET /api/enviar-acordos-datacob
     */
    public function enviarAcordosDatacob()
    {
        try {
            $acordos = Acordo::where('id', '209')
                ->whereHas('contatoDado', function ($query) {
                    $query->whereNotNull('id_contrato');
                })
                ->get();

            if ($acordos->isEmpty()) {
                return response()->json([
                    'sucesso' => true,
                    'mensagem' => 'Nenhum acordo pendente para enviar',
                    'total_enviados' => 0
                ]);
            }

            $totalEnviados = 0;
            $totalErros = 0;
            $erros = [];

            // Token da API Datacob
            $token = $this->geraTokenDATACOB();
            if (!$token) {
                return response()->json([
                    'sucesso' => false,
                    'erro' => 'Não foi possível obter token Datacob',
                    'total_enviados' => 0
                ], 500);
            }

            $client = new Client();

            // Envia cada acordo
            foreach ($acordos as $acordo) {
                try {
                    $contatoDado = $acordo->contatoDado;
                    if (!$contatoDado)
                        continue;

                    $idContrato = (int) $contatoDado->cod_cliente;
                    $qtdeParcelas = 1;
                    $valorParcela = 0.00;
                    // Calcula data de pagamento com 5 dias úteis e converte para Y-m-d
                    $dataPagtoEntradaFormatada = $this->calcularDataVencimentoComDiasUteis();
                    $dataPagtoEntrada = \DateTime::createFromFormat('d/m/Y', $dataPagtoEntradaFormatada)->format('Y-m-d');
                    $dataCriacao = $acordo->created_at->format('Y-m-d');

                    // Extrai qtde e valor de parcela
                    // Trata formato: "6x de R$ 1.095,95" ou "acordo a vista: R$ 1.095,95"
                    if (preg_match('/(\d+)x\s+de\s+R\$\s+([\d.]+,\d{2})/', $acordo->texto, $matches)) {
                        $qtdeParcelas = (int) $matches[1];
                        // Converte "1.095,95" para 1095.95
                        $valorParcela = (float) str_replace(['.', ','], ['', '.'], $matches[2]);
                    } elseif (preg_match('/acordo\s+a\s+vista:\s+R\$\s+([\d.]+,\d{2})/', $acordo->texto, $matches)) {
                        $qtdeParcelas = 1;
                        // Converte "1.095,95" para 1095.95
                        $valorParcela = (float) str_replace(['.', ','], ['', '.'], $matches[1]);
                    }

                    // Sanitiza valores para garantir UTF-8 válido
                    // Calcula data vencimento próxima parcela (5 dias úteis após pagamento entrada)
                    $dataProximaParcela = \DateTime::createFromFormat('Y-m-d', $dataPagtoEntrada);
                    $dataProximaParcela->add(new \DateInterval('P5D'));
                    $dataVencimentoProximaParcela = $dataProximaParcela->format('Y-m-d');

                    $payload = [
                        'IdContrato' => (int) $idContrato,
                        'ValorAcordo' => round((float) $valorParcela, 2), // Valor total do acordo
                        'QtdeParcelas' => (int) $qtdeParcelas,
                        'ValorEntrada' => round((float) $valorParcela, 2),
                        'DataPagtoEntrada' => (string) $dataPagtoEntrada,
                        'ValorParcela' => round((float) $valorParcela, 2), // Valor de cada parcela
                        'DataVencimentoProximaParcela' => (string) $dataVencimentoProximaParcela,
                    ];
                    dd(json_encode($payload));



                    // Envia para API (sem Content-Type, como Node)
                    $response = $client->post(
                        'http://datacob.thiagofarias.adv.br/api/negociacao/v1/confirmar-acordo',
                        [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $token,
                                'apiKey' => 'PYBW+7AndDA=',
                            ],
                            'json' => $payload,
                            'verify' => false,
                        ]
                    );

                    $statusCode = $response->getStatusCode();
                    $responseBody = $response->getBody()->getContents();

                    \Log::info('Resposta Datacob:', [
                        'acordo_id' => $acordo->id,
                        'status_code' => $statusCode,
                        'response_body' => $responseBody,
                    ]);

                    if ($statusCode === 200 || $statusCode === 201) {
                        $acordo->update(['status' => 'enviado']);
                        $totalEnviados++;
                        \Log::info('✓ Acordo enviado com sucesso: ' . $acordo->id);
                    } else {
                        $totalErros++;
                        $erros[] = "Acordo {$acordo->id}: Status {$statusCode}";

                    }

                } catch (\Exception $e) {
                    $totalErros++;
                    $erros[] = "Acordo {$acordo->id}: " . $e->getMessage();
                    \Log::error('✗ Exceção ao enviar acordo ' . $acordo->id, [
                        'message' => $e->getMessage(),
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }

            return response()->json([
                'sucesso' => true,
                'mensagem' => 'Envio de acordos concluído',
                'total_enviados' => $totalEnviados,
                'total_erros' => $totalErros,
                'erros' => $erros
            ]);

        } catch (\Exception $e) {
            $mensagemErro = $e->getMessage();
            // Remove caracteres UTF-8 inválidos se existirem
            if (!mb_check_encoding($mensagemErro, 'UTF-8')) {
                $mensagemErro = mb_convert_encoding($mensagemErro, 'UTF-8', 'ISO-8859-1');
            }
            \Log::error('Erro ao enviar acordos Datacob: ' . $mensagemErro);
            return response()->json([
                'sucesso' => false,
                'erro' => $mensagemErro,
                'total_enviados' => 0
            ], 500);
        }
    }
    private function calcularDataVencimentoComDiasUteis()
    {
        // Data fixa para hoje: 28/11/2025
        // return '28/11/2025';

        // Código original comentado para recuperar depois:

        $data = now();
        $diasAdicionados = 0;
        $diasUteis = 0;

        // Adiciona dias até completar 5 dias úteis
        while ($diasUteis < 5) {
            $data = $data->addDay();
            $diasAdicionados++;

            // Verifica se é dia útil (segunda a sexta = 1 a 5)
            if ($data->dayOfWeek >= 1 && $data->dayOfWeek <= 5) {
                $diasUteis++;
            }
        }

        // Verifica se caiu em sábado (6) ou domingo (0)
        if ($data->dayOfWeek == 6) { // Sábado
            $data = $data->addDays(2); // Pula para segunda
        } elseif ($data->dayOfWeek == 0) { // Domingo
            $data = $data->addDay(); // Pula para segunda
        }

        return $data->format('d/m/Y');

    }

    /**
     * Deleta acordos pendentes a partir do ID 211 em diante
     * DELETE /api/deletar-acordos-apos-211
     */
    public function deletarAcordosFechadosApos211()
    {
        try {
            $acordos = Acordo::where('id', '>', 211)
                ->whereIn('status', ['pendente'])
                ->whereNotIn('id', ['313'])
                ->get();

            if ($acordos->isEmpty()) {
                return response()->json([
                    'sucesso' => true,
                    'mensagem' => 'Nenhum acordo pendente encontrado após ID 211',
                    'total_deletados' => 0
                ]);
            }

            $totalDeletados = $acordos->count();
            $ids = $acordos->pluck('id')->toArray();

            // Deleta apenas os acordos (não deleta contatoDado)
            Acordo::whereIn('id', $ids)->delete();

            \Log::info('Acordos deletados com sucesso', [
                'total' => $totalDeletados,
                'ids' => $ids
            ]);

            return response()->json([
                'sucesso' => true,
                'mensagem' => 'Acordos deletados com sucesso',
                'total_deletados' => $totalDeletados,
                'ids_deletados' => $ids
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao deletar acordos: ' . $e->getMessage());
            return response()->json([
                'sucesso' => false,
                'erro' => $e->getMessage(),
                'total_deletados' => 0
            ], 500);
        }
    }

    /**
     * Envia mensagens WhatsApp de texto para array de telefones (10 por array)
     * Usa arrays manuais passados diretamente no código
     * GET /api/enviar-whatsapp-batch
     */
    public function enviarWhatsappBatch()
    {
        try {
            // Array de telefones - cole aqui diretamente
            $telefones = [
                "5519995010740",
                "5515991176556",
                "5513991179806",
                "5519994984659",
                "5515981843766",
                "5514991542448",
                "5516999628880",
                "5519993478935",
                "5512988052050",
                "5514996675826",
                "5511985519035",
                "5513996773815",
                "5517991420087",
                "5518935009491",
                "5519982810541",
                "5514997090968",
            ];

            if (empty($telefones)) {
                return response()->json([
                    'sucesso' => false,
                    'mensagem' => 'Nenhum telefone fornecido',
                    'total_enviados' => 0
                ], 400);
            }

            // Obtém configuração do WhatsApp
            $config = DB::table('whatsapp')->first();
            if (!$config || !$config->access_token) {
                return response()->json([
                    'sucesso' => false,
                    'erro' => 'Token WhatsApp não configurado',
                    'total_enviados' => 0
                ], 500);
            }

            $token = trim($config->access_token);

            $client = new Client();
            $totalEnviados = 0;
            $totalErros = 0;
            $erros = [];

            // Mensagem padrão - Mensagem simpática sobre instabilidade do sistema
            $mensagemPadrao = "Olá! 👋\n\n" .
                "Mais cedo enfrentamos uma instabilidade no nosso sistema e infelizmente seu acordo não foi registrado.\n\n" .
                "Sem problemas! Para continuar, basta você digitar *Olá* que retornaremos com a proposta de parcelamento.\n\n" .
                "Desculpe pelo inconveniente! 😊";

            // Envia para cada telefone
            foreach ($telefones as $telefone) {
                try {
                    // Sanitiza o telefone
                    $telefoneFormatado = preg_replace('/[^0-9]/', '', $telefone);

                    if (strlen($telefoneFormatado) < 10) {
                        $erros[] = "Telefone inválido: {$telefone}";
                        $totalErros++;
                        continue;
                    }

                    // Monta o payload de mensagem de texto
                    $body = json_encode([
                        'messaging_product' => 'whatsapp',
                        'recipient_type' => 'individual',
                        'to' => $telefoneFormatado,
                        'type' => 'text',
                        'text' => [
                            'preview_url' => false,
                            'body' => $mensagemPadrao
                        ]
                    ]);

                    // Headers
                    $headers = [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $token
                    ];

                    // Cria e envia a requisição com sendAsync
                    $guzzleRequest = new Request(
                        'POST',
                        'https://graph.facebook.com/v22.0/895058090348328/messages',
                        $headers,
                        $body
                    );

                    $response = $client->sendAsync($guzzleRequest)->wait();
                    $statusCode = $response->getStatusCode();
                    $responseBody = $response->getBody()->getContents();

                    \Log::info('Resposta WhatsApp:', [
                        'telefone' => $telefoneFormatado,
                        'status_code' => $statusCode,
                        'response' => $responseBody,
                    ]);

                    if ($statusCode >= 200 && $statusCode < 300) {
                        $totalEnviados++;
                        \Log::info('✓ Mensagem enviada para: ' . $telefoneFormatado);
                    } else {
                        $totalErros++;
                        $erros[] = "Telefone {$telefoneFormatado}: Status {$statusCode}";
                    }

                } catch (\Exception $e) {
                    $totalErros++;
                    $erros[] = "Telefone {$telefone}: " . $e->getMessage();
                    \Log::error('Erro ao enviar para ' . $telefone . ': ' . $e->getMessage());
                }
            }

            return response()->json([
                'sucesso' => true,
                'mensagem' => 'Envio em lote concluído',
                'total_enviados' => $totalEnviados,
                'total_erros' => $totalErros,
                'erros' => $erros
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao enviar WhatsApp em lote: ' . $e->getMessage());
            return response()->json([
                'sucesso' => false,
                'erro' => $e->getMessage(),
                'total_enviados' => 0
            ], 500);
        }
    }
}
