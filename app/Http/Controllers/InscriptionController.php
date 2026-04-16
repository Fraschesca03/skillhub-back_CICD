<?php

namespace App\Http\Controllers;

use App\Models\Formation;
use App\Models\Inscription;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

/**
 * Contrôleur de gestion des inscriptions.
 */
class InscriptionController extends Controller
{
    /**
     * Inscrire un apprenant à une formation.
     * Route : POST /formations/{id}/inscription
     */
    public function store($formationId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json(['message' => 'Utilisateur non trouvé'], 404);
            }

            if ($user->role !== 'apprenant') {
                return response()->json([
                    'message' => "Seul un apprenant peut s'inscrire à une formation"
                ], 403);
            }

            $formation = Formation::find($formationId);

            if (! $formation) {
                return response()->json(['message' => 'Formation introuvable'], 404);
            }

            $dejaInscrit = Inscription::where('utilisateur_id', $user->id)
                ->where('formation_id', $formation->id)
                ->first();

            if ($dejaInscrit) {
                return response()->json([
                    'message' => 'Vous êtes déjà inscrit à cette formation'
                ], 409);
            }

            $inscription = Inscription::create([
                'utilisateur_id' => $user->id,
                'formation_id'   => $formation->id,
                'progression'    => 0,
            ]);

            // Log MongoDB — inscription formation
            ActivityLogService::inscriptionFormation($formation->id, $user->id);

            return response()->json([
                'message'     => 'Inscription réussie',
                'inscription' => $inscription
            ], 201);

        } catch (JWTException $e) {
            return response()->json(['message' => 'Token invalide ou absent'], 401);
        }
    }

    /**
     * Désinscrire un apprenant d'une formation.
     * Route : DELETE /formations/{id}/inscription
     */
    public function destroy($formationId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json(['message' => 'Utilisateur non trouvé'], 404);
            }

            if ($user->role !== 'apprenant') {
                return response()->json([
                    'message' => 'Seul un apprenant peut se désinscrire'
                ], 403);
            }

            $inscription = Inscription::where('utilisateur_id', $user->id)
                ->where('formation_id', $formationId)
                ->first();

            if (! $inscription) {
                return response()->json(['message' => 'Inscription introuvable'], 404);
            }

            $inscription->delete();

            return response()->json(['message' => 'Désinscription réussie']);

        } catch (JWTException $e) {
            return response()->json(['message' => 'Token invalide ou absent'], 401);
        }
    }

    /**
     * Liste des formations suivies par l'apprenant connecté.
     * Route : GET /apprenant/formations
     */
    public function mesFormations(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json(['message' => 'Utilisateur non trouvé'], 404);
            }

            if ($user->role !== 'apprenant') {
                return response()->json([
                    'message' => 'Seul un apprenant peut voir ses formations'
                ], 403);
            }

            $inscriptions = Inscription::with('formation.formateur:id,nom,email')
                ->where('utilisateur_id', $user->id)
                ->get();

            return response()->json($inscriptions);

        } catch (JWTException $e) {
            return response()->json(['message' => 'Token invalide ou absent'], 401);
        }
    }
}