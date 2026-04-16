<?php

namespace App\Http\Controllers;

use App\Models\Formation;
use App\Models\Inscription;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

/**
 * Contrôleur de gestion des modules.
 */
class ModuleController extends Controller
{
    /**
     * Lister les modules d'une formation (accès public).
     * Route : GET /formations/{id}/modules
     */
    public function index($formationId): JsonResponse
    {
        $modules = Module::where('formation_id', $formationId)
            ->orderBy('ordre')
            ->get();

        return response()->json($modules);
    }

    /**
     * Créer un module — réservé au formateur propriétaire de la formation.
     * Route : POST /formations/{id}/modules
     */
    public function store(Request $request, $formationId): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json([
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            // Seul un formateur peut créer un module
            if ($user->role !== 'formateur') {
                return response()->json([
                    'message' => 'Seul un formateur peut créer un module'
                ], 403);
            }

            // Vérification que la formation existe et appartient au formateur
            $formation = Formation::find($formationId);

            if (! $formation) {
                return response()->json([
                    'message' => 'Formation introuvable'
                ], 404);
            }

            if ($formation->formateur_id !== $user->id) {
                return response()->json([
                    'message' => 'Vous ne pouvez pas modifier une formation qui ne vous appartient pas'
                ], 403);
            }

            $data = $request->validate([
                'titre'   => 'required|string|max:255',
                'contenu' => 'required|string',
                'ordre'   => 'required|integer|min:1',
            ]);

            $module = Module::create([
                'titre'        => $data['titre'],
                'contenu'      => $data['contenu'],
                'ordre'        => $data['ordre'],
                'formation_id' => $formationId,
            ]);

            return response()->json([
                'message' => 'Module créé avec succès',
                'module'  => $module
            ], 201);

        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Token invalide ou absent'
            ], 401);
        }
    }

    /**
     * Mettre à jour un module — réservé au formateur propriétaire.
     * Route : PUT /modules/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json([
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            if ($user->role !== 'formateur') {
                return response()->json([
                    'message' => 'Seul un formateur peut modifier un module'
                ], 403);
            }

            $module = Module::find($id);

            if (! $module) {
                return response()->json([
                    'message' => 'Module introuvable'
                ], 404);
            }

            // Vérification que la formation appartient au formateur connecté
            $formation = Formation::find($module->formation_id);

            if (! $formation || $formation->formateur_id !== $user->id) {
                return response()->json([
                    'message' => 'Action non autorisée'
                ], 403);
            }

            $data = $request->validate([
                'titre'   => 'required|string|max:255',
                'contenu' => 'required|string',
                'ordre'   => 'required|integer|min:1',
            ]);

            $module->update([
                'titre'   => $data['titre'],
                'contenu' => $data['contenu'],
                'ordre'   => $data['ordre'],
            ]);

            return response()->json([
                'message' => 'Module mis à jour avec succès',
                'module'  => $module
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Token invalide ou absent'
            ], 401);
        }
    }

    /**
     * Supprimer un module — réservé au formateur propriétaire.
     * Route : DELETE /modules/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json([
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            if ($user->role !== 'formateur') {
                return response()->json([
                    'message' => 'Seul un formateur peut supprimer un module'
                ], 403);
            }

            $module = Module::find($id);

            if (! $module) {
                return response()->json([
                    'message' => 'Module introuvable'
                ], 404);
            }

            // Vérification que la formation appartient au formateur connecté
            $formation = Formation::find($module->formation_id);

            if (! $formation || $formation->formateur_id !== $user->id) {
                return response()->json([
                    'message' => 'Action non autorisée'
                ], 403);
            }

            $module->delete();

            return response()->json([
                'message' => 'Module supprimé avec succès'
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Token invalide ou absent'
            ], 401);
        }
    }

    /**
     * Marquer un module comme terminé — réservé à l'apprenant inscrit.
     * Calcule automatiquement la progression en pourcentage.
     * Route : POST /modules/{id}/terminer
     */
    public function terminer($id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json([
                    'message' => 'Utilisateur non trouvé'
                ], 404);
            }

            // Seul un apprenant peut terminer un module
            if ($user->role !== 'apprenant') {
                return response()->json([
                    'message' => 'Seul un apprenant peut terminer un module'
                ], 403);
            }

            $module = Module::find($id);

            if (! $module) {
                return response()->json([
                    'message' => 'Module introuvable'
                ], 404);
            }

            // Vérification que l'apprenant est bien inscrit à la formation
            $inscription = Inscription::where('utilisateur_id', $user->id)
                ->where('formation_id', $module->formation_id)
                ->first();

            if (! $inscription) {
                return response()->json([
                    'message' => "Vous n'êtes pas inscrit à cette formation"
                ], 403);
            }

            // Vérification anti-doublon : module déjà terminé ?
            $dejaTermine = $user->modulesTermines()
                ->where('modules.id', $module->id)
                ->exists();

            if ($dejaTermine) {
                return response()->json([
                    'message'     => 'Ce module est déjà terminé',
                    'progression' => $inscription->progression
                ]);
            }

            // Enregistrement du module terminé dans la table pivot
            $user->modulesTermines()->attach($module->id, [
                'termine' => true
            ]);

            // Calcul de la progression en pourcentage
            $totalModules    = Module::where('formation_id', $module->formation_id)->count();
            $modulesTermines = $user->modulesTermines()
                ->where('formation_id', $module->formation_id)
                ->count();

            $progression = $totalModules > 0
                ? round(($modulesTermines / $totalModules) * 100)
                : 0;

            $inscription->progression = $progression;
            $inscription->save();

            return response()->json([
                'message'     => 'Module terminé avec succès',
                'progression' => $inscription->progression
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Token invalide ou absent'
            ], 401);
        }
    }
}
