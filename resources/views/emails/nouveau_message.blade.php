<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body        { font-family: Arial, sans-serif; background:#f4f4f4; margin:0; padding:0; }
        .container  { max-width:560px; margin:40px auto; background:#ffffff; border-radius:8px; overflow:hidden; }
        .header     { background:#0d1b2a; padding:28px 32px; }
        .header h1  { color:#f5a623; margin:0; font-size:1.4rem; }
        .body       { padding:28px 32px; color:#333333; line-height:1.6; }
        .message    { background:#f0f4f8; border-left:4px solid #f5a623; padding:14px 18px;
                      border-radius:4px; margin:18px 0; font-style:italic; color:#444; }
        .btn        { display:inline-block; margin-top:20px; padding:12px 28px;
                      background:#f5a623; color:#0d1b2a; text-decoration:none;
                      border-radius:6px; font-weight:bold; }
        .footer     { padding:16px 32px; font-size:0.8rem; color:#999; border-top:1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SkillHub — Nouveau message</h1>
        </div>
        <div class="body">
            <p>Bonjour <strong>{{ $destinataire }}</strong>,</p>
            <p>Vous avez reçu un nouveau message de <strong>{{ $expediteur }}</strong> :</p>
            <div class="message">{{ $contenu }}</div>
            <p>Connectez-vous à SkillHub pour répondre.</p>
            <a href="{{ $lienPlateforme }}" class="btn">Voir le message</a>
        </div>
        <div class="footer">
            Cet email a été envoyé automatiquement par SkillHub. Ne pas répondre directement.
        </div>
    </div>
</body>
</html>