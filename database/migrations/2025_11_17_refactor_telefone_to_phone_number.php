<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Refatora a tabela campanha_telefone para usar phone_number_id em vez de telefone_id
     * Apaga a tabela telefones que não é mais necessária
     */
    public function up()
    {
        // Desabilitar verificação de foreign key
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            // 1. Apagar a tabela campanha_telefone (com foreign key)
            Schema::dropIfExists('campanha_telefone');

            // 2. Apagar a tabela telefones (não é mais necessária)
            Schema::dropIfExists('telefones');

            // 3. Recriar campanha_telefone com phone_number_id em vez de telefone_id
            Schema::create('campanha_telefone', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campanha_id')->constrained('campanhas')->onDelete('cascade');
                $table->string('phone_number_id')->comment('ID do número de telefone da WABA (não é foreign key, vem do WABA)');
                $table->timestamps();
            });
        } finally {
            // Reabilitar verificação de foreign key
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            Schema::dropIfExists('campanha_telefone');

            // Recriar relação antiga
            Schema::create('campanha_telefone', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campanha_id')->constrained('campanhas')->onDelete('cascade');
                $table->foreignId('telefone_id')->constrained('telefones')->onDelete('cascade');
                $table->timestamps();
            });

            // Recriar tabela telefones se necessário (rollback)
            Schema::create('telefones', function (Blueprint $table) {
                $table->id();
                $table->string('phone_number_id')->nullable();
                $table->string('phone_number');
                $table->timestamps();
            });
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
};
