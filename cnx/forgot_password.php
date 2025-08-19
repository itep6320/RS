<?php
session_start();
require_once __DIR__ . '/../commun/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    if (empty($username)) {
        $error = "Veuillez entrer votre nom d'utilisateur.";
    } else {
        $stmt = $mysqli->prepare("SELECT id, email FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();

        if (!$user) {
            $error = "Nom d'utilisateur non trouvé.";
        } else {
            // Générer un token sécurisé
            $token = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Enregistrer le token dans la base (table users : reset_token, reset_expires)
            $stmt = $mysqli->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $stmt->bind_param('ssi', $token, $expires, $user['id']);
            $stmt->execute();

            // Préparer le lien de réinitialisation
            $resetLink = "https://tonsite.com/auth/reset_password.php?token=$token";

            // Envoi du mail (à adapter selon ton serveur)
            $to = $user['email'];
            $subject = "Réinitialisation de votre mot de passe";
            $message = "Bonjour " . htmlspecialchars($username) . ",\n\n";
            $message .= "Pour réinitialiser votre mot de passe, cliquez sur ce lien :\n";
            $message .= $resetLink . "\n\n";
            $message .= "Ce lien expire dans 1 heure.";

            $headers = "From: no-reply@tonsite.com\r\n";
            if (mail($to, $subject, $message, $headers)) {
                $success = "Un email de réinitialisation a été envoyé à l'adresse associée à ce compte.";
            } else {
                $error = "Impossible d'envoyer l'email. Veuillez contacter l'administrateur.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mot de passe oublié</title>
    <link rel="stylesheet" href="../commun/styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <h1>Mot de passe oublié</h1>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <form method="post" class="login-form">
            <label>
                Nom d'utilisateur :
                <input type="text" name="username" required>
            </label>
            <button type="submit">Réinitialiser le mot de passe</button>
        </form>
        <div class="login-actions">
            <a href="login.php" class="login-button">Retour à la connexion</a>
        </div>
    </div>
</body>
</html>
