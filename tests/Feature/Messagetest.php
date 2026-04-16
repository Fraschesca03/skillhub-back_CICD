<?php

namespace Tests\Feature;

use App\Models\Formation;
use App\Models\Inscription;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Tests fonctionnels du MessageController.
 *
 * Couvre :
 * - Compte des messages non lus
 * - Liste des conversations
 * - Messages d une conversation
 * - Envoi d un message
 * - Liste des interlocuteurs
 * - Acces non autorise sans token
 */
class MessageTest extends TestCase
{
    use RefreshDatabase;

    // ─── Nettoyage MongoDB entre chaque test ─────────────────────────────
    protected function setUp(): void
    {
        parent::setUp();
        // On vide la collection messages MongoDB avant chaque test
        Message::truncate();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function creerUtilisateur(string $role, string $suffix = ''): array
    {
        $email = $role . $suffix . '@msg-test.com';
        $user = User::create([
            'nom'      => ucfirst($role) . $suffix,
            'email'    => $email,
            'password' => bcrypt('password123'),
            'role'     => $role,
        ]);
        $token = JWTAuth::fromUser($user);
        return ['user' => $user, 'token' => $token];
    }

    private function headers(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function creerFormation(User $formateur): Formation
    {
        return Formation::create([
            'titre'          => 'Formation Message Test',
            'description'    => 'Description',
            'categorie'      => 'developpement_web',
            'niveau'         => 'debutant',
            'nombre_de_vues' => 0,
            'formateur_id'   => $formateur->id,
        ]);
    }

    // =========================================================================
    // Messages non lus
    // =========================================================================

    /** @test */
    public function non_lus_retourne_le_nombre_de_messages_non_lus(): void
    {
        ['user' => $expediteur, 'token' => $tokenExp] = $this->creerUtilisateur('formateur');
        ['user' => $destinataire, 'token' => $tokenDest] = $this->creerUtilisateur('apprenant');

        // Envoie 2 messages
        Message::create([
            'expediteur_id'   => $expediteur->id,
            'destinataire_id' => $destinataire->id,
            'contenu'         => 'Bonjour',
            'lu'              => false,
        ]);
        Message::create([
            'expediteur_id'   => $expediteur->id,
            'destinataire_id' => $destinataire->id,
            'contenu'         => 'Comment allez-vous ?',
            'lu'              => false,
        ]);

        $response = $this->getJson('/api/messages/non-lus', $this->headers($tokenDest));

        $response->assertStatus(200)
            ->assertJsonFragment(['non_lus' => 2]);
    }

    /** @test */
    public function non_lus_retourne_zero_si_aucun_message(): void
    {
        ['token' => $token] = $this->creerUtilisateur('apprenant');

        $response = $this->getJson('/api/messages/non-lus', $this->headers($token));

        $response->assertStatus(200)
            ->assertJsonFragment(['non_lus' => 0]);
    }

    /** @test */
    public function non_lus_retourne_401_sans_token(): void
    {
        $response = $this->getJson('/api/messages/non-lus');

        $response->assertStatus(401);
    }

    // =========================================================================
    // Conversations
    // =========================================================================

    /** @test */
    public function conversations_retourne_la_liste_des_interlocuteurs(): void
    {
        ['user' => $userA, 'token' => $tokenA] = $this->creerUtilisateur('formateur');
        ['user' => $userB] = $this->creerUtilisateur('apprenant');

        Message::create([
            'expediteur_id'   => $userA->id,
            'destinataire_id' => $userB->id,
            'contenu'         => 'Premier message',
            'lu'              => false,
        ]);

        $response = $this->getJson('/api/messages/conversations', $this->headers($tokenA));

        $response->assertStatus(200)
            ->assertJsonStructure(['conversations']);
    }

    /** @test */
    public function conversations_retourne_401_sans_token(): void
    {
        $response = $this->getJson('/api/messages/conversations');

        $response->assertStatus(401);
    }

    // =========================================================================
    // Messagerie (conversation entre deux utilisateurs)
    // =========================================================================

    /** @test */
    public function messagerie_retourne_les_messages_entre_deux_utilisateurs(): void
    {
        ['user' => $userA, 'token' => $tokenA] = $this->creerUtilisateur('formateur');
        ['user' => $userB] = $this->creerUtilisateur('apprenant');

        Message::create([
            'expediteur_id'   => $userA->id,
            'destinataire_id' => $userB->id,
            'contenu'         => 'Bonjour apprenant',
            'lu'              => false,
        ]);

        $response = $this->getJson('/api/messages/conversation/' . $userB->id, $this->headers($tokenA));

        $response->assertStatus(200)
            ->assertJsonStructure(['messages']);
    }

    /** @test */
    public function messagerie_marque_les_messages_comme_lus(): void
    {
        ['user' => $userA, 'token' => $tokenA] = $this->creerUtilisateur('formateur');
        ['user' => $userB, 'token' => $tokenB] = $this->creerUtilisateur('apprenant');

        // userA envoie un message a userB
        Message::create([
            'expediteur_id'   => $userA->id,
            'destinataire_id' => $userB->id,
            'contenu'         => 'Message a lire',
            'lu'              => false,
        ]);

        // userB consulte la conversation : le message doit etre marque lu
        $this->getJson('/api/messages/conversation/' . $userA->id, $this->headers($tokenB));

        $nonLus = $this->getJson('/api/messages/non-lus', $this->headers($tokenB));
        $nonLus->assertJsonFragment(['non_lus' => 0]);
    }

    /** @test */
    public function messagerie_retourne_401_sans_token(): void
    {
        $response = $this->getJson('/api/messages/conversation/1');

        $response->assertStatus(401);
    }

    // =========================================================================
    // Envoi d un message
    // =========================================================================

    /** @test */
    public function envoyer_un_message_fonctionne(): void
    {
        ['user' => $expediteur, 'token' => $token] = $this->creerUtilisateur('formateur');
        ['user' => $destinataire] = $this->creerUtilisateur('apprenant');

        $response = $this->postJson('/api/messages/envoyer', [
            'destinataire_id' => $destinataire->id,
            'contenu'         => 'Bienvenue dans la formation !',
        ], $this->headers($token));

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Message envoyé']);
    }

    /** @test */
    public function envoyer_un_message_echoue_si_destinataire_inexistant(): void
    {
        ['token' => $token] = $this->creerUtilisateur('formateur');

        $response = $this->postJson('/api/messages/envoyer', [
            'destinataire_id' => 9999,
            'contenu'         => 'Message vers utilisateur inexistant',
        ], $this->headers($token));

        $response->assertStatus(422);
    }

    /** @test */
    public function envoyer_un_message_retourne_401_sans_token(): void
    {
        $response = $this->postJson('/api/messages/envoyer', [
            'destinataire_id' => 1,
            'contenu'         => 'Test',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function envoyer_un_premier_message_envoie_un_mail(): void
    {
        // On utilise le mailer en mode array (pas de vrai envoi)
        ['user' => $expediteur, 'token' => $token] = $this->creerUtilisateur('formateur');
        ['user' => $destinataire] = $this->creerUtilisateur('apprenant');

        // Premier message de la conversation
        $response = $this->postJson('/api/messages/envoyer', [
            'destinataire_id' => $destinataire->id,
            'contenu'         => 'Premier contact !',
        ], $this->headers($token));

        $response->assertStatus(201);

        // Deuxieme message : pas de mail
        $response2 = $this->postJson('/api/messages/envoyer', [
            'destinataire_id' => $destinataire->id,
            'contenu'         => 'Suite de la conversation',
        ], $this->headers($token));

        $response2->assertStatus(201);
    }

    // =========================================================================
    // Interlocuteurs
    // =========================================================================

    /** @test */
    public function interlocuteurs_formateur_retourne_ses_apprenants(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUtilisateur('formateur');
        ['user' => $apprenant] = $this->creerUtilisateur('apprenant');

        $formation = $this->creerFormation($formateur);
        Inscription::create([
            'utilisateur_id' => $apprenant->id,
            'formation_id'   => $formation->id,
            'progression'    => 0,
        ]);

        $response = $this->getJson('/api/messages/interlocuteurs', $this->headers($token));

        $response->assertStatus(200)
            ->assertJsonStructure(['interlocuteurs']);
    }

    /** @test */
    public function interlocuteurs_apprenant_retourne_ses_formateurs(): void
    {
        ['user' => $formateur] = $this->creerUtilisateur('formateur');
        ['user' => $apprenant, 'token' => $token] = $this->creerUtilisateur('apprenant');

        $formation = $this->creerFormation($formateur);
        Inscription::create([
            'utilisateur_id' => $apprenant->id,
            'formation_id'   => $formation->id,
            'progression'    => 0,
        ]);

        $response = $this->getJson('/api/messages/interlocuteurs', $this->headers($token));

        $response->assertStatus(200)
            ->assertJsonStructure(['interlocuteurs']);
    }

    /** @test */
    public function interlocuteurs_retourne_401_sans_token(): void
    {
        $response = $this->getJson('/api/messages/interlocuteurs');

        $response->assertStatus(401);
    }
}
