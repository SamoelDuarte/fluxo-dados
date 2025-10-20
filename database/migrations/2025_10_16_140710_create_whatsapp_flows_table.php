<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_flows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
       
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
            ['name' => 'Módulo Algo Mais', 'description' => 'Menu adicional: Início / Atendimento / Encerrar conversa'],
            ['name' => 'Módulo Encerrar Conversa', 'description' => 'Mensagem de encerramento da conversa e opção de finalizar atendimento'],
            ['name' => 'Abandono Bot', 'description' => 'Fluxo de abandono por inatividade — pergunta de retorno e roteamento para Encerrar/Retomar'],
            ['name' => 'Módulo Transbordo', 'description' => 'Transfere atendimento para um especialista de acordo com expediente, sábado e feriados'],
            ['name' => 'Abandono Atendente', 'description' => 'Gatilho de abandono quando atendente aguarda resposta — Continuar/Finalizar'],
        ];

        foreach ($flows as $flow) {
            DB::table('whatsapp_flows')->insert([
                'name' => $flow['name'],
                'description' => $flow['description'],
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
        Schema::dropIfExists('whatsapp_flows');
    }
};
