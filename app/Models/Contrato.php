<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contrato extends Model
{
    use HasFactory;
    protected $fillable = ['carteira_id', 'contrato', 'nome','documento', 'lote_id','erro', 'mensagem_erro'];

    // Definindo o relacionamento com o lote
    public function lote()
    {
        return $this->belongsTo(Lote::class);
    }

    // Definindo o relacionamento com a carteira
    public function carteira()
    {
        return $this->belongsTo(Carteira::class);
    }

    // Relacionamento com a Planilha
    public function planilhas()
    {
        return $this->hasMany(Planilha::class);
    }
}
