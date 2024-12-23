<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateValoresfreteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('valoresfrete', function (Blueprint $table) {
            $table->id();
            $table->string('peso')->nullable(); // Exemplo para o campo Peso em kg

            // Adicionar todas as colunas
            $columns = [
                'ITIL', 'SPC', 'SPG', 'RJC', 'RJG', 'MGC', 'ESC', 'PRC', 'SCC', 'RSC', 'DFC',
                'GOC', 'MSC', 'MTC', 'ALC', 'BAC', 'CEC', 'MAC', 'PBC', 'PEC', 'PIC', 'RNC', 
                'SEC', 'ACC', 'AMC', 'APC', 'PAC', 'ROC', 'RRC', 'TOC', 'ITII', 'SP1', 'SP2', 
                'SP3', 'RJI', 'MGI', 'ESI', 'PRI', 'SCI', 'RSI', 'DFI', 'GOI', 'MSI', 'MTI', 
                'ALI', 'BAI', 'CEI', 'MAI', 'PBI', 'PEI', 'PII', 'RNI', 'SEI', 'ACI', 'AMI', 
                'API', 'PAI', 'ROI', 'RRI', 'TOI', 'SPB', 'RJB', 'MGB', 'ESB', 'PRB', 'SCB', 
                'RSB', 'GOB', 'MSB', 'MTB', 'ALB', 'BAB', 'CEB', 'MAB', 'PBB', 'PEB', 'PIB', 
                'RNB', 'SEB', 'ACB', 'AMB', 'APB', 'PAB', 'ROB', 'TOB'
            ];

            foreach ($columns as $column) {
                $table->decimal($column, 10, 2)->nullable();
            }

            $table->timestamps(); // Campos padrão created_at e updated_at
        });

        // Inserção dos valores na tabela
        $values = [
            ['peso' => '0.500'],
            ['peso' => '1.000'],
            ['peso' => '2.000'],
            ['peso' => '3.000'],
            ['peso' => '4.000'],
            ['peso' => '5.000'],
            ['peso' => '6.000'],
            ['peso' => '7.000'],
            ['peso' => '8.000'],
            ['peso' => '9.000'],
            ['peso' => '10.000'],
            ['peso' => '11.000'],
            ['peso' => '12.000'],
            ['peso' => '13.000'],
            ['peso' => '14.000'],
            ['peso' => '15.000'],
            ['peso' => '16.000'],
            ['peso' => '17.000'],
            ['peso' => '18.000'],
            ['peso' => '19.000'],
            ['peso' => '20.000'],
            ['peso' => '21.000'],
            ['peso' => '22.000'],
            ['peso' => '23.000'],
            ['peso' => '24.000'],
            ['peso' => '25.000'],
            ['peso' => '26.000'],
            ['peso' => '27.000'],
            ['peso' => '28.000'],
            ['peso' => '29.000'],
            ['peso' => '30.000'],
            ['peso' => 'Adicional'],
        ];

        DB::table('valoresfrete')->insert($values);

     
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('valoresfrete');
    }
}
