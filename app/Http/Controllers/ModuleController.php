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
 * Controleur de gestion des modules.
 */
class ModuleController extends Controller
{
    private const MSG_TOKEN_INVALIDE  = 'Token invalide ou absent';
    private const MSG_USER_NON_TROUVE = 'Utilisateur non trouvé';
    private const MSG_MODULE_INTRO    = 'Module introuvable';

    /**
     * Lister les modules d une formation (acces public).
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
     * Creer un module - reserve au formateur proprietaire.
     * Route : POST /formations/{id}/modules
     */
    public function store(Request $request, $formationId): JsonResponse
    {
        $reponse = response()->json(['message' => self::MSG_TOKEN_INVALIDE], 401);

        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                $reponse = response()->json(['message' => self::MSG_USER_NON_TROUVE], 404);
            } elseif ($user->role !== 'formateur') {
                $reponse = response()->json(['message' => 'Seul un formateur peut créer un module'], 403);
            } else {
                $formation = Formation::find($formationId);

                if (! $formation) {
                    $reponse = response()->json(['message' => 'Formation introuvable'], 404);
                } elseif ($formation->formateur_id !== $user->id) {
                    $reponse = response()->json([
                        'message' => 'Vous ne pouvez pas modifier une formation qui ne vous appartient pas',
                    ], 403);
                } else {
                    $data   = $request->validate([
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

                    $reponse = response()->json([
                        'message' => 'Module créé avec succès',
                        'module'  => $module,
                    ], 201);
                }
            }
        } catch (JWTException $e) {
            // reponse 401 deja definie
        }

        return $reponse;
    }

    /**
     * Mettre a jour un module - reserve au formateur proprietaire.
     * Route : PUT /modules/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $reponse = response()->json(['message' => self::MSG_TOKEN_INVALIDE], 401);

        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                $reponse = response()->json(['message' => self::MSG_USER_NON_TROUVE], 404);
            } elseif ($user->role !== 'formateur') {
                $reponse = response()->json(['message' => 'Seul un formateur peut modifier un module'], 403);
            } else {
                $module = Module::find($id);

                if (! $module) {
                    $reponse = response()->json(['message' => self::MSG_MODULE_INTRO], 404);
                } else {
                    $formation = Formation::find($module->formation_id);

                    if (! $formation || $formation->formateur_id !== $user->id) {
                        $reponse = response()->json(['message' => 'Action non autorisée'], 403);
                    } else {
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

                        $reponse = response()->json([
                            'message' => 'Module mis à jour avec succès',
                            'module'  => $module,
                        ]);
                    }
                }
            }
        } catch (JWTException $e) {
            // reponse 401 deja definie
        }

        return $reponse;
    }

    /**
     * Supprimer un module - reserve au formateur proprietaire.
     * Route : DELETE /modules/{id}
     */
    public function destroy($id): JsonResponse
    {
        $reponse = response()->json(['message' => self::MSG_TOKEN_INVALIDE], 401);

        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                $reponse = response()->json(['message' => self::MSG_USER_NON_TROUVE], 404);
            } elseif ($user->role !== 'formateur') {
                $reponse = response()->json(['message' => 'Seul un formateur peut supprimer un module'], 403);
            } else {
                $module = Module::find($id);

                if (! $module) {
                    $reponse = response()->json(['message' => self::MSG_MODULE_INTRO], 404);
                } else {
                    $formation = Formation::find($module->formation_id);

                    if (! $formation || $formation->formateur_id !== $user->id) {
                        $reponse = response()->json(['message' => 'Action non autorisée'], 403);
                    } else {
                        $module->delete();
                        $reponse = response()->json(['message' => 'Module supprimé avec succès']);
                    }
                }
            }
        } catch (JWTException $e) {
            // reponse 401 deja definie
        }

        return $reponse;
    }

    /**
     * Marquer un module comme termine - reserve a l apprenant inscrit.
     * Route : POST /modules/{id}/terminer
     */
    public function terminer($id): JsonResponse
    {
        $reponse = response()->json(['message' => self::MSG_TOKEN_INVALIDE], 401);

        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                $reponse = response()->json(['message' => self::MSG_USER_NON_TROUVE], 404);
            } elseif ($user->role !== 'apprenant') {
                $reponse = response()->json(['message' => 'Seul un apprenant peut terminer un module'], 403);
            } else {
                $module = Module::find($id);

                if (! $module) {
                    $reponse = response()->json(['message' => self::MSG_MODULE_INTRO], 404);
                } else {
                    $inscription = Inscription::where('utilisateur_id', $user->id)
                        ->where('formation_id', $module->formation_id)
                        ->first();

                    if (! $inscription) {
                        $reponse = response()->json([
                            'message' => "Vous n'êtes pas inscrit à cette formation",
                        ], 403);
                    } else {
                        $dejaTermine = $user->modulesTermines()
                            ->where('modules.id', $module->id)
                            ->exists();

                        if ($dejaTermine) {
                            $reponse = response()->json([
                                'message'     => 'Ce module est déjà terminé',
                                'progression' => $inscription->progression,
                            ]);
                        } else {
                            $user->modulesTermines()->attach($module->id, ['termine' => true]);

                            $totalModules    = Module::where('formation_id', $module->formation_id)->count();
                            $modulesTermines = $user->modulesTermines()
                                ->where('formation_id', $module->formation_id)
                                ->count();

                            $progression = $totalModules > 0
                                ? round(($modulesTermines / $totalModules) * 100)
                                : 0;

                            $inscription->progression = $progression;
                            $inscription->save();

                            $reponse = response()->json([
                                'message'     => 'Module terminé avec succès',
                                'progression' => $inscription->progression,
                            ]);
                        }
                    }
                }
            }
        } catch (JWTException $e) {
            // reponse 401 deja definie
        }

        return $reponse;
    }
}
