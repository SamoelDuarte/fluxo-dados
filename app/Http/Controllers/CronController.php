<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Carbon\Carbon;
use App\Models\Contrato;
use App\Models\Planilha;
use GuzzleHttp\Psr7\Request;

class CronController extends Controller
{

    public function obterOpcoesParcelamento()
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
}
