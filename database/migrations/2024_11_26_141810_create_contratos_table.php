<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContratosTable extends Migration
{
    public function up()
    {
        // Criação da tabela contratos
        Schema::create('contratos', function (Blueprint $table) {
            $table->id(); // Criando a coluna 'id' com auto incremento
            $table->foreignId('carteira_id')->constrained('carteiras')->onDelete('cascade'); // Definindo a chave estrangeira com a tabela 'carteiras'
            $table->string('contrato');  // Informações do contrato
            $table->string('documento'); // Documento associado
            $table->text('nome')->nullable(); // Nome, caso exista
            $table->foreignId('lote_id')->constrained('lotes')->onDelete('cascade'); // Relacionamento com a tabela 'lotes'
            $table->boolean('request')->default(false); // Indica se foi feita uma requisição
            $table->boolean('erro')->default(false); // Indica se ocorreu um erro
            $table->text('mensagem_erro')->nullable(); // Mensagem detalhada do erro, caso exista
            $table->timestamps();  // Campos created_at e updated_at
        });
    }

    public function down()
    {
        // Deleta a tabela 'contratos' e suas chaves estrangeiras
        Schema::dropIfExists('contratos');
    }
}
