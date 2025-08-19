<?php
require_once __DIR__ . "/../commun/db.php";

$action = $_GET['action'] ?? '';

if ($action === 'read') {
    $res = $mysqli->query("SELECT id, nom, type_chasse, DATE(date_debut) as date_debut, DATE(date_fin) as date_fin, ST_AsGeoJSON(geometry) as geojson FROM zones_chasse");
    $zones = [];
    while ($row = $res->fetch_assoc()) {
        $zones[] = $row;
    }
    echo json_encode($zones);
} elseif ($action === 'create') {
    $nom = $mysqli->real_escape_string($_POST['nom']);
    $type = $mysqli->real_escape_string($_POST['type_chasse']);
    $debut = $mysqli->real_escape_string($_POST['date_debut']);
    $fin = $mysqli->real_escape_string($_POST['date_fin']);
    $wkt = $mysqli->real_escape_string($_POST['wkt']);
    $sql = "INSERT INTO zones_chasse (nom, type_chasse, date_debut, date_fin, geometry)
            VALUES ('$nom', '$type', '$debut', '$fin', ST_GeomFromText('$wkt', 4326))";
    echo $mysqli->query($sql) ? "OK" : $mysqli->error;
} elseif ($action === 'delete') {
    $id = (int)$_GET['id'];
    echo $mysqli->query("DELETE FROM zones_chasse WHERE id=$id") ? "OK" : $mysqli->error;
} elseif ($action === 'update') {
    $id = (int)$_POST['id'];
    $wkt = $mysqli->real_escape_string($_POST['wkt']);
    $sql = "UPDATE zones_chasse SET geometry = ST_GeomFromText('$wkt', 4326) WHERE id=$id";
    echo $mysqli->query($sql) ? "OK" : $mysqli->error;
}


$mysqli->close();
