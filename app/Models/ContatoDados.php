<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContatoDados extends Model
{
    use HasFactory;

    protected $table = 'contato_dados';

    protected $fillable = [
        'id_contrato',
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

    /**
     * ContatoDados pertence a um Contato
     */
    public function contato(): BelongsTo
    {
        return $this->belongsTo(Contato::class, 'contato_id');
    }

    /**
     * ContatoDados tem muitos Acordos
     */
    public function acordos(): HasMany
    {
        return $this->hasMany(Acordo::class, 'contato_dado_id');
    }

    /**
     * Acessar campanhas relacionadas através do contato
     */
    public function campanhas()
    {
        return $this->contato->campanhas();
    }
}
