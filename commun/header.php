<?php
require_once __DIR__ . "/../cnx/auth.php";
require_once __DIR__ . "/../commun/db.php";

// Changement de mode via GET
if (isset($_GET['switch_role']) && can_be($_GET['switch_role'])) {
    $_SESSION['mode'] = $_GET['switch_role'];
    // Redirection pour éviter le resoumission GET
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

$mode_actuel = $_SESSION['mode'] ?? 'inconnu';
?>

<!doctype html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Rando Secure</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <!-- Favicon -->
    <link rel="icon" href="../commun/favicon.ico" type="image/x-icon">

    <meta charset="utf-8">
    <title>Gestion Zones de Chasse</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Feuilles de style -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw/dist/leaflet.draw.css" />
    <link rel="stylesheet" title="defaut" media="screen" href="../commun/styles.css" type="text/css" />

    <!-- JS divers -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-draw/dist/leaflet.draw.js"></script>
    <script src="https://unpkg.com/leaflet-editable@1.2.0/src/Leaflet.Editable.js"></script>
</head>

<header style="background:#eee;padding:10px;display:flex;justify-content:space-between;align-items:center;">
    <div>
        <?php if (isset($_SESSION['user'])): ?>
            <strong><?php echo $_SESSION['user']['username'] ?> :</strong>
            <?php if ($mode_actuel === 'chasseur'): ?>
                🦌 Chasseur
            <?php elseif ($mode_actuel === 'randonneur'): ?>
                🥾 Randonneur
            <?php else: ?>
                ❓ Inconnu
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <nav>
        <?php if (isset($_SESSION['user'])): ?>
            <?php if (can_be('chasseur') && can_be('randonneur')): ?>
                <?php if ($mode_actuel === 'chasseur'): ?>
                    <span
                        style="cursor:pointer;color:blue;text-decoration:underline;"
                        onclick="window.location.href = '../traces/index.php?switch_role=randonneur';">
                        Passer en mode Randonneur
                    </span>
                <?php else: ?>
                    <span
                        style="cursor:pointer;color:blue;text-decoration:underline;"
                        onclick="window.location.href = '../zones/index.php?switch_role=chasseur';">
                        Passer en mode Chasseur
                    </span>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Déconnexion -->
            | <a href="../cnx/logout.php">Déconnexion</a>
        <?php else: ?>
            <!-- Utilisateur non connecté -->
            <a href="../cnx/login.php">Connexion</a>
        <?php endif; ?>
    </nav>
</header>