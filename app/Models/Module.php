<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle Module.
 * Représente un module d'une formation.
 */
class Module extends Model
{
    /**
     * Champs autorisés à l'insertion.
     */
    protected $fillable = [
        'titre',
        'contenu',
        'ordre',
        'formation_id',
    ];

    /**
     * Relation : un module appartient à une formation.
     */
    public function formation()
    {
        return $this->belongsTo(Formation::class, 'formation_id');
    }

    /**
     * Relation : utilisateurs ayant terminé ce module.
     */
    public function utilisateurs()
    {
        return $this->belongsToMany(User::class, 'module_user', 'module_id', 'utilisateur_id')
            ->withPivot('termine')
            ->withTimestamps();
    }
}
