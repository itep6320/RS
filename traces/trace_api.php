<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once("../commun/db.php");
session_start();

$action = $_GET['action'] ?? '';
$id_user = $_SESSION['user']['id'] ?? null;

// Si pas connecté → on ne fait rien, c'est le JS qui gère via localStorage
if (!$id_user) {
    if ($action == 'read') {
        echo json_encode([]); // aucune trace en BDD
        exit;
    }
    if ($action == 'create' || $action == 'delete') {
        exit("MODE_LOCALSTORAGE"); // signaler au JS de basculer
    }
}

// Lecture de toutes les zones de chasse
if ($action == 'zones') {
    $res = $pdo->query("SELECT id,nom,type_chasse,date_debut,date_fin,ST_AsGeoJSON(geometry) AS geojson FROM zones_chasse");
    $zones = $res->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($zones);
    exit;
}

// Lecture de toutes les traces pour l'utilisateur courant
if ($action == 'read') {
    $stmt = $pdo->prepare("SELECT id,nom,ST_AsGeoJSON(geometry) AS geojson 
                           FROM traces_gpx 
                           WHERE id_user = ?");
    $stmt->execute([$id_user]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Supprimer une trace (seulement si elle appartient à l'utilisateur)
if ($action == 'delete') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM traces_gpx WHERE id=? AND id_user=?");
        echo $stmt->execute([$id, $id_user]) ? 'OK' : 'Erreur suppression';
    } else echo 'ID invalide';
    exit;
}

// Créer une trace
if ($action == 'create') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    $logFile = '/tmp/trace_debug.log';
    file_put_contents($logFile, "=== Nouvelle requête create ===\n", FILE_APPEND);

    // Vérification des champs
    if (empty($_POST['nom']) || empty($_FILES['gpx']) || $_FILES['gpx']['error'] !== 0) {
        exit('Champs manquants ou fichier GPX invalide');
    }

    $nom = $_POST['nom'];

    // Charger le GPX
    $gpx = @simplexml_load_file($_FILES['gpx']['tmp_name']);
    if (!$gpx) exit('Fichier GPX invalide');

    $geojson = ['type' => 'FeatureCollection', 'features' => []];

    foreach ($gpx->trk as $trk) {
        foreach ($trk->trkseg as $seg) {
            $coords = [];
            foreach ($seg->trkpt as $pt) {
                $lat = floatval($pt['lat']);
                $lon = floatval($pt['lon']);
                $coords[] = [$lon, $lat];
            }
            if (count($coords) > 1) {
                $geojson['features'][] = [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'LineString', 'coordinates' => $coords],
                    'properties' => []
                ];
            }
        }
    }

    if (empty($geojson['features'])) {
        exit('Pas de données GPS valides');
    }

    $json = json_encode($geojson);
    if ($json === false) {
        exit("Erreur JSON: " . json_last_error_msg());
    }

    // Insertion SQL avec id_user
    try {
        $stmt = $pdo->prepare("INSERT INTO traces_gpx (nom,geometry,id_user) 
                               VALUES (?, ST_GeomFromGeoJSON(?), ?)");
        $ok = $stmt->execute([$nom, $json, $id_user]);
        echo $ok ? 'OK' : 'Erreur insertion';
    } catch (Exception $e) {
        exit("Erreur SQL: " . $e->getMessage());
    }
    exit;
}
