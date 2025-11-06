@extends('layouts.app')

@section('title', 'Agendamentos')

@section('css')
    <style>
        .slot-card {
            border-left: 4px solid #4e73df;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }

        .slot-card.active {
            border-left-color: #1cc88a;
            background-color: #f8fff9;
        }

        .slot-card.inactive {
            border-left-color: #e74c3c;
            background-color: #fef8f8;
        }

        .slot-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .day-header {
            font-weight: 600;
            padding: 12px;
            border-radius: 4px 4px 0 0;
            color: white;
        }

        .day-header.active {
            background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
        }

        .day-header.inactive {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .time-input {
            font-size: 0.95rem;
        }

        .switch-label {
            margin-bottom: 0;
            font-weight: 500;
        }

        .slot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            align-items: stretch;
        }

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 30px;
        }

        .custom-control-input:checked ~ .custom-control-label::before {
            background-color: #1cc88a;
            border-color: #1cc88a;
        }

        .form-group label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }

        .card {
            border: none;
            border-radius: 8px;
        }

        .card-body {
            padding: 20px;
            flex-grow: 1;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/home">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('campanhas.crud.index') }}">Campanhas</a></li>
                <li class="breadcrumb-item active" aria-current="page">Agendamentos</li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-calendar-alt"></i> Gerenciar Agendamentos</h1>
        </div>

        <!-- Info Card -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle"></i>
                    <strong>Dica:</strong> Configure os horários de funcionamento para cada dia da semana. Os horários
                    inativos não poderão receber agendamentos.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>

        <!-- Form -->
        <form action="{{ route('agendamento.update') }}" method="POST">
            @csrf

            <div class="slot-grid">
                @php
                    $days = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
                @endphp

                @foreach ($days as $index => $day)
                    @php
                        $slot = $availability->firstWhere('day_of_week', $day);
                        $isActive = $slot && $slot->start_time && $slot->end_time;
                    @endphp

                    <div class="slot-card {{ $isActive ? 'active' : 'inactive' }}">
                        <div class="day-header {{ $isActive ? 'active' : 'inactive' }}">
                            {{ ucfirst($day) }}
                        </div>

                        <div class="card-body">
                            <!-- Hidden field to track if day is active -->
                            <input type="hidden" id="{{ 'active-' . $index }}" name="days[{{ $day }}][active]" value="">

                            <!-- Toggle Active/Inactive -->
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" class="custom-control-input" id="{{ 'switch-' . $index }}"
                                    onchange="toggleSlotStatus(this, '{{ $index }}', '{{ $day }}')"
                                    {{ $isActive ? 'checked' : '' }}>
                                <label class="custom-control-label switch-label" for="{{ 'switch-' . $index }}">
                                    {{ $isActive ? '✓ Ativo' : '✗ Inativo' }}
                                </label>
                            </div>

                            <!-- Start Time -->
                            <div class="form-group">
                                <label for="{{ 'start-' . $index }}">Início:</label>
                                <input type="time" class="form-control time-input" id="{{ 'start-' . $index }}"
                                    name="days[{{ $day }}][start_time]"
                                    value="{{ $slot && $slot->start_time ? date('H:i', strtotime($slot->start_time)) : '' }}"
                                    {{ !$isActive ? 'disabled' : '' }}>
                            </div>

                            <!-- End Time -->
                            <div class="form-group mb-0">
                                <label for="{{ 'end-' . $index }}">Término:</label>
                                <input type="time" class="form-control time-input" id="{{ 'end-' . $index }}"
                                    name="days[{{ $day }}][end_time]"
                                    value="{{ $slot && $slot->end_time ? date('H:i', strtotime($slot->end_time)) : '' }}"
                                    {{ !$isActive ? 'disabled' : '' }}>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Action Buttons -->
            <div class="button-group">
                <a href="{{ route('campanhas.crud.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
            </div>
        </form>
    </div>

    <script>
        function toggleSlotStatus(element, index, day) {
            const startInput = document.getElementById('start-' + index);
            const endInput = document.getElementById('end-' + index);
            const activeInput = document.getElementById('active-' + index);
            const card = element.closest('.slot-card');
            const header = card.querySelector('.day-header');
            const label = element.nextElementSibling;

            if (element.checked) {
                // Ativar
                startInput.disabled = false;
                endInput.disabled = false;
                activeInput.value = 'on';
                label.textContent = '✓ Ativo';

                card.classList.remove('inactive');
                card.classList.add('active');

                header.classList.remove('inactive');
                header.classList.add('active');
            } else {
                // Desativar
                startInput.disabled = true;
                endInput.disabled = true;
                startInput.value = '';
                endInput.value = '';
                activeInput.value = '';
                label.textContent = '✗ Inativo';

                card.classList.remove('active');
                card.classList.add('inactive');

                header.classList.remove('active');
                header.classList.add('inactive');
            }
        }

        // Inicializar estado dos campos
        document.addEventListener('DOMContentLoaded', function() {
            @php
                $days = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
            @endphp

            @foreach ($days as $index => $day)
                @php
                    $slot = $availability->firstWhere('day_of_week', $day);
                    $isActive = $slot && $slot->start_time && $slot->end_time;
                @endphp
                const input{{ $index }} = document.getElementById('switch-{{ $index }}');
                const activeInput{{ $index }} = document.getElementById('active-{{ $index }}');
                
                if (input{{ $index }}.checked) {
                    activeInput{{ $index }}.value = 'on';
                    document.getElementById('start-{{ $index }}').disabled = false;
                    document.getElementById('end-{{ $index }}').disabled = false;
                } else {
                    activeInput{{ $index }}.value = '';
                    document.getElementById('start-{{ $index }}').disabled = true;
                    document.getElementById('end-{{ $index }}').disabled = true;
                }
            @endforeach
        });
    </script>
@endsection
