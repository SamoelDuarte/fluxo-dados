<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('contato_dados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contato_id')->constrained('contatos')->onDelete('cascade');
            $table->string('telefone')->nullable();
            $table->string('nome')->nullable();
            $table->string('document')->nullable();
            $table->string('cod_cliente')->nullable();
            $table->date('data_vencimento')->nullable();
            $table->integer('dias_atraso')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->string('carteira')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('contato_dados');
    }
};
