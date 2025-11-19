<?php

namespace App\Console\Commands;

use App\Models\Acordo;
use Illuminate\Console\Command;

class CancelAcordosDatacob extends Command
{
    protected $signature = 'acordos:cancel-datacob';
    protected $description = 'Cancela acordos que entraram na fila (status enviado)';

    public function handle()
    {
        $acordosEnviados = Acordo::where('status', 'enviado')->get();

        if ($acordosEnviados->isEmpty()) {
            $this->info('Nenhum acordo enviado para cancelar');
            return 0;
        }

        $this->info("Encontrados {$acordosEnviados->count()} acordos enviados");

        foreach ($acordosEnviados as $acordo) {
            $acordo->update(['status' => 'pendente']);
            $this->line("✓ Acordo {$acordo->id} voltou para pendente");
        }

        $this->info("✅ {$acordosEnviados->count()} acordos cancelados com sucesso");

        return 0;
    }
}
