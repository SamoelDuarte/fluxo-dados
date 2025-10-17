<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WhatsappFlowSeeder extends Seeder
{
    public function run(): void
    {
        // === FLUXOS PRINCIPAIS ===
        $flows = [
            ['name' => 'Fluxo Inicial', 'description' => 'Saudação e solicitação de CPF'],
            ['name' => 'Fluxo Negociar', 'description' => 'Identifica contratos e negociações'],
            ['name' => 'Fluxo Proposta', 'description' => 'Apresenta propostas de pagamento'],
            ['name' => 'Fluxo Acordos', 'description' => 'Consulta acordos existentes'],
            ['name' => 'Fluxo Confirma Acordo', 'description' => 'Confirma acordo e gera boleto'],
            ['name' => 'Fluxo Envia Código de Barras', 'description' => 'Envia código de barras do boleto'],
            ['name' => 'Fluxo Erros', 'description' => 'Tratamento de erros de input, API e mídias'],
            ['name' => 'Fluxo Administrativo', 'description' => 'Gerencia feriados e horários de atendimento'],
            ['name' => 'Fluxo Avaliação Atendente', 'description' => 'Coleta e registra avaliações de atendimento'],
        ];

        foreach ($flows as $flow) {
            DB::table('whatsapp_flows')->insert([
                'name' => $flow['name'],
                'description' => $flow['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

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
                'prompt' => 'Verificando suas informações...',
                'expected_input' => null,
                'next_step_condition' => 'fluxo_negociar',
            ],

            // === FLUXO NEGOCIAR ===
            [
                'flow_id' => 2,
                'step_number' => 1,
                'prompt' => '@primeironome, localizei *{{qtdContratos}}* contratos em aberto. Deseja negociar?',
                'expected_input' => 'botao',
                'next_step_condition' => 'fluxo_proposta',
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
}
