<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Acordo extends Model
{
    use HasFactory;

    protected $table = 'acordos';

    protected $fillable = [
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
