<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model as MongoModel;

/**
 * Modèle ActivityLog.
 * Enregistre les événements importants dans MongoDB.
 */
class ActivityLog extends MongoModel
{
    // Connexion MongoDB
    protected $connection = 'mongodb';

    // Collection MongoDB
    protected $collection = 'activity_logs';

    protected $fillable = [
        'event',
        'user_id',
        'formation_id',
        'module_id',
        'old_values',
        'new_values',
        'timestamp',
    ];
}
