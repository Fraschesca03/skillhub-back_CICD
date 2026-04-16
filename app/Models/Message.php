<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Modèle Message — stocké dans MongoDB.
 * Les IDs expéditeur/destinataire référencent la table MySQL users.
 */
class Message extends Model
{
    // Connexion MongoDB
    protected $connection = 'mongodb';

    // Collection MongoDB
    protected $collection = 'messages';

    protected $fillable = [
        'expediteur_id',
        'destinataire_id',
        'contenu',
        'lu',
    ];

    protected $casts = [
        'lu'              => 'boolean',
        'expediteur_id'   => 'integer',
        'destinataire_id' => 'integer',
    ];

    /**
     * Relation : l'expéditeur (table MySQL users).
     */
    public function expediteur()
    {
        return $this->belongsTo(User::class, 'expediteur_id');
    }

    /**
     * Relation : le destinataire (table MySQL users).
     */
    public function destinataire()
    {
        return $this->belongsTo(User::class, 'destinataire_id');
    }
}
