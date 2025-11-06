<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campanha extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'status', 'mensagem', 'img_campanha'];

    /**
     * Relacionamento com Contatos
     */
    public function contatos(): BelongsToMany
    {
        return $this->belongsToMany(Contato::class, 'campanha_contato', 'campanha_id', 'contato_id');
    }

    /**
     * Relacionamento com Telefones
     */
    public function telefones(): BelongsToMany
    {
        return $this->belongsToMany(Telefone::class, 'campanha_telefone', 'campanha_id', 'telefone_id');
    }

    /**
     * Relacionamento com Imagem Principal da Campanha
     */
    public function imagemPrincipal(): BelongsTo
    {
        return $this->belongsTo(ImagemCampanha::class, 'img_campanha');
    }
}