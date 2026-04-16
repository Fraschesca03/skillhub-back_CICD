<?php

namespace App\Http\Controllers;

use App\Mail\NouveauMessageMail;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

/**
 * Contrôleur de messagerie entre utilisateurs.
 */
class MessageController extends Controller
{
    /**
     * Récupère le nombre de messages non lus de l'utilisateur connecté.
     */
    public function nonLus(): JsonResponse
    {
        $user = $this->utilisateurConnecte();
        if (! $user) return $this->reponseNonAutorise();

        $count = Message::where('destinataire_id', $user->id)
                        ->where('lu', false)
                        ->count();

        return response()->json(['non_lus' => $count]);
    }

    /**
     * Récupère la liste des conversations de l'utilisateur connecté.
     * Chaque conversation = un interlocuteur unique.
     */
    public function conversations(): JsonResponse
    {
        $user = $this->utilisateurConnecte();
        if (! $user) return $this->reponseNonAutorise();

        // Récupère tous les messages impliquant l'utilisateur
        $messages = Message::where('expediteur_id', $user->id)
                           ->orWhere('destinataire_id', $user->id)
                           ->with(['expediteur', 'destinataire'])
                           ->orderByDesc('created_at')
                           ->get();

        // Regroupe par interlocuteur (dernier message + nb non lus)
        $conversations = [];

        foreach ($messages as $message) {
            // Détermine l'interlocuteur
            $interlocuteur = $message->expediteur_id === $user->id
                           ? $message->destinataire
                           : $message->expediteur;

            $id = $interlocuteur->id;

            if (! isset($conversations[$id])) {
                $conversations[$id] = [
                    'interlocuteur_id'  => $interlocuteur->id,
                    'interlocuteur_nom' => $interlocuteur->nom,
                    'dernier_message'   => $message->contenu,
                    'date'              => $message->created_at,
                    'non_lus'           => 0,
                ];
            }

            // Compte les messages non lus de cet interlocuteur
            if ($message->destinataire_id === $user->id && ! $message->lu) {
                $conversations[$id]['non_lus']++;
            }
        }

        return response()->json(['conversations' => array_values($conversations)]);
    }

    /**
     * Récupère tous les messages d'une conversation avec un interlocuteur.
     * Marque automatiquement les messages reçus comme lus.
     */
    public function messagerie(int $interlocuteurId): JsonResponse
    {
        $user = $this->utilisateurConnecte();
        if (! $user) return $this->reponseNonAutorise();

        // Récupère les messages entre les deux utilisateurs
        $messages = Message::where(function ($q) use ($user, $interlocuteurId) {
                               $q->where('expediteur_id', $user->id)
                                 ->where('destinataire_id', $interlocuteurId);
                           })
                           ->orWhere(function ($q) use ($user, $interlocuteurId) {
                               $q->where('expediteur_id', $interlocuteurId)
                                 ->where('destinataire_id', $user->id);
                           })
                           ->with(['expediteur:id,nom', 'destinataire:id,nom'])
                           ->orderBy('created_at', 'asc')
                           ->get();

        // Marque les messages reçus non lus comme lus
        Message::where('expediteur_id', $interlocuteurId)
               ->where('destinataire_id', $user->id)
               ->where('lu', false)
               ->update(['lu' => true]);

        return response()->json(['messages' => $messages]);
    }

    /**
     * Envoie un nouveau message à un utilisateur.
     * Envoie un mail si c'est le tout premier message de la conversation.
     */
    public function envoyer(Request $request): JsonResponse
    {
        $user = $this->utilisateurConnecte();
        if (! $user) return $this->reponseNonAutorise();

        $request->validate([
            'destinataire_id' => 'required|integer|exists:users,id',
            'contenu'         => 'required|string|max:2000',
        ]);

        $destinataireId = $request->input('destinataire_id');
        $contenu        = $request->input('contenu');

        // Vérifie si c'est le premier message de cette conversation
        $estPremierMessage = ! Message::where(function ($q) use ($user, $destinataireId) {
            $q->where('expediteur_id', $user->id)
              ->where('destinataire_id', $destinataireId);
        })->orWhere(function ($q) use ($user, $destinataireId) {
            $q->where('expediteur_id', $destinataireId)
              ->where('destinataire_id', $user->id);
        })->exists();

        // Crée et sauvegarde le message
        $message = Message::create([
            'expediteur_id'   => $user->id,
            'destinataire_id' => $destinataireId,
            'contenu'         => $contenu,
            'lu'              => false,
        ]);

        $message->load('expediteur:id,nom', 'destinataire:id,nom');

        // Envoie un mail uniquement si c'est le premier message
        if ($estPremierMessage) {
            $destinataire = User::find($destinataireId);
            Mail::to($destinataire->email)
                ->send(new NouveauMessageMail($user->nom, $destinataire->nom, $contenu));
        }

        return response()->json([
            'message'  => 'Message envoyé',
            'data'     => $message,
        ], 201);
    }

    /**
     * Retourne la liste des utilisateurs avec qui on peut échanger.
     * Formateur → tous les apprenants inscrits à ses formations.
     * Apprenant → tous ses formateurs.
     */
    public function interlocuteurs(): JsonResponse
    {
        $user = $this->utilisateurConnecte();
        if (! $user) return $this->reponseNonAutorise();

        if ($user->role === 'formateur') {
            // Les apprenants inscrits aux formations du formateur
            $utilisateurs = User::whereHas('inscriptions', function ($q) use ($user) {
                $q->whereHas('formation', function ($q2) use ($user) {
                    $q2->where('formateur_id', $user->id);
                });
            })->select('id', 'nom', 'email', 'role')->get();
        } else {
            // Les formateurs des formations auxquelles l'apprenant est inscrit
            $utilisateurs = User::where('role', 'formateur')
                                ->whereHas('formations', function ($q) use ($user) {
                                    $q->whereHas('inscriptions', function ($q2) use ($user) {
                                        $q2->where('utilisateur_id', $user->id);
                                    });
                                })->select('id', 'nom', 'email', 'role')->get();
        }

        return response()->json(['interlocuteurs' => $utilisateurs]);
    }

    // ─── Helpers privés ──────────────────────────────────────────

    /**
     * Récupère l'utilisateur authentifié via JWT.
     */
    private function utilisateurConnecte()
    {
        try {
            return JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {
            return null;
        }
    }

    /**
     * Réponse standard 401 non autorisé.
     */
    private function reponseNonAutorise(): JsonResponse
    {
        return response()->json(['message' => 'Non autorisé'], 401);
    }
}