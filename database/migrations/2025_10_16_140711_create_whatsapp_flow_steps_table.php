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
            $table->foreignId('flow_id')->constrained('whatsapp_flows')->cascadeOnDelete();
            $table->integer('step_number'); // ordem do passo
            // use text to allow long prompts and emojis
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
                'next_step_condition' => 'verifica_horario',
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
                'next_step_condition' => 'fluxo_negociar',
            ],
            // Mensagem quando não encontra cadastro (repetir CPF)
            [
                'flow_id' => 1,
                'step_number' => 5,
                'prompt' => 'Não localizei cadastro com esse documento. Por favor digite novamente o CPF/CNPJ (apenas números).',
                'expected_input' => 'cpf',
                'next_step_condition' => 'api_valida_cpf',
            ],
            [
                'flow_id' => 1,
                'step_number' => 6,
                'prompt' => 'Encontrei Você!',
                'expected_input' => null,
                'next_step_condition' => 'fluxo_negociar',
            ],

            // === FLUXO NEGOCIAR ===
            // Step 1: mensagem inicial informando débitos
            [
                'flow_id' => 2,
                'step_number' => 1,
                'prompt' => '@primeironome! Identifiquei *{{qtdContratos}}* débito(s) em atraso.\n\nAguarde enquanto localizo a melhor proposta para você...',
                'expected_input' => null,
                'next_step_condition' => 'repetir_pergunta',
            ],
            // Step 2: pergunta quando cliente não possui contratos/ acordos
            [
                'flow_id' => 2,
                'step_number' => 2,
                'prompt' => '{{Nome}}, você não possui contrato(s) ativo(s) em nossa assessoria.\n\n\nPodemos ajudar em algo mais?\nSelecione uma opção abaixo:',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],
            // Step 3: lista de contratos
            [
                'flow_id' => 2,
                'step_number' => 3,
                'prompt' => '@primeironome, localizei *{{qtdContratos}}* contratos em aberto.\n\nSelecione o botão abaixo para conferir:',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],
            // Step 4: pergunta se possui acordo vigente para o contrato selecionado
            [
                'flow_id' => 2,
                'step_number' => 4,
                'prompt' => '@primeironome, este contrato possui acordo vigente? Por favor selecione: Sim ou Não',
                'expected_input' => 'botao',
                'next_step_condition' => 'verifica_acordo',
            ],
            // Step 5: caso exista(s) acordo(s) vigentes - pergunta para visualizar
            [
                'flow_id' => 2,
                'step_number' => 5,
                'prompt' => '@primeironome, localizei *{{qtdAcordos}}* acordo(s) vigente(s). Deseja visualizar?',
                'expected_input' => 'botao',
                'next_step_condition' => 'fluxo_acordos',
            ],
            // Step 6: opções para o contrato selecionado (negociar / 2ª via / enviar comprovante / atendimento / encerrar)
            [
                'flow_id' => 2,
                'step_number' => 6,
                'prompt' => 'Para este contrato, selecione uma opção abaixo:',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],
            // Step 7: solicita envio de comprovante (imagem/arquivo)
            [
                'flow_id' => 2,
                'step_number' => 7,
                'prompt' => 'Por favor, envie seu comprovante de pagamento.\n\n📸 Tire uma foto legível ou envie um arquivo contendo o comprovante.',
                'expected_input' => 'media',
                'next_step_condition' => 'recebeu_comprovante',
            ],
            // Step 8: confirmação de recebimento do comprovante
            [
                'flow_id' => 2,
                'step_number' => 8,
                'prompt' => 'Comprovante recebido!\n\n_Aguarde, estamos verificando a disponibilidade dos nossos especialistas._',
                'expected_input' => null,
                'next_step_condition' => 'fluxo_algo_mais',
            ],
            // Step 8: confirmação de recebimento do comprovante
            [
                'flow_id' => 2,
                'step_number' => 9,
                'prompt' => '@primeironome, confira como podemos te ajudar por este canal. 🙂',
                'expected_input' => null,
                'next_step_condition' => 'processar_opcao_negociar',
            ],
            [
                'flow_id' => 2,
                'step_number' => 10,
                'prompt' => '@primeironome, confira como podemos te ajudar por este canal. 🙂',
                'expected_input' => null,
                'next_step_condition' => 'processar_opcao_negociar',
            ],

            // === FLUXO PROPOSTA ===
            [
                'flow_id' => 3,
                'step_number' => 1,
                'prompt' => 'A melhor oferta para pagamento é de R$ *{{valorTotal}}* com vencimento em *{{data}}*.\n\n*Podemos enviar o boleto?*\nSelecione uma opção abaixo:',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],
            // Step 2: botões específicos da proposta (Gerar Acordo / Mais Opções / Ver outro contrato)
            [
                'flow_id' => 3,
                'step_number' => 2,
                'prompt' => 'Opções:\n- Gerar Acordo\n- Mais Opções\n- Ver outro Contrato',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],
            // Step 3: lista de opções adicionais (ex: Alterar Vencimento, Parcelar Pagamento...)
            [
                'flow_id' => 3,
                'step_number' => 3,
                'prompt' => 'Opções adicionais:\n- Alterar Vencimento\n- Parcelar Pagamento\n- Ver outro contrato\n- Falar com Especialista\n- Encerrar Atendimento',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],
            // Step 4: opções de parcelamento (retornadas pela API)
            [
                'flow_id' => 3,
                'step_number' => 4,
                'prompt' => '
                ,,,,ções de parcelamento com vencimento da primeira parcela para *{{dataVencimento}}*:\n\n1) {{opcoesPagamento[0].valorParcela}} - {{opcoesPagamento[0].descricao}}\n2) {{opcoesPagamento[1].valorParcela}} - {{opcoesPagamento[1].descricao}}\n\nSelecione a opção desejada:',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],
            // Step 5: mensagem quando API não retornar opções
            [
                'flow_id' => 3,
                'step_number' => 5,
                'prompt' => 'Não conseguimos consultar as opções de parcelamento no momento. Por favor, tente novamente mais tarde.',
                'expected_input' => null,
                'next_step_condition' => 'repetir_pergunta',
            ],
            // Step 6: confirmação da data escolhida pelo usuário
            [
                'flow_id' => 3,
                'step_number' => 6,
                'prompt' => 'Você escolheu a data *{{DataEscolhida}}* para pagamento.\n\nDeseja confirmar essa opção?',
                'expected_input' => 'botao',
                'next_step_condition' => 'fluxo_confirma_acordo',
            ],

            // === FLUXO ACORDOS ===
            // Step 1: caso exista exatamente 1 acordo - confirma e envia código
            [
                'flow_id' => 4,
                'step_number' => 1,
                'prompt' => 'Obrigado pela confirmação! Identifiquei que você possui uma negociação na data *{{dataAcordo}}* no valor de *{{valorTotal}}*.\nAguarde, vou te enviar o código de barras referente ao seu acordo.',
                'expected_input' => null,
                'next_step_condition' => 'fluxo_envia_codigo_barras',
            ],
            // Step 2: lista de acordos quando houver mais de um
            [
                'flow_id' => 4,
                'step_number' => 2,
                'prompt' => 'Localizei *{{qtdAcordos}}* acordo(s) formalizado(s).\nClique no botão abaixo para conferir:',
                'expected_input' => 'botao',
                'next_step_condition' => 'seleciona_acordo',
            ],
            // Step 3: ações para o acordo selecionado (enviar código, ver detalhes, voltar)
            [
                'flow_id' => 4,
                'step_number' => 3,
                'prompt' => 'Selecione uma opção para o acordo selecionado:\n- Enviar código de barras\n- Ver detalhes do acordo\n- Voltar',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],
            // Step 4: mensagem quando não existem acordos
            [
                'flow_id' => 4,
                'step_number' => 4,
                'prompt' => '{{Nome}}, você não possui acordo(s) ativo(s) em nossa assessoria.\n\nPodemos ajudar em algo mais?\nSelecione uma opção abaixo:',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],

            // === FLUXO CONFIRMA ACORDO ===
            [
                'flow_id' => 5,
                'step_number' => 1,
                'prompt' => '@primeironome, aqui está o resumo da proposta:\n\n- Valor acordo: R$ *{{valorTotal}}*\n- Data de Vencimento: *{{dataVencimento}}*\n- Modo de pagamento: *{{modoPagamento}}*\n\n*Podemos formalizar o acordo?*',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],

            // === FLUXO ENVIA CÓDIGO DE BARRAS ===
            // Step 1: confirmação de geração do acordo
            [
                'flow_id' => 6,
                'step_number' => 1,
                'prompt' => 'Acordo gerado com sucesso!\n\n{{textAcordoFormalizado}}',
                'expected_input' => null,
                'next_step_condition' => 'repetir_pergunta',
            ],
            // Step 2: aviso de envio do código de barras
            [
                'flow_id' => 6,
                'step_number' => 2,
                'prompt' => 'Estou te enviando o código de barras para pagamento. 🔔',
                'expected_input' => null,
                'next_step_condition' => 'repetir_pergunta',
            ],
            // Step 3: código de barras (texto)
            [
                'flow_id' => 6,
                'step_number' => 3,
                'prompt' => '{{codigoBarras}}',
                'expected_input' => null,
                'next_step_condition' => 'repetir_pergunta',
            ],
            // Step 4: placeholder para anexo/arquivo do boleto
            [
                'flow_id' => 6,
                'step_number' => 4,
                'prompt' => 'Enviei o boleto em anexo. Por favor verifique o arquivo para pagamento.',
                'expected_input' => null,
                'next_step_condition' => 'repetir_pergunta',
            ],
            // Step 5: lembrete e menu de opções pós-envio
            [
                'flow_id' => 6,
                'step_number' => 5,
                'prompt' => 'Lembrando que é muito importante você realizar o pagamento para garantir esse valor.\n\nDeseja algo mais?',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],

            // === FLUXO ERROS ===
            [
                'flow_id' => 7,
                'step_number' => 1,
                'prompt' => 'Esta não é uma resposta válida. Por favor, responda conforme solicitado.',
                'expected_input' => null,
                'next_step_condition' => 'repetir_pergunta',
            ],

            // === FLUXO ADMINISTRATIVO ===
            [
                'flow_id' => 8,
                'step_number' => 1,
                'prompt' => 'Configuração de feriados e expediente: informe as novas datas e horários.',
                'expected_input' => 'texto',
                'next_step_condition' => 'salvar_configuracao',
            ],

            // === FLUXO AVALIAÇÃO ATENDENTE ===
            [
                'flow_id' => 9,
                'step_number' => 1,
                'prompt' => '@primeironome, avalie seu atendimento com uma nota de 0 a 10:',
                'expected_input' => 'numero',
                'next_step_condition' => 'avaliacao_finalizada',
            ],
            // === MODULO ALGO MAIS (flow_id = 10) ===
            [
                'flow_id' => 10,
                'step_number' => 1,
                'prompt' => 'Posso ajudar em algo mais?\n\nSelecione um botão abaixo:',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],
            // === MODULO ENCERRAR CONVERSA (flow_id = 11) ===
            [
                'flow_id' => 11,
                'step_number' => 1,
                'prompt' => 'Agradecemos a sua atenção.\n\nFoi um prazer te ajudar através deste canal!\n\nAté mais. 🙂',
                'expected_input' => null,
                'next_step_condition' => 'finalizar_atendimento',
            ],
            // === ABANDONO BOT (flow_id = 12) ===
            [
                'flow_id' => 12,
                'step_number' => 1,
                'prompt' => '{{head}}\n@primeironome, notei que parou de responder. Deseja continuar seu atendimento?',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],
            [
                'flow_id' => 12,
                'step_number' => 2,
                'prompt' => 'Seja bem-vindo(a) de volta! 👋',
                'expected_input' => null,
                'next_step_condition' => 'repetir_pergunta',
            ],
            [
                'flow_id' => 12,
                'step_number' => 3,
                'prompt' => 'Entendi que deseja encerrar. Posso finalizar o atendimento?',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],
            // === MODULO TRANSBORDO (flow_id = 13) ===
            [
                'flow_id' => 13,
                'step_number' => 1,
                'prompt' => 'Aguarde, seu atendimento está sendo transferido a um de nossos especialistas.',
                'expected_input' => null,
                'next_step_condition' => 'repetir_pergunta',
            ],
            // === ABANDONO ATENDENTE (flow_id = 14) ===
            [
                'flow_id' => 14,
                'step_number' => 1,
                'prompt' => '@primeironome, vamos continuar o atendimento?\n\nEstou te aguardando. 🙂\n\nSelecione um botão abaixo:',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],
            [
                'flow_id' => 14,
                'step_number' => 2,
                'prompt' => 'Seja bem-vindo(a) de volta! 👋',
                'expected_input' => null,
                'next_step_condition' => 'repetir_pergunta',
            ],
            [
                'flow_id' => 14,
                'step_number' => 3,
                'prompt' => 'Estou finalizando nossa conversa devido a falta de resposta.\n\nQuando desejar conversar novamente, estaremos à disposição. 🙂',
                'expected_input' => null,
                'next_step_condition' => 'finalizar_atendimento',
            ],
            [
                'flow_id' => 13,
                'step_number' => 2,
                'prompt' => 'No momento, estamos fora do horário de atendimento. Por favor, retorne no seguinte período:\n\n• Segunda a sexta de 08h às 19h.\n• Sábado de 08h às 14h.',
                'expected_input' => null,
                'next_step_condition' => 'fora_expediente_humano',
            ],
            [
                'flow_id' => 13,
                'step_number' => 3,
                'prompt' => '{{fraseFeriado}}',
                'expected_input' => null,
                'next_step_condition' => 'finalizar_atendimento',
            ],
            [
                'flow_id' => 11,
                'step_number' => 2,
                'prompt' => 'Deseja finalizar o atendimento agora?',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],
        ];

        foreach ($steps as $step) {
            DB::table('whatsapp_flow_steps')->insert([
                'flow_id' => $step['flow_id'],
                'step_number' => $step['step_number'],
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
