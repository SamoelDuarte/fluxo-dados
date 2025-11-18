<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Adicionar coluna contato_dado_id se não existir
        if (!Schema::hasColumn('acordos', 'contato_dado_id')) {
            Schema::table('acordos', function (Blueprint $table) {
                $table->foreignId('contato_dado_id')->nullable()->constrained('contato_dados')->onDelete('set null')->after('id');
            });
        }

        // Preencher acordos existentes com contato_dado_id baseado em documento/telefone
        $acordos = DB::table('acordos')->whereNull('contato_dado_id')->get();

        foreach ($acordos as $acordo) {
            // Tenta encontrar por documento primeiro (mais preciso)
            $contatoDado = DB::table('contato_dados')
                ->where('document', $acordo->documento)
                ->first();

            // Se não encontrar por documento, tenta por telefone
            if (!$contatoDado) {
                $contatoDado = DB::table('contato_dados')
                    ->where('telefone', $acordo->telefone)
                    ->first();
            }

            // Atualiza o acordo com o contato_dado_id encontrado
            if ($contatoDado) {
                DB::table('acordos')
                    ->where('id', $acordo->id)
                    ->update(['contato_dado_id' => $contatoDado->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acordos', function (Blueprint $table) {
            $table->dropForeignIdFor('contato_dados');
        });
    }
};
