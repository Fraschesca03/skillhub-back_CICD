<?php

namespace App\Services;

use App\Models\ActivityLog;

/**
 * Service d'historisation des actions dans MongoDB.
 */
class ActivityLogService
{
    /**
     * Enregistrer la consultation d'une formation.
     */
    public static function consultationFormation(int $formationId, ?int $userId): void
    {
        ActivityLog::create([
            'event'        => 'course_view',
            'user_id'      => $userId,
            'formation_id' => $formationId,
            'timestamp'    => now()->toISOString(),
        ]);
    }

    /**
     * Enregistrer l'inscription à une formation.
     */
    public static function inscriptionFormation(int $formationId, int $userId): void
    {
        ActivityLog::create([
            'event'        => 'course_enrollment',
            'user_id'      => $userId,
            'formation_id' => $formationId,
            'timestamp'    => now()->toISOString(),
        ]);
    }

    /**
     * Enregistrer la création d'une formation.
     */
    public static function creationFormation(int $formationId, int $userId): void
    {
        ActivityLog::create([
            'event'        => 'course_creation',
            'user_id'      => $userId,
            'formation_id' => $formationId,
            'timestamp'    => now()->toISOString(),
        ]);
    }

    /**
     * Enregistrer la modification d'une formation.
     * Stocke les valeurs avant et après modification.
     */
    public static function modificationFormation(int $formationId, int $userId, array $oldValues, array $newValues): void
    {
        ActivityLog::create([
            'event'        => 'course_update',
            'user_id'      => $userId,
            'formation_id' => $formationId,
            'old_values'   => $oldValues,
            'new_values'   => $newValues,
            'timestamp'    => now()->toISOString(),
        ]);
    }

    /**
     * Enregistrer la suppression d'une formation.
     */
    public static function suppressionFormation(int $formationId, int $userId): void
    {
        ActivityLog::create([
            'event'        => 'course_deletion',
            'user_id'      => $userId,
            'formation_id' => $formationId,
            'timestamp'    => now()->toISOString(),
        ]);
    }
}
