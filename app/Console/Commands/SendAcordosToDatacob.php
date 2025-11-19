<?php

namespace App\Console\Commands;

use App\Models\Acordo;
use App\Jobs\SendAcordoToDatacob;
use Illuminate\Console\Command;

class SendAcordosToDatacob extends Command
{
    protected $signature = 'acordos:send-datacob';
    protected $description = 'Envia acordos pendentes para Datacob API';

    public function handle()
    {
        $acordosPendentes = Acordo::where('id', '178')
            ->whereHas('contatoDado', function ($query) {
                $query->whereNotNull('id_contrato');
            })
            ->get();

        if ($acordosPendentes->isEmpty()) {
            $this->info('Nenhum acordo pendente encontrado');
            return 0;
        }

        $this->info("Encontrados {$acordosPendentes->count()} acordos pendentes");

        foreach ($acordosPendentes as $acordo) {
            SendAcordoToDatacob::dispatch($acordo->id)->onQueue('default');
            $this->line("✓ Acordo {$acordo->id} adicionado à fila");
        }

        return 0;
    }
}
