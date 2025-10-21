<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->text('prompt'); // mensagem a ser enviada
            $table->string('expected_input')->nullable(); // tipo de resposta esperada (ex: cpf, sim/nao)
            $table->string('next_step_condition')->nullable(); // lógica para próximo passo
            $table->timestamps();
        });

        // === PASSOS (STEPS) ===
        $steps = [
            // === FLUXO INICIAL ===
            [
                'flow_id' => 1,
                'step_number' => 1,
                'prompt' => 'Seja bem-vindo(a) ao nosso canal digital! Sou a assistente da Neocob em nome da {{NomeBanco}}.',
                'expected_input' => null,
                'next_step_condition' => 'null',
            ],
            // Pergunta de horário (Sim / Não)
            [
                'flow_id' => 1,
                'step_number' => 2,
                'prompt' => 'Hoje é dia útil ? (07:00 às 22:00; Sábados: 07:00 às 14:00)\n\nSelecione: Sim ou Não',
                'expected_input' => 'botao',
                'next_step_condition' => 'verifica_horario',
            ],
            // Solicitação de CPF com instrução de exemplo
            [
                'flow_id' => 1,
                'step_number' => 3,
                'prompt' => 'Para localizar suas informações, por favor informe seu *CPF/CNPJ* (apenas números).\nDigite apenas os números conforme o exemplo abaixo:\n01010109120',
                'expected_input' => 'cpf',
                'next_step_condition' => 'api_valida_cpf',
            ],
            // Mensagem de busca (buscando informações)
            [
                'flow_id' => 1,
                'step_number' => 4,
                'prompt' => 'Estou buscando as informações necessárias para seguirmos por aqui, só um instante.',
                'expected_input' => null,
                'next_step_condition' => 'null',
            ],


            // === FLUXO Fluxo Identificador de Divida ===

            [
                'flow_id' => 2,
                'step_number' => 1,
                'prompt' => 'Encontrei Você!',
                'expected_input' => null,
                'next_step_condition' => 'null',
            ],
            [
                'flow_id' => 2,
                'step_number' => 2,
                'prompt' => '@primeironome, confira como podemos te ajudar por este canal. 🙂',
                'expected_input' => null,
                'next_step_condition' => 'aguarda_resposta_acordo_aberto_true',
            ],
            [
                'flow_id' => 2,
                'step_number' => 3,
                'prompt' => '@primeironome, confira como podemos te ajudar por este canal. 🙂',
                'expected_input' => null,
                'next_step_condition' => 'aguarda_resposta_acordo_aberto_false',
            ],
            [
                'flow_id' => 2,
                'step_number' => 4,
                'prompt' => '{{Nome}}, você não possui contrato(s) ativo(s) em nossa assessoria.\n\n\nPodemos ajudar em algo mais?\nSelecione uma opção abaixo:',
                'expected_input' => 'botao',
                'next_step_condition' => 'aguarda_resposta_acordo_vigente_false',
            ],
            [
                'flow_id' => 2,
                'step_number' => 5,
                'prompt' => 'Não localizei cadastro com esse documento. Por favor digite novamente o CPF/CNPJ (apenas números).',
                'expected_input' => 'cpf',
                'next_step_condition' => 'pergunta_cliente_sem_contratos_e_acordo',
            ],

            // === FLUXO NEGOCIAR ===

            [
                'flow_id' => 3,
                'step_number' => 1,
                'prompt' => 'Aguarde mais um momento por gentileza, estou verificando seus dados...',
                'expected_input' => null,
                'next_step_condition' => 'null',
            ],
            [
                'flow_id' => 3,
                'step_number' => 2,
                'prompt' => 'Identifiquei 1 débito em atraso a *5* dias. Aguarde enquanto localizo a melhor proposta para você... 🔎',
                'expected_input' => null,
                'next_step_condition' => 'null',
            ],
            [
                'flow_id' => 3,
                'step_number' => 3,
                'prompt' => '@primeironome, localizei *X* contratos em aberto.',
                'expected_input' => 'botao',
                'next_step_condition' => 'aguarda_resposta_lista_de_contratos',
            ],

            // === FLUXO ACORDOS ===

            [
                'flow_id' => 4,
                'step_number' => 1,
                'prompt' => 'Obrigada pela confirmação! Identifiquei que você ja possui uma negociação na data *{{acordoSelecionado@dataAcordo}}* com o valor de *{{acordoSelecionado@valorTotal}}*. Aguarde, vou te enviar o código de barras referente ao seu acordo...',
                'expected_input' => 'botao',
                'next_step_condition' => 'null',
            ],
            [
                'flow_id' => 4,
                'step_number' => 2,
                'prompt' => 'Localizei *{{qtdAcordos}}* acordo(s) formalizado(s).\nClique no botão abaixo para conferir:',
                'expected_input' => 'botao',
                'next_step_condition' => 'aguarda_resposta_seleciona_acordo',
            ],

            // === FLUXO Comprovantes ===

            [
                'flow_id' => 5,
                'step_number' => 1,
                'prompt' => 'Por favor, Envie seu comprovante de Pagamento',
                'expected_input' => 'botao',
                'next_step_condition' => 'aguarda_resposta_img_comprovante',
            ],
            [
                'flow_id' => 5,
                'step_number' => 2,
                'prompt' => 'Comprovante Recebido com sucesso! Aguarde enquanto verificamos a disponibilidade dos nossos especialistas.',
                'expected_input' => null,
                'next_step_condition' => 'null',
            ],

            // === FLUXO proposta ===

            [
                'flow_id' => 6,
                'step_number' => 1,
                'prompt' => 'A melhor oferta para pagamento é de R$ *{{valorTotal}}* com vencimento em *{{data}}*.\n\n*Podemos enviar o boleto?*\nSelecione uma opção abaixo:',
                'expected_input' => 'botao',
                'next_step_condition' => 'aguardar_resposta_proposta',
            ],
            // Step 2: botões específicos da proposta (Gerar Acordo / Mais Opções / Ver outro contrato)
            [
                'flow_id' => 6,
                'step_number' => 2,
                'prompt' => 'Aqui estão outras opções, selecione o botão abaixo para conferir:',
                'expected_input' => 'botao',
                'next_step_condition' => 'aguardar_resposta_mais_opcoes_proposta',
            ],

            // === FLUXO parcelamento ===

            [
                'flow_id' => 7,
                'step_number' => 1,
                'prompt' => 'Confira as opções de parcelamento com vencimento da primeira parcela para *{{dataVencimento}}.*',
                'expected_input' => 'botao',
                'next_step_condition' => 'aguardar_resposta_opcoes_parcelamento',
            ],
            [
                'flow_id' => 7,
                'step_number' => 2,
                'prompt' => 'Não conseguimos consultar as opções de parcelamento no momento. Por favor, tente novamente mais tarde.',
                'expected_input' => 'botao',
                'next_step_condition' => 'null',
            ],

            // === FLUXO vencimento ===

            [
                'flow_id' => 8,
                'step_number' => 1,
                'prompt' => 'Verifique as opções datas de vencimento encontradas para seu débito:',
                'expected_input' => 'botao',
                'next_step_condition' => 'aguardar_resposta_opcoes_vencimento',
            ],

            // === Fluxo Confirma Acordo ===

            [
                'flow_id' => 9,
                'step_number' => 1,
                'prompt' => '@primeironome, aqui está o resumo da proposta: - *Valor acordo*: R$ 500,00 - *Data de Vencimento*: {{dataVencimento} - *Modo de pagamento*: Parcelado em 2x de R$ 250,00 
                *Podemos formalizar o acordo?*
                Selecione uma opção abaixo:',
                'expected_input' => 'botao',
                'next_step_condition' => 'aguardar_resposta_confirma_acordo',
            ],

            // === Fluxo Envio Codigo de Barras ===

            [
                'flow_id' => 10,
                'step_number' => 1,
                'prompt' => 'Acordo gerado com sucesso!{{textAcordoFormalizado}}',
                'expected_input' => 'botao',
                'next_step_condition' => 'null',
            ],

            [
                'flow_id' => 10,
                'step_number' => 2,
                'prompt' => 'Estou te enviando o código de barras para pagamento. 👇🏼',
                'expected_input' => 'botao',
                'next_step_condition' => 'null',
            ],
            [
                'flow_id' => 10,
                'step_number' => 3,
                'prompt' => 'Lembrando que é muito importante você realizar o pagamento para garantir esse valor.',
                'expected_input' => 'botao',
                'next_step_condition' => 'null',
            ],

            //adiciona modulos a parti daqui 

            // === Módulos de Erro (flow_id = 11) - conforme design anexado ===
            [
                'flow_id' => 11,
                'step_number' => 100,
                'prompt' => 'No momento não consigo assistir vídeos. Por favor, responda a pergunta a seguir.',
                'expected_input' => 'texto',
                'next_step_condition' => 'repetir_pergunta',
            ],
            [
                'flow_id' => 11,
                'step_number' => 101,
                'prompt' => 'No momento não consigo visualizar este conteúdo. Por favor, responda a pergunta a seguir.',
                'expected_input' => 'texto',
                'next_step_condition' => 'repetir_pergunta',
            ],
            [
                'flow_id' => 11,
                'step_number' => 102,
                'prompt' => 'Este documento não é válido. Por favor digite apenas o CPF/CNPJ (apenas números).',
                'expected_input' => 'cpf',
                'next_step_condition' => 'repetir_pergunta',
            ],
            [
                'flow_id' => 11,
                'step_number' => 103,
                'prompt' => 'No momento não consigo ouvir áudios. Por favor, responda a pergunta a seguir.',
                'expected_input' => 'texto',
                'next_step_condition' => 'repetir_pergunta',
            ],
            [
                'flow_id' => 11,
                'step_number' => 104,
                'prompt' => 'Este comprovante não pôde ser processado. Por favor envie em PDF ou imagem legível.',
                'expected_input' => 'botao',
                'next_step_condition' => 'repetir_pergunta',
            ],
            [
                'flow_id' => 11,
                'step_number' => 105,
                'prompt' => 'Tivemos um problema de mensageria. Por favor tente novamente.',
                'expected_input' => null,
                'next_step_condition' => 'repetir_pergunta',
            ],
            [
                'flow_id' => 11,
                'step_number' => 106,
                'prompt' => 'Não conseguimos receber este anexo. Tente outro formato ou envie novamente.',
                'expected_input' => null,
                'next_step_condition' => 'repetir_pergunta',
            ],
            [
                'flow_id' => 11,
                'step_number' => 107,
                'prompt' => 'Este não é um número válido. Por favor informe apenas números.',
                'expected_input' => 'numero',
                'next_step_condition' => 'repetir_pergunta',
            ],
            [
                'flow_id' => 11,
                'step_number' => 108,
                'prompt' => 'Por favor selecione uma opção usando os botões apresentados.',
                'expected_input' => 'botao',
                'next_step_condition' => 'repetir_pergunta',
            ],
            [
                'flow_id' => 11,
                'step_number' => 109,
                'prompt' => 'Resposta inválida. Por favor responda conforme solicitado para que possamos prosseguir.',
                'expected_input' => null,
                'next_step_condition' => 'repetir_pergunta',
            ],
            // Após 3 erros seguidos, mensagem persistente
            [
                'flow_id' => 11,
                'step_number' => 110,
                'prompt' => 'Desculpe, não estou conseguindo te entender. Um atendente irá receber seu caso e entrará em contato em breve.',
                'expected_input' => null,
                'next_step_condition' => 'null',
            ],
             [
                'flow_id' => 11,
                'step_number' => 111,
                'prompt' => 'No momento não consigo ler documentos.Por favor, *responda a pergunta a seguir:*',
                'expected_input' => null,
                'next_step_condition' => 'null',
            ],
             [
                'flow_id' => 11,
                'step_number' => 111,
                'prompt' => 'posso ajudar em algo mais?',
                'expected_input' => null,
                'next_step_condition' => 'aguardar_resposta_posso_ajudar',
            ],

        ];

        foreach ($steps as $step) {
            DB::table('whatsapp_flow_steps')->insert([
                'prompt' => $step['prompt'],
                'expected_input' => $step['expected_input'],
                'next_step_condition' => $step['next_step_condition'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_steps');
    }
};
