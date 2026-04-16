<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Mail envoyé au destinataire lors du premier message d'une conversation.
 */
class NouveauMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $expediteur;
    public string $destinataire;
    public string $contenu;
    public string $lienPlateforme;

    public function __construct(string $expediteur, string $destinataire, string $contenu)
    {
        $this->expediteur     = $expediteur;
        $this->destinataire   = $destinataire;
        $this->contenu        = $contenu;
        $this->lienPlateforme = config('app.url');
    }

    public function build(): static
    {
        return $this->subject('SkillHub — Nouveau message de ' . $this->expediteur)
                    ->view('emails.nouveau_message');
    }
}
