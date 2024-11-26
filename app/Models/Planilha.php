<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Planilha extends Model
{
    use HasFactory;

    // A tabela associada ao modelo.
    protected $table = 'planilhas';

    // A chave primária da tabela.
    protected $primaryKey = 'id';

    // Os atributos que podem ser atribuídos em massa (Mass Assignment).
    protected $fillable = [
        'contrato_id',
        'contrato',
        'empresa',
        'valor_atualizado',
        'valor_proposta_1',
        'data_vencimento_proposta_1',
        'quantidade_parcelas_proposta_2',
        'valor_proposta_2',
        'data_vencimento_proposta_2',
        'telefone_recado',
        'linha_digitavel',
        'dddtelefone_1',
        'dddtelefone_2',
        'dddtelefone_3',
        'dddtelefone_4',
        'dddtelefone_5',
        'dddtelefone_6',
        'dddtelefone_7',
        'dddtelefone_8',
        'dddtelefone_9',
        'dddtelefone_10',
    ];

    // Definindo o relacionamento com a tabela de contratos
    public function contrato()
    {
        return $this->belongsTo(Contrato::class);  // Um contrato pode ter várias planilhas
    }

    

    // Aqui você pode adicionar métodos adicionais conforme necessário
}
