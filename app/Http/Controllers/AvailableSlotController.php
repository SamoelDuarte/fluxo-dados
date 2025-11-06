<?php

namespace App\Http\Controllers;

use App\Models\AvailableSlot;
use Illuminate\Http\Request;

class AvailableSlotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $slots = AvailableSlot::all();
        $days = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
        
        $availability = collect();
        foreach ($days as $day) {
            $slot = $slots->firstWhere('day_of_week', $day);
            if (!$slot) {
                $availability->push(new AvailableSlot([
                    'day_of_week' => $day,
                    'start_time' => null,
                    'end_time' => null,
                ]));
            } else {
                $availability->push($slot);
            }
        }

        return view('campanhas.agendamento.index', compact('availability'));
    }

    /**
     * Store or update the available slots.
     */
    public function update(Request $request)
    {
        try {
            $days = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

            foreach ($days as $day) {
                $dayData = $request->input("days.{$day}", []);
                
                // Se o checkbox está marcado (ativo), salva com horários
                if (isset($dayData['active']) && !empty($dayData['start_time']) && !empty($dayData['end_time'])) {
                    // Valida os horários
                    if (!preg_match('/^\d{2}:\d{2}$/', $dayData['start_time']) || !preg_match('/^\d{2}:\d{2}$/', $dayData['end_time'])) {
                        continue;
                    }

                    $slot = AvailableSlot::where('day_of_week', $day)->first();
                    
                    if ($slot) {
                        $slot->update([
                            'start_time' => $dayData['start_time'],
                            'end_time' => $dayData['end_time'],
                        ]);
                    } else {
                        AvailableSlot::create([
                            'day_of_week' => $day,
                            'start_time' => $dayData['start_time'],
                            'end_time' => $dayData['end_time'],
                        ]);
                    }
                } else {
                    // Se não está ativo, deleta ou deixa sem horário
                    AvailableSlot::where('day_of_week', $day)->delete();
                }
            }

            return redirect()->route('agendamento.index')
                ->with('success', '✓ Agendamentos atualizados com sucesso!');
        } catch (\Exception $e) {
            return redirect()->route('agendamento.index')
                ->with('error', '✗ Erro ao atualizar agendamentos: ' . $e->getMessage());
        }
    }
}
