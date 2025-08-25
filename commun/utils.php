<?php
// utils.php

function geoJsonPolygonToWKT($geojson)
{
    if (!isset($geojson['type']) || $geojson['type'] !== 'Polygon') throw new Exception('Expected Polygon GeoJSON');
    $rings = $geojson['coordinates'];
    $parts = [];
    foreach ($rings as $ring) {
        $coords = [];
        foreach ($ring as $pt) {
            $coords[] = $pt[0] . ' ' . $pt[1]; // lon lat
        }
        $parts[] = '(' . implode(',', $coords) . ')';
    }
    return 'POLYGON(' . implode(',', $parts) . ')';
}

function geoJsonLineStringToWKT($geojson)
{
    if (!isset($geojson['type']) || $geojson['type'] !== 'LineString') throw new Exception('Expected LineString GeoJSON');
    $coords = [];
    foreach ($geojson['coordinates'] as $pt) {
        $coords[] = $pt[0] . ' ' . $pt[1];
    }
    return 'LINESTRING(' . implode(',', $coords) . ')';
}
