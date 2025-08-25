<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../commun/db.php';

$message = '';

$token = $_GET['cle'] ?? '';

if (!$token) {
    $message = "Lien d'activation invalide.";
} else {
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE activation_token = ? AND is_active = 0");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if ($user) {
        // Activer le compte
        $stmt = $mysqli->prepare("UPDATE users SET is_active = 1, activation_token = NULL WHERE id = ?");
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $message = "Compte activé ! Vous pouvez maintenant vous connecter.";
    } else {
        $message = "Lien invalide ou compte déjà activé.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Activation du compte</title>
    <link rel="stylesheet" href="../commun/styles.css">
</head>
<body class="login-page">
<div class="login-container">
    <h1>Activation du compte</h1>
    <p><?= htmlspecialchars($message) ?></p>
    <a href="login.php" class="login-button">Se connecter</a>
</div>
</body>
</html>
