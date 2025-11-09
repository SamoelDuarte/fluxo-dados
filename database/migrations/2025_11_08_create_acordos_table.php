<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acordos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('documento'); // CPF/CNPJ
            $table->string('telefone');
            $table->string('phone_number_id')->nullable(); // ID do telefone WhatsApp
            $table->enum('status', ['pendente', 'ativo', 'finalizado', 'cancelado'])->default('pendente');
            $table->longText('texto')->nullable(); // Texto/detalhes do acordo
            $table->timestamps();
            
            // Índices para buscas rápidas
            $table->index('documento');
            $table->index('telefone');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acordos');
    }
};
