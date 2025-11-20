<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TestSchedulingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa o agendamento atual na tabela available_slots';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->line("\n═══════════════════════════════════════════════════════════════");
        $this->line("🕐 TESTE DE AGENDAMENTO EM TEMPO REAL");
        $this->line("═══════════════════════════════════════════════════════════════");

        // Obtém horário atual em São Paulo
        $now = Carbon::now('America/Sao_Paulo');
        $daysOfWeek = [
            0 => 'domingo',
            1 => 'segunda',
            2 => 'terça',
            3 => 'quarta',
            4 => 'quinta',
            5 => 'sexta',
            6 => 'sábado',
        ];
        $dayOfWeek = $daysOfWeek[$now->dayOfWeek];
        $currentTime = $now->format('H:i:s');
        $currentDate = $now->format('Y-m-d H:i:s');

        $this->line("\n📅 Data/Hora atual (América/São Paulo):");
        $this->line("   {$currentDate}");
        $this->line("\n📆 Dia da semana: <fg=cyan>{$dayOfWeek}</> | Hora: <fg=cyan>{$currentTime}</>");

        // Busca TODOS os slots da tabela
        $allSlots = DB::table('available_slots')->orderBy('day_of_week')->get();

        if ($allSlots->isEmpty()) {
            $this->error("\n❌ Nenhum agendamento configurado na tabela available_slots!");
            $this->line("\n💡 Para adicionar agendamentos, execute:");
            $this->line("   php artisan tinker");
            $this->line("   DB::table('available_slots')->insert([");
            $this->line("       ['day_of_week' => 'segunda', 'start_time' => '09:00:00', 'end_time' => '18:00:00'],");
            $this->line("       ['day_of_week' => 'terça', 'start_time' => '09:00:00', 'end_time' => '18:00:00'],");
            $this->line("   ]);");
            $this->line("═══════════════════════════════════════════════════════════════\n");
            return;
        }

        $this->line("\n📋 TODOS OS AGENDAMENTOS CONFIGURADOS:");
        $this->table(
            ['Dia da Semana', 'Início', 'Fim'],
            $allSlots->map(function ($slot) {
                return [
                    $slot->day_of_week,
                    $slot->start_time,
                    $slot->end_time
                ];
            })->toArray()
        );

        // Busca o slot para hoje e hora atual
        $matchingSlot = DB::table('available_slots')
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<=', $currentTime)
            ->where('end_time', '>=', $currentTime)
            ->first();

        $this->line("\n🔍 VERIFICANDO AGENDAMENTO PARA AGORA:");
        $this->line("   Dia: <fg=cyan>{$dayOfWeek}</> | Hora: <fg=cyan>{$currentTime}</>");

        if ($matchingSlot) {
            $this->line("\n✅ <fg=green>HORÁRIO ATIVO!</>");
            $this->line("   - Dia: <fg=green>{$matchingSlot->day_of_week}</>");
            $this->line("   - Início: <fg=green>{$matchingSlot->start_time}</>");
            $this->line("   - Fim: <fg=green>{$matchingSlot->end_time}</>");
            $this->line("\n   🟢 MENSAGENS PODEM SER ENVIADAS AGORA!");
        } else {
            $this->line("\n❌ <fg=red>HORÁRIO INATIVO!</>");
            $this->line("   Nenhum agendamento ativo para {$dayOfWeek} às {$currentTime}");
            $this->line("\n   🔴 MENSAGENS SERÃO BLOQUEADAS E REENFILEIRADAS!");

            // Tenta encontrar o próximo slot disponível
            $tomorrow = $now->copy()->addDay();
            $tomorrowDayName = $daysOfWeek[$tomorrow->dayOfWeek];
            $nextSlot = DB::table('available_slots')
                ->where('day_of_week', $tomorrowDayName)
                ->orderBy('start_time')
                ->first();

            if ($nextSlot) {
                $this->line("\n💡 Próximo agendamento disponível:");
                $this->line("   - Dia: <fg=yellow>{$nextSlot->day_of_week}</>");
                $this->line("   - Horário: <fg=yellow>{$nextSlot->start_time}</> até <fg=yellow>{$nextSlot->end_time}</>");
            }
        }

        $this->line("\n═══════════════════════════════════════════════════════════════");
        $this->line("💡 PARA VER OS LOGS EM TEMPO REAL:");
        $this->line("   tail -f storage/logs/laravel.log");
        $this->line("═══════════════════════════════════════════════════════════════\n");
    }
}
