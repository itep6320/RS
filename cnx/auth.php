<?php
session_start();

// Vérifie si l'utilisateur est connecté ou si la page est publique
if (!isset($_SESSION['user']) && !on_public_page()) {
    header("Location: ../cnx/login.php");
    exit;
}

// Raccourci pour savoir si on est chasseur ou randonneur
function is_chasseur()
{
    return isset($_SESSION['mode']) && $_SESSION['mode'] === 'chasseur';
}

function is_randonneur()
{
    return isset($_SESSION['mode']) && $_SESSION['mode'] === 'randonneur';
}

// Vérifie si l'utilisateur a le droit de passer en mode donné
function can_be($role)
{
    return !empty($_SESSION['user']["is_$role"]);
}

// Permet de rendre publiques certaines pages
function on_public_page()
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    $publicPrefixes = [
        '/rs/index.php',
        '/rs/traces',
        '/rs/cnx/login.php',
        '/rs/cnx/logout.php',
        '/rs/cnx/forgot_password.php',
        '/rs/cnx/activate.php',
        '/rs/cnx/register.php'
    ];
    foreach ($publicPrefixes as $p) {
        if (strpos($path, $p) === 0) {
            // laisser passer
            return true;
        }
    }
    return false;
}
