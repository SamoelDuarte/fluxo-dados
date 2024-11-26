<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlanilhasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('planilhas', function (Blueprint $table) {
            $table->id(); // Criando a coluna 'id' com auto incremento
            $table->foreignId('contrato_id')->constrained('contratos')->onDelete('cascade'); // Definindo a chave estrangeira com a tabela 'carteiras'
            $table->string('empresa'); // Coluna 'empresa'

            // Dados financeiros
            $table->decimal('valor_atualizado', 10, 2)->nullable(); // Valor atualizado
            $table->decimal('valor_proposta_1', 10, 2)->nullable(); // Valor proposta 1
            $table->string('data_vencimento_proposta_1')->nullable(); // Data vencimento proposta 1
            $table->integer('quantidade_parcelas_proposta_2')->nullable(); // Quantidade de parcelas proposta 2
            $table->decimal('valor_proposta_2', 10, 2)->nullable(); // Valor proposta 2
            $table->string('data_vencimento_proposta_2')->nullable(); // Data vencimento proposta 2

            // Contatos
            $table->string('telefone_recado', 255)->nullable(); // Telefone recado
            $table->string('linha_digitavel', 255)->nullable(); // Linha digitável

            // Telefones adicionais
            $table->string('dddtelefone_1', 255)->nullable(); // Telefone adicional 1
            $table->string('dddtelefone_2', 255)->nullable(); // Telefone adicional 2
            $table->string('dddtelefone_3', 255)->nullable(); // Telefone adicional 3
            $table->string('dddtelefone_4', 255)->nullable(); // Telefone adicional 4
            $table->string('dddtelefone_5', 255)->nullable(); // Telefone adicional 5
            $table->string('dddtelefone_6', 255)->nullable(); // Telefone adicional 6
            $table->string('dddtelefone_7', 255)->nullable(); // Telefone adicional 7
            $table->string('dddtelefone_8', 255)->nullable(); // Telefone adicional 8
            $table->string('dddtelefone_9', 255)->nullable(); // Telefone adicional 9
            $table->string('dddtelefone_10', 255)->nullable(); // Telefone adicional 10

            $table->timestamps(); // Timestamps padrão
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('planilhas');
    }
}
