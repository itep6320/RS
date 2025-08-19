<?php
session_start();
require_once __DIR__ . '/../commun/db.php';

// Si déjà connecté → rediriger
if (isset($_SESSION['user'])) {
    header("Location: ../zones/index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $mysqli->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'is_chasseur' => (bool)$user['is_chasseur'],
            'is_randonneur' => (bool)$user['is_randonneur']
        ];

        if ($user['is_chasseur']) {
            $_SESSION['mode'] = 'chasseur';
        } elseif ($user['is_randonneur']) {
            $_SESSION['mode'] = 'randonneur';
        }

        header("Location: ../zones/index.php");
        exit;
    } else {
        $error = "Identifiants invalides";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Connexion</title>
    <link rel="stylesheet" title="defaut" media="screen" href="../commun/styles.css" type="text/css" />
    <link rel="icon" href="../commun/favicon.ico" type="image/x-icon">
</head>

<body class="login-page">
    <div class="login-container">
        <h1>Connexion</h1>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post" class="login-form">
            <label>
                Nom d'utilisateur :
                <input type="text" name="username" required>
            </label>
            <label>
                Mot de passe :
                <input type="password" name="password" required>
            </label>
            <div>
                <button type="submit">Se connecter</button>
                <a href="forgot_password.php" class="login-button">Mot de passe oublié ?</a>
                <a href="register.php" class="login-button">Créer un compte</a>
            </div>
        </form>

    </div>
</body>

</html>