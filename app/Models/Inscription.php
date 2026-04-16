<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle Inscription
 * Représente l'inscription d'un apprenant à une formation.
 */
class Inscription extends Model
{
    /**
     * Champs autorisés pour l'insertion.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'utilisateur_id',
        'formation_id',
        'progression',
    ];

    /**
     * Relation vers l'utilisateur.
     */
    public function utilisateur()
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    /**
     * Relation vers la formation.
     */
    public function formation()
    {
        return $this->belongsTo(Formation::class);
    }
}
