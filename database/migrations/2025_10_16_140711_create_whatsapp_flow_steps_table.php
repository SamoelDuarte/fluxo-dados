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
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_steps');
    }
};
