<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Acordo extends Model
{
    use HasFactory;

    protected $table = 'acordos';

    protected $fillable = [
        'contato_dado_id',
        'nome',
        'documento',
        'telefone',
        'phone_number_id',
        'status',
        'texto'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Acordo pertence a um ContatoDados
     */
    public function contatoDado(): BelongsTo
    {
        return $this->belongsTo(ContatoDados::class, 'contato_dado_id');
    }

    /**
     * Acessar a campanha através do contato_dados
     */
    public function getCampanha()
    {
        if ($this->contatoDado && $this->contatoDado->contato) {
            return $this->contatoDado->contato->campanhas()->first();
        }
        return null;
    }

    /**
     * Scopes para filtros comuns
     */
    public function scopeAtivos($query)
    {
        return $query->where('status', 'ativo');
    }

    public function scopePendentes($query)
    {
        return $query->where('status', 'pendente');
    }

    public function scopePorDocumento($query, $documento)
    {
        return $query->where('documento', $documento);
    }

    public function scopePorTelefone($query, $telefone)
    {
        return $query->where('telefone', $telefone);
    }
}
