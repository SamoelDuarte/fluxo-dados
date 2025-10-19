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
            $table->string('prompt'); // mensagem a ser enviada
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
                'next_step_condition' => 'solicita_cpf',
            ],
            [
                'flow_id' => 1,
                'step_number' => 2,
                'prompt' => 'Por favor, informe seu *CPF/CNPJ* (apenas números):',
                'expected_input' => 'cpf',
                'next_step_condition' => 'api_valida_cpf',
            ],
            [
                'flow_id' => 1,
                'step_number' => 3,
                'prompt' => 'Estou buscando as informações necessárias para seguirmos por aqui, só um instante.',
                'expected_input' => null,
                'next_step_condition' => 'fluxo_negociar',
            ],
            // Mensagem quando não encontra cadastro
            [
                'flow_id' => 1,
                'step_number' => 4,
                'prompt' => 'Não localizei cadastro com esse documento. Por favor digite novamente o CPF/CNPJ (apenas números).',
                'expected_input' => 'cpf',
                'next_step_condition' => 'api_valida_cpf',
            ],

            // === FLUXO NEGOCIAR ===
            // Step 1: mensagem inicial informando débitos
            [
                'flow_id' => 2,
                'step_number' => 1,
                'prompt' => '@primeironome! Identifiquei 1 débito em atraso a *5* dias.\n\nAguarde enquanto localizo a melhor proposta para você... ',
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
                'prompt' => '@primeironome, localizei *8* contratos em aberto.\n\nSelecione o botão abaixo para conferir:',
                'expected_input' => 'botao',
                'next_step_condition' => 'processar_opcao',
            ],

            // === FLUXO PROPOSTA ===
            [
                'flow_id' => 3,
                'step_number' => 1,
                'prompt' => 'A melhor oferta é de R$ *{{valorTotal}}* com vencimento em *{{data}}*. Deseja gerar o boleto?',
                'expected_input' => 'sim_nao',
                'next_step_condition' => 'fluxo_confirma_acordo',
            ],

            // === FLUXO ACORDOS ===
            [
                'flow_id' => 4,
                'step_number' => 1,
                'prompt' => '@primeironome, localizei *{{qtdAcordos}}* acordos formalizados. Deseja visualizar?',
                'expected_input' => 'botao',
                'next_step_condition' => 'fluxo_envia_codigo_barras',
            ],

            // === FLUXO CONFIRMA ACORDO ===
            [
                'flow_id' => 5,
                'step_number' => 1,
                'prompt' => 'Resumo do acordo: Valor R$ *{{valorTotal}}*, Vencimento *{{dataVencimento}}*. Confirmar formalização?',
                'expected_input' => 'sim_nao',
                'next_step_condition' => 'fluxo_envia_codigo_barras',
            ],

            // === FLUXO ENVIA CÓDIGO DE BARRAS ===
            [
                'flow_id' => 6,
                'step_number' => 1,
                'prompt' => 'Estou te enviando o código de barras para pagamento: {{codigoBarras}}',
                'expected_input' => null,
                'next_step_condition' => 'fluxo_algo_mais',
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
