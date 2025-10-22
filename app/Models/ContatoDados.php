<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContatoDados extends Model
{
    use HasFactory;

    protected $table = 'contato_dados';

    protected $fillable = [
        'contato_id',
        'telefone',
        'nome',
        'document',
        'cod_cliente',
        'data_vencimento',
        'dias_atraso',
        'valor',
        'carteira',
        'send',
        'play',
    ];

    public function contato()
    {
        return $this->belongsTo(Contato::class, 'contato_id');
    }
}
