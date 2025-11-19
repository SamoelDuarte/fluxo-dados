<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contato extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * Contato tem muitos ContatoDados
     */
    public function dados(): HasMany
    {
        return $this->hasMany(ContatoDados::class, 'contato_id');
    }

    /**
     * Contato tem muitas Campanhas através da tabela campanha_contato
     */
    public function campanhas(): BelongsToMany
    {
        return $this->belongsToMany(Campanha::class, 'campanha_contato', 'contato_id', 'campanha_id');
    }
}
