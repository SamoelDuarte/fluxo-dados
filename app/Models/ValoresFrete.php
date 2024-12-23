<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValoresFrete extends Model
{

    protected $table = "valoresfrete";
    use HasFactory;

    // Adicione todas as colunas que podem ser preenchidas aqui
    protected $fillable = [
        'peso',
        'ITIL', 'SPC', 'SPG', 'RJC', 'RJG', 'MGC', 'ESC', 'PRC', 'SCC', 'RSC', 'DFC',
        'GOC', 'MSC', 'MTC', 'ALC', 'BAC', 'CEC', 'MAC', 'PBC', 'PEC', 'PIC', 'RNC', 
        'SEC', 'ACC', 'AMC', 'APC', 'PAC', 'ROC', 'RRC', 'TOC', 'ITII', 'SP1', 'SP2', 
        'SP3', 'RJI', 'MGI', 'ESI', 'PRI', 'SCI', 'RSI', 'DFI', 'GOI', 'MSI', 'MTI', 
        'ALI', 'BAI', 'CEI', 'MAI', 'PBI', 'PEI', 'PII', 'RNI', 'SEI', 'ACI', 'AMI', 
        'API', 'PAI', 'ROI', 'RRI', 'TOI', 'SPB', 'RJB', 'MGB', 'ESB', 'PRB', 'SCB', 
        'RSB', 'GOB', 'MSB', 'MTB', 'ALB', 'BAB', 'CEB', 'MAB', 'PBB', 'PEB', 'PIB', 
        'RNB', 'SEB', 'ACB', 'AMB', 'APB', 'PAB', 'ROB', 'TOB'
    ];
}
