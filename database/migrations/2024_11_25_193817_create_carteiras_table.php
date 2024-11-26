<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCarteirasTable extends Migration
{
    /**
     * Execute as migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('carteiras', function (Blueprint $table) {
            $table->id(); // Cria a coluna 'id' com auto incremento
            $table->integer('codigo_usuario_cobranca'); // Coluna para 'codigo_usuario_cobranca'
            $table->timestamps(); // Cria as colunas 'created_at' e 'updated_at'
        });

        $carteiras = [
            875 => 30,
            874 => 24,
            873 => 24,
            872 => 24,
            871 => 24,
            870 => 24,
            869 => 24,
        ];

        foreach ($carteiras as $id => $codigo_usuario_cobranca) {
            DB::table('carteiras')->insert([
                'id' => $id,
                'codigo_usuario_cobranca' => $codigo_usuario_cobranca,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverter a migration.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('carteiras');
    }
}
