<?php
require_once __DIR__ . '/../commun/db.php';
require_once __DIR__ . '/../assets/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../assets/PHPMailer/SMTP.php';
require_once __DIR__ . '/../assets/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Vérifications basiques
    if (!$username || !$email || !$password || !$confirm_password) {
        $error = "Tous les champs sont obligatoires.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Vérifier si le nom d'utilisateur ou email existe déjà
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        if (!$stmt) {
            die("Erreur SQL (SELECT) : " . $mysqli->error);
        }
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $error = "Nom d'utilisateur ou email déjà utilisé.";
        } else {
            // Générer mot de passe hashé
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Générer token d'activation
            $activation_token = bin2hex(random_bytes(16));

            // Insérer l'utilisateur en base (non activé)
            $stmt = $mysqli->prepare("INSERT INTO users (username, email, password, is_active, activation_token) VALUES (?, ?, ?, 0, ?)");
            if (!$stmt) {
                die("Erreur SQL (INSERT) : " . $mysqli->error);
            }
            $stmt->bind_param('ssss', $username, $email, $hashed_password, $activation_token);

            if ($stmt->execute()) {
                // Préparer le lien d'activation (corrigé)
                $activationLink = 'https://' . $_SERVER['HTTP_HOST'] . '/rs/cnx/activate.php?email=' . urlencode($email) . '&cle=' . $activation_token;

                // Sujet du mail
                $sujet = "Activation de votre compte - Rando Secure";

                // Version HTML
                $contenuHTML = "
                <html>
                <body style='font-family: Arial, sans-serif; color: #333;'>
                    <h2>Bonjour $username,</h2>
                    <p>Merci de vous être inscrit sur <b>Rando Secure</b>.</p>
                    <p>Cliquez sur le bouton ci-dessous pour activer votre compte :</p>
                    <p style='text-align:center; margin:20px;'>
                        <a href='$activationLink' 
                           style='display:inline-block; text-align:center; text-decoration:none; 
                                  padding:12px; background-color:#4CAF50; border:none; 
                                  color:white; font-size:16px; border-radius:5px; 
                                  cursor:pointer; transition:background 0.3s;'>
                           Activer mon compte
                        </a>
                    </p>
                    <p>Ou copiez-collez ce lien dans votre navigateur :<br>
                    <a href='$activationLink'>$activationLink</a></p>
                    <hr>
                    <p style='font-size:12px;color:#777;'>Cet email vous a été envoyé par Rando Secure<br>
                    Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.</p>
                </body>
                </html>
                ";

                // Version texte brut
                $contenuTXT = "Bonjour $username,\n\nCliquez sur ce lien pour activer votre compte :\n$activationLink";

                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'thierry.enjalbert@gmail.com';
                    $mail->Password   = 'vndbrfvwalsayahc';
                    $mail->SMTPSecure = 'tls';
                    $mail->Port       = 587;
                    $mail->setFrom('thierry.enjalbert@gmail.com', 'Rando Secure');
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = $sujet;
                    $mail->Body    = $contenuHTML;
                    $mail->AltBody = $contenuTXT;

                    $mail->send();
                    $error = "✅ Un lien d'activation vous a été envoyé par e-mail.";
                } catch (Exception $e) {
                    $error = "❌ Erreur d’envoi : " . $mail->ErrorInfo;
                }

            } else {
                $error = "Erreur lors de la création du compte.";
            }
        }
    }
}
if (session_status() === PHP_SESSION_NONE) session_start();
?>


<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Créer un compte</title>
    <link rel="stylesheet" href="../commun/styles.css">
</head>

<body class="login-page">
    <div class="login-container">
        <h1>Créer un compte</h1>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success"><?= htmlspecialchars($success) ?></p>
            <a href="login.php" class="login-button">Se connecter</a>
        <?php else: ?>
            <form method="post" class="login-form">
                <label>
                    Nom d'utilisateur :
                    <input type="text" name="username" required>
                </label>
                <label>
                    Email :
                    <input type="email" name="email" required>
                </label>
                <label>
                    Mot de passe :
                    <input type="password" name="password" required>
                </label>
                <label>
                    Confirmer le mot de passe :
                    <input type="password" name="confirm_password" required>
                </label>
                <button type="submit">Créer un compte</button>
            </form>
            <div class="login-actions">
                <a href="login.php" class="login-button">Retour à la connexion</a>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
