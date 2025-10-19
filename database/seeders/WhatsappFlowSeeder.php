<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WhatsappFlowSeeder extends Seeder
{
    public function run(): void
    {
        $flows = [
            ['id' => 1, 'name' => 'Fluxo Inicial'],
            ['id' => 2, 'name' => 'Fluxo Negociar'],
            ['id' => 3, 'name' => 'Fluxo Proposta'],
            ['id' => 4, 'name' => 'Fluxo Acordos'],
            ['id' => 5, 'name' => 'Fluxo Confirma Acordo'],
            ['id' => 6, 'name' => 'Fluxo Envia Código de Barras'],
            ['id' => 7, 'name' => 'Fluxo Erros'],
            ['id' => 8, 'name' => 'Fluxo Administrativo'],
            ['id' => 9, 'name' => 'Fluxo Avaliação Atendente'],
        ];

        foreach ($flows as $flow) {
            DB::table('whatsapp_flows')->updateOrInsert(['id' => $flow['id']], $flow);
        }
    }
}
