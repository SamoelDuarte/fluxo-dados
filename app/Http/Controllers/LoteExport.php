<?php

namespace App\Http\Controllers;

use App\Models\Planilha;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LoteExport implements FromCollection, WithHeadings
{
    protected $planilhas;

    public function __construct($planilhas)
    {
        $this->planilhas = $planilhas;
    }

    public function collection()
    {
        return collect($this->planilhas);
    }

    public function headings(): array
    {
        return [
            'CPF', 'NOME', 'EMPRESA', 'CARTEIRA', 'VALOR ATUALIZADO', 'VALOR PROPOSTA 1',
            'DATA VENCIMENTO PROPOSTA 1', 'QUANTIDADE PARCELAS PROPOSTA 2', 'VALOR PROPOSTA 2',
            'DATA VENCIMENTO PROPOSTA 2', 'TELEFONE RECADO', 'LINHA DIGITAVEL', 'DDDTELEFONE 1',
            'DDDTELEFONE 2', 'DDDTELEFONE 3', 'DDDTELEFONE 4', 'DDDTELEFONE 5', 'DDDTELEFONE 6',
            'DDDTELEFONE 7', 'DDDTELEFONE 8', 'DDDTELEFONE 9', 'DDDTELEFONE 10'
        ];
    }
}
