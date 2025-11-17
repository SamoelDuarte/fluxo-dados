<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Campanha extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'status', 'mensagem', 'img_campanha', 'template_id', 'template_name', 'waba_id'];

    /**
     * Relacionamento com Contatos
     */
    public function contatos(): BelongsToMany
    {
        return $this->belongsToMany(Contato::class, 'campanha_contato', 'campanha_id', 'contato_id');
    }

    /**
     * Obter phone_number_ids da campanha (como coleção de strings)
     * Acessa a tabela pivot campanha_telefone diretamente
     */
    public function phoneNumbers()
    {
        return DB::table('campanha_telefone')
            ->where('campanha_id', $this->id)
            ->pluck('phone_number_id');
    }

    /**
     * Relacionamento com Imagem Principal da Campanha
     */
    public function imagemPrincipal(): BelongsTo
    {
        return $this->belongsTo(ImagemCampanha::class, 'img_campanha');
    }
}
