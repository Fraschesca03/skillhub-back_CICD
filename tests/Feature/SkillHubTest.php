<?php

namespace Tests\Feature;

use App\Models\Formation;
use App\Models\Inscription;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Tests fonctionnels de l'API SkillHub.
 */
class SkillHubTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers prives
    // -------------------------------------------------------------------------

    private function creerUtilisateur(string $role): array
    {
        $user = User::create([
            'nom'      => 'Test ' . ucfirst($role),
            'email'    => $role . '@test.com',
            'password' => bcrypt('password123'),
            'role'     => $role,
        ]);

        $token = JWTAuth::fromUser($user);

        return ['user' => $user, 'token' => $token];
    }

    private function creerFormation(User $formateur): Formation
    {
        return Formation::create([
            'titre'          => 'Formation Test',
            'description'    => 'Description de test',
            'categorie'      => 'developpement_web',
            'niveau'         => 'debutant',
            'nombre_de_vues' => 0,
            'formateur_id'   => $formateur->id,
        ]);
    }

    private function creerModule(Formation $formation, int $ordre = 1): Module
    {
        return Module::create([
            'titre'        => 'Module ' . $ordre,
            'contenu'      => 'Contenu du module ' . $ordre,
            'ordre'        => $ordre,
            'formation_id' => $formation->id,
        ]);
    }

    private function headers(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    // =========================================================================
    // SECTION 1 - Authentification
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_utilisateur_peut_sinscrire(): void
    {
        $response = $this->postJson('/api/register', [
            'nom'      => 'Alice',
            'email'    => 'alice@test.com',
            'password' => 'password123',
            'role'     => 'apprenant',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'token', 'user']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function linscription_echoue_si_email_deja_utilise(): void
    {
        $this->creerUtilisateur('apprenant');

        $response = $this->postJson('/api/register', [
            'nom'      => 'Copie',
            'email'    => 'apprenant@test.com',
            'password' => 'password123',
            'role'     => 'apprenant',
        ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_utilisateur_peut_se_connecter(): void
    {
        $this->creerUtilisateur('formateur');

        $response = $this->postJson('/api/login', [
            'email'    => 'formateur@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'token', 'user']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function la_connexion_echoue_avec_mauvais_mot_de_passe(): void
    {
        $this->creerUtilisateur('formateur');

        $response = $this->postJson('/api/login', [
            'email'    => 'formateur@test.com',
            'password' => 'mauvais_mdp',
        ]);

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_utilisateur_connecte_peut_voir_son_profil(): void
    {
        ['token' => $token] = $this->creerUtilisateur('apprenant');

        $response = $this->getJson('/api/profile', $this->headers($token));

        $response->assertStatus(200)
            ->assertJsonStructure(['user']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function le_profil_retourne_401_sans_token(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_utilisateur_peut_se_deconnecter(): void
    {
        ['token' => $token] = $this->creerUtilisateur('apprenant');

        $response = $this->postJson('/api/logout', [], $this->headers($token));

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Déconnexion réussie']);
    }

    // =========================================================================
    // SECTION 2 - Formations
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_formateur_peut_creer_une_formation(): void
    {
        ['token' => $token] = $this->creerUtilisateur('formateur');

        $response = $this->postJson('/api/formations', [
            'titre'       => 'Laravel avancé',
            'description' => 'Apprendre Laravel en profondeur',
            'categorie'   => 'developpement_web',
            'niveau'      => 'avance',
        ], $this->headers($token));

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Formation créée avec succès']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_apprenant_ne_peut_pas_creer_de_formation(): void
    {
        ['token' => $token] = $this->creerUtilisateur('apprenant');

        $response = $this->postJson('/api/formations', [
            'titre'       => 'Formation interdite',
            'description' => 'Test rôle',
            'categorie'   => 'developpement_web',
            'niveau'      => 'debutant',
        ], $this->headers($token));

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function la_liste_des_formations_est_publique(): void
    {
        $response = $this->getJson('/api/formations');

        $response->assertStatus(200)
            ->assertJsonStructure([]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function on_peut_voir_une_formation_existante(): void
    {
        ['user' => $formateur] = $this->creerUtilisateur('formateur');
        $formation = $this->creerFormation($formateur);

        $response = $this->getJson('/api/formations/' . $formation->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['titre' => 'Formation Test']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function la_vue_dune_formation_inexistante_retourne_404(): void
    {
        $response = $this->getJson('/api/formations/9999');

        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_formateur_peut_modifier_sa_formation(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUtilisateur('formateur');
        $formation = $this->creerFormation($formateur);

        $response = $this->putJson('/api/formations/' . $formation->id, [
            'titre'       => 'Nouveau titre',
            'description' => 'Nouvelle description',
            'categorie'   => 'developpement_web',
            'niveau'      => 'intermediaire',
        ], $this->headers($token));

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Formation mise à jour avec succès']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_formateur_ne_peut_pas_modifier_la_formation_dun_autre(): void
    {
        ['user' => $formateur1] = $this->creerUtilisateur('formateur');
        $formation = $this->creerFormation($formateur1);

        $formateur2 = User::create([
            'nom'      => 'Formateur 2',
            'email'    => 'formateur2@test.com',
            'password' => bcrypt('password123'),
            'role'     => 'formateur',
        ]);
        $token2 = JWTAuth::fromUser($formateur2);

        $response = $this->putJson('/api/formations/' . $formation->id, [
            'titre'       => 'Vol de formation',
            'description' => 'Tentative',
            'categorie'   => 'developpement_web',
            'niveau'      => 'debutant',
        ], $this->headers($token2));

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_formateur_peut_supprimer_sa_formation(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUtilisateur('formateur');
        $formation = $this->creerFormation($formateur);

        $response = $this->deleteJson('/api/formations/' . $formation->id, [], $this->headers($token));

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Formation supprimée avec succès']);
    }

    // =========================================================================
    // SECTION 3 - Modules
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_formateur_peut_ajouter_un_module_a_sa_formation(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUtilisateur('formateur');
        $formation = $this->creerFormation($formateur);

        $response = $this->postJson('/api/formations/' . $formation->id . '/modules', [
            'titre'   => 'Module 1',
            'contenu' => 'Contenu du module 1',
            'ordre'   => 1,
        ], $this->headers($token));

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Module créé avec succès']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_apprenant_ne_peut_pas_creer_un_module(): void
    {
        ['user' => $formateur] = $this->creerUtilisateur('formateur');
        $formation = $this->creerFormation($formateur);

        ['token' => $tokenApprenant] = $this->creerUtilisateur('apprenant');

        $response = $this->postJson('/api/formations/' . $formation->id . '/modules', [
            'titre'   => 'Module interdit',
            'contenu' => 'Test',
            'ordre'   => 1,
        ], $this->headers($tokenApprenant));

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function la_creation_de_module_sans_token_retourne_401(): void
    {
        ['user' => $formateur] = $this->creerUtilisateur('formateur');
        $formation = $this->creerFormation($formateur);

        $response = $this->postJson('/api/formations/' . $formation->id . '/modules', [
            'titre'   => 'Module sans auth',
            'contenu' => 'Test',
            'ordre'   => 1,
        ]);

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function on_peut_lister_les_modules_dune_formation(): void
    {
        ['user' => $formateur] = $this->creerUtilisateur('formateur');
        $formation = $this->creerFormation($formateur);
        $this->creerModule($formation, 1);
        $this->creerModule($formation, 2);

        $response = $this->getJson('/api/formations/' . $formation->id . '/modules');

        $response->assertStatus(200)
            ->assertJsonCount(2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_formateur_peut_modifier_un_module_de_sa_formation(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUtilisateur('formateur');
        $formation = $this->creerFormation($formateur);
        $module    = $this->creerModule($formation);

        $response = $this->putJson('/api/modules/' . $module->id, [
            'titre'   => 'Titre modifié',
            'contenu' => 'Contenu modifié',
            'ordre'   => 1,
        ], $this->headers($token));

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Module mis à jour avec succès']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_formateur_peut_supprimer_un_module_de_sa_formation(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUtilisateur('formateur');
        $formation = $this->creerFormation($formateur);
        $module    = $this->creerModule($formation);

        $response = $this->deleteJson('/api/modules/' . $module->id, [], $this->headers($token));

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Module supprimé avec succès']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function la_modification_dun_module_inexistant_retourne_404(): void
    {
        ['token' => $token] = $this->creerUtilisateur('formateur');

        $response = $this->putJson('/api/modules/9999', [
            'titre'   => 'Test',
            'contenu' => 'Test',
            'ordre'   => 1,
        ], $this->headers($token));

        $response->assertStatus(404);
    }

    // =========================================================================
    // SECTION 4 - Inscriptions
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_apprenant_peut_sinscrire_a_une_formation(): void
    {
        ['user' => $formateur]       = $this->creerUtilisateur('formateur');
        ['token' => $tokenApprenant] = $this->creerUtilisateur('apprenant');
        $formation = $this->creerFormation($formateur);

        $response = $this->postJson('/api/formations/' . $formation->id . '/inscription', [], $this->headers($tokenApprenant));

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Inscription réussie']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function linscription_en_double_retourne_409(): void
    {
        ['user' => $formateur]                    = $this->creerUtilisateur('formateur');
        ['user' => $apprenant, 'token' => $token] = $this->creerUtilisateur('apprenant');
        $formation = $this->creerFormation($formateur);

        $this->postJson('/api/formations/' . $formation->id . '/inscription', [], $this->headers($token));
        $response = $this->postJson('/api/formations/' . $formation->id . '/inscription', [], $this->headers($token));

        $response->assertStatus(409);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_formateur_ne_peut_pas_sinscrire_a_une_formation(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUtilisateur('formateur');
        $formation = $this->creerFormation($formateur);

        $response = $this->postJson('/api/formations/' . $formation->id . '/inscription', [], $this->headers($token));

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function linscription_a_une_formation_inexistante_retourne_404(): void
    {
        ['token' => $token] = $this->creerUtilisateur('apprenant');

        $response = $this->postJson('/api/formations/9999/inscription', [], $this->headers($token));

        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_apprenant_peut_se_desinscrire(): void
    {
        ['user' => $formateur]                    = $this->creerUtilisateur('formateur');
        ['user' => $apprenant, 'token' => $token] = $this->creerUtilisateur('apprenant');
        $formation = $this->creerFormation($formateur);

        Inscription::create([
            'utilisateur_id' => $apprenant->id,
            'formation_id'   => $formation->id,
            'progression'    => 0,
        ]);

        $response = $this->deleteJson('/api/formations/' . $formation->id . '/inscription', [], $this->headers($token));

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Désinscription réussie']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_apprenant_voit_ses_formations_inscrites(): void
    {
        ['user' => $formateur]                    = $this->creerUtilisateur('formateur');
        ['user' => $apprenant, 'token' => $token] = $this->creerUtilisateur('apprenant');
        $formation = $this->creerFormation($formateur);

        Inscription::create([
            'utilisateur_id' => $apprenant->id,
            'formation_id'   => $formation->id,
            'progression'    => 0,
        ]);

        $response = $this->getJson('/api/apprenant/formations', $this->headers($token));

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    // =========================================================================
    // SECTION 5 - Progression
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_apprenant_peut_terminer_un_module(): void
    {
        ['user' => $formateur]                    = $this->creerUtilisateur('formateur');
        ['user' => $apprenant, 'token' => $token] = $this->creerUtilisateur('apprenant');
        $formation = $this->creerFormation($formateur);
        $module    = $this->creerModule($formation);

        Inscription::create([
            'utilisateur_id' => $apprenant->id,
            'formation_id'   => $formation->id,
            'progression'    => 0,
        ]);

        $response = $this->postJson('/api/modules/' . $module->id . '/terminer', [], $this->headers($token));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message'     => 'Module terminé avec succès',
                'progression' => 100,
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function la_progression_est_calculee_correctement_sur_plusieurs_modules(): void
    {
        ['user' => $formateur]                    = $this->creerUtilisateur('formateur');
        ['user' => $apprenant, 'token' => $token] = $this->creerUtilisateur('apprenant');
        $formation = $this->creerFormation($formateur);

        $module1 = $this->creerModule($formation, 1);
        $this->creerModule($formation, 2);

        Inscription::create([
            'utilisateur_id' => $apprenant->id,
            'formation_id'   => $formation->id,
            'progression'    => 0,
        ]);

        $response = $this->postJson('/api/modules/' . $module1->id . '/terminer', [], $this->headers($token));

        $response->assertStatus(200)
            ->assertJsonFragment(['progression' => 50]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function terminer_un_module_deja_termine_retourne_un_message_sans_erreur(): void
    {
        ['user' => $formateur]                    = $this->creerUtilisateur('formateur');
        ['user' => $apprenant, 'token' => $token] = $this->creerUtilisateur('apprenant');
        $formation = $this->creerFormation($formateur);
        $module    = $this->creerModule($formation);

        Inscription::create([
            'utilisateur_id' => $apprenant->id,
            'formation_id'   => $formation->id,
            'progression'    => 0,
        ]);

        $this->postJson('/api/modules/' . $module->id . '/terminer', [], $this->headers($token));
        $response = $this->postJson('/api/modules/' . $module->id . '/terminer', [], $this->headers($token));

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Ce module est déjà terminé']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function terminer_un_module_sans_etre_inscrit_retourne_403(): void
    {
        ['user' => $formateur]       = $this->creerUtilisateur('formateur');
        ['token' => $tokenApprenant] = $this->creerUtilisateur('apprenant');
        $formation = $this->creerFormation($formateur);
        $module    = $this->creerModule($formation);

        $response = $this->postJson('/api/modules/' . $module->id . '/terminer', [], $this->headers($tokenApprenant));

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function un_formateur_ne_peut_pas_terminer_un_module(): void
    {
        ['user' => $formateur, 'token' => $token] = $this->creerUtilisateur('formateur');
        $formation = $this->creerFormation($formateur);
        $module    = $this->creerModule($formation);

        $response = $this->postJson('/api/modules/' . $module->id . '/terminer', [], $this->headers($token));

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function terminer_un_module_inexistant_retourne_404(): void
    {
        ['user' => $formateur]                    = $this->creerUtilisateur('formateur');
        ['user' => $apprenant, 'token' => $token] = $this->creerUtilisateur('apprenant');
        $formation = $this->creerFormation($formateur);

        Inscription::create([
            'utilisateur_id' => $apprenant->id,
            'formation_id'   => $formation->id,
            'progression'    => 0,
        ]);

        $response = $this->postJson('/api/modules/9999/terminer', [], $this->headers($token));

        $response->assertStatus(404);
    }
    #[\PHPUnit\Framework\Attributes\Test]
    public function login_sans_email(): void
    {
        $response = $this->postJson('/api/login', [
            'password' => '123456'
        ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function login_sans_password(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@test.com'
        ]);

        $response->assertStatus(422);
    }
    #[\PHPUnit\Framework\Attributes\Test]
    public function filtre_par_categorie(): void
    {
        $response = $this->getJson('/api/formations?categorie=developpement_web');

        $response->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function filtre_par_niveau(): void
    {
        $response = $this->getJson('/api/formations?niveau=debutant');

        $response->assertStatus(200);
    }
    #[\PHPUnit\Framework\Attributes\Test]
    public function desinscription_sans_token(): void
    {
        $response = $this->deleteJson('/api/formations/1/inscription');

        $response->assertStatus(401);
    }
    #[\PHPUnit\Framework\Attributes\Test]
    public function supprimer_module_inexistant(): void
    {
        ['token' => $token] = $this->creerUtilisateur('formateur');

        $response = $this->deleteJson('/api/modules/999', [], $this->headers($token));

        $response->assertStatus(404);
    }

}
