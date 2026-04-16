<?php

namespace App\Http\Controllers;

use App\Models\Formation;
use App\Models\FormationVue;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

/**
 * Contrôleur de gestion des formations.
 */
class FormationController extends Controller
{
    /**
     * Liste des formations avec filtres optionnels.
     * Route : GET /formations
     */
    public function index(Request $request): JsonResponse
    {
        $query = Formation::with('formateur:id,nom,email')
            ->withCount('inscriptions');

        if ($request->filled('recherche')) {
            $motCle = $request->input('recherche');
            $query->where(function ($q) use ($motCle) {
                $q->where('titre', 'like', '%' . $motCle . '%')
                  ->orWhere('description', 'like', '%' . $motCle . '%');
            });
        }

        if ($request->filled('categorie')) {
            $query->where('categorie', $request->input('categorie'));
        }

        if ($request->filled('niveau')) {
            $query->where('niveau', $request->input('niveau'));
        }

        return response()->json($query->get());
    }

    /**
     * Afficher une formation et incrémenter ses vues de façon unique.
     * Route : GET /formations/{id}
     */
    public function show(Request $request, $id): JsonResponse
    {
        $formation = Formation::with('formateur:id,nom,email')
            ->withCount('inscriptions')
            ->find($id);

        if (! $formation) {
            return response()->json(['message' => 'Formation introuvable'], 404);
        }

        $utilisateurId = null;
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if ($user) {
                $utilisateurId = $user->id;
            }
        } catch (JWTException $e) {
            // Utilisateur non connecté
        }

        if ($utilisateurId) {
            $dejaVue = FormationVue::where('formation_id', $formation->id)
                ->where('utilisateur_id', $utilisateurId)
                ->exists();

            if (! $dejaVue) {
                FormationVue::create([
                    'formation_id'   => $formation->id,
                    'utilisateur_id' => $utilisateurId,
                    'ip'             => $request->ip(),
                ]);
                $formation->increment('nombre_de_vues');
            }
        } else {
            $dejaVueIp = FormationVue::where('formation_id', $formation->id)
                ->whereNull('utilisateur_id')
                ->where('ip', $request->ip())
                ->exists();

            if (! $dejaVueIp) {
                FormationVue::create([
                    'formation_id'   => $formation->id,
                    'utilisateur_id' => null,
                    'ip'             => $request->ip(),
                ]);
                $formation->increment('nombre_de_vues');
            }
        }

        // Log MongoDB — consultation formation
        ActivityLogService::consultationFormation($formation->id, $utilisateurId);

        return response()->json($formation->fresh(['formateur:id,nom,email']));
    }

    /**
     * Créer une nouvelle formation.
     * Route : POST /formations
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json(['message' => 'Utilisateur non trouvé'], 404);
            }

            if ($user->role !== 'formateur') {
                return response()->json(['message' => 'Seul un formateur peut créer une formation'], 403);
            }

            $request->validate([
    'titre'        => 'required|string|max:255',
    'description'  => 'required|string',
    'categorie'    => 'required|in:developpement_web,data,design,marketing,devops,autre',
    'niveau'       => 'required|in:debutant,intermediaire,avance',
    'prix'         => 'nullable|numeric|min:0',
    'duree_heures' => 'nullable|integer|min:0',
]);

$formation = Formation::create([
    'titre'          => $request->titre,
    'description'    => $request->description,
    'categorie'      => $request->categorie,
    'niveau'         => $request->niveau,
    'prix'           => $request->prix ?? 0,
    'duree_heures'   => $request->duree_heures ?? 0,
    'nombre_de_vues' => 0,
    'formateur_id'   => $user->id,
]);

            // Log MongoDB — création formation
            ActivityLogService::creationFormation($formation->id, $user->id);

            return response()->json([
                'message'   => 'Formation créée avec succès',
                'formation' => $formation
            ], 201);

        } catch (JWTException $e) {
            return response()->json(['message' => 'Token invalide ou absent'], 401);
        }
    }

    /**
     * Mettre à jour une formation.
     * Route : PUT /formations/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json(['message' => 'Utilisateur non trouvé'], 404);
            }

            $formation = Formation::find($id);

            if (! $formation) {
                return response()->json(['message' => 'Formation introuvable'], 404);
            }

            if ($user->role !== 'formateur' || $formation->formateur_id !== $user->id) {
                return response()->json(['message' => 'Action non autorisée'], 403);
            }

            $request->validate([
    'titre'        => 'required|string|max:255',
    'description'  => 'required|string',
    'categorie'    => 'required|in:developpement_web,data,design,marketing,devops,autre',
    'niveau'       => 'required|in:debutant,intermediaire,avance',
    'prix'         => 'nullable|numeric|min:0',
    'duree_heures' => 'nullable|integer|min:0',
]);

$formation->update([
    'titre'        => $request->titre,
    'description'  => $request->description,
    'categorie'    => $request->categorie,
    'niveau'       => $request->niveau,
    'prix'         => $request->prix ?? $formation->prix,
    'duree_heures' => $request->duree_heures ?? $formation->duree_heures,
]);

            // Sauvegarde des anciennes valeurs pour le log
            $oldValues = [
                'titre'       => $formation->titre,
                'description' => $formation->description,
                'categorie'   => $formation->categorie,
                'niveau'      => $formation->niveau,
            ];

           

            // Log MongoDB — modification formation avec avant/après
            ActivityLogService::modificationFormation(
                $formation->id,
                $user->id,
                $oldValues,
                [
                    'titre'       => $request->titre,
                    'description' => $request->description,
                    'categorie'   => $request->categorie,
                    'niveau'      => $request->niveau,
                ]
            );

            return response()->json([
                'message'   => 'Formation mise à jour avec succès',
                'formation' => $formation
            ]);

        } catch (JWTException $e) {
            return response()->json(['message' => 'Token invalide ou absent'], 401);
        }
    }

    /**
     * Supprimer une formation.
     * Route : DELETE /formations/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json(['message' => 'Utilisateur non trouvé'], 404);
            }

            $formation = Formation::find($id);

            if (! $formation) {
                return response()->json(['message' => 'Formation introuvable'], 404);
            }

            if ($user->role !== 'formateur' || $formation->formateur_id !== $user->id) {
                return response()->json(['message' => 'Action non autorisée'], 403);
            }

            // Log MongoDB — suppression formation
            ActivityLogService::suppressionFormation($formation->id, $user->id);

            $formation->delete();

            return response()->json(['message' => 'Formation supprimée avec succès']);

        } catch (JWTException $e) {
            return response()->json(['message' => 'Token invalide ou absent'], 401);
        }
    }
}