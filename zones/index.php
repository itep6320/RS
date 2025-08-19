<?php
    require_once __DIR__ . '/../commun/header.php';
?>

<body>
    <!-- Sélecteur de date -->
    <input type="date" id="dateSelector">

    <div id="map"></div>
    <button id="btnAddZone">＋</button>

    <div id="zoneModal" class="modal">
        <h3>Ajouter une zone</h3>
        <select id="zone_forme">
            <option value="circle">Cercle</option>
            <option value="rectangle">Rectangle</option>
            <option value="polygon">Polygone</option>
        </select>
        <input type="text" id="zone_nom" placeholder="Nom">
        <select id="zone_type">
            <option value="battue">Battue</option>
            <option value="courre">Courre</option>
            <option value="approche">Approche</option>
            <option value="affut">Affût</option>
        </select>
        <input type="date" id="zone_debut">
        <input type="date" id="zone_fin">
        <button id="startDraw">Ajouter</button>
        <button onclick="closeModal()">Annuler</button>
    </div>

    <script>
        // Récupération depuis localStorage
        const savedLat = localStorage.getItem('mapCenterLat');
        const savedLng = localStorage.getItem('mapCenterLng');
        const savedZoom = localStorage.getItem('mapZoom');

        const map = L.map('map', {
            editable: true
        }).setView(
            savedLat && savedLng ? [savedLat, savedLng] : [46.8, 2.5],
            savedZoom ? savedZoom : 6
        );

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(map);

        const zonesLayer = new L.FeatureGroup().addTo(map);

        // Sauvegarder position et zoom de la carte
        map.on('moveend zoomend', function() {
            const center = map.getCenter();
            localStorage.setItem('mapCenterLat', center.lat);
            localStorage.setItem('mapCenterLng', center.lng);
            localStorage.setItem('mapZoom', map.getZoom());
        });

        // On garde la date choisie globalement
        let selectedDate = new Date().toISOString().split('T')[0];

        // Quand l'utilisateur change la date
        document.getElementById('dateSelector').addEventListener('change', function() {
            selectedDate = this.value || new Date().toISOString().split('T')[0];
            loadZones(); // recharger avec la nouvelle date
        });

        // On met par défaut la date du jour dans le sélecteur
        document.getElementById('dateSelector').value = selectedDate;
        // Conversion en WKT
        function geoJSONToWKT(geojson) {
            if (!geojson || !geojson.geometry) return null;
            const coordsToStr = coords => coords.map(c => c.join(' ')).join(',');
            switch (geojson.geometry.type) {
                case "Polygon":
                    return 'POLYGON((' + coordsToStr(geojson.geometry.coordinates[0]) + '))';
                case "MultiPolygon":
                    return 'MULTIPOLYGON(' + geojson.geometry.coordinates.map(
                        poly => '((' + coordsToStr(poly[0]) + '))'
                    ).join(',') + ')';
                case "Point":
                    return 'POINT(' + geojson.geometry.coordinates.join(' ') + ')';
                default:
                    return null;
            }
        }

        // Sauvegarder la zone
        function saveLayer(layer, id) {
            const wkt = geoJSONToWKT(layer.toGeoJSON());
            if (!wkt) return alert("Impossible de convertir la géométrie.");
            fetch('zones_api.php?action=update', {
                method: 'POST',
                body: new URLSearchParams({
                    id: id,
                    wkt: wkt
                })
            }).then(r => r.text()).then(resp => {
                if (resp.trim() != 'OK') alert(resp);
            });
        }

        // Afficher les zones
        function loadZones(zoomLast = false) {
            fetch('zones_api.php?action=read')
                .then(r => r.json())
                .then(data => {
                    zonesLayer.clearLayers();
                    let lastLayer = null;

                    // Filtrer les zones dont la date de fin < aujourd'hui
                    const filteredZones = data.filter(z => z.date_fin >= selectedDate);

                    filteredZones.forEach(z => {
                        const color = {
                            battue: 'red',
                            courre: 'orange',
                            approche: 'green',
                            affut: 'purple'
                        } [z.type_chasse] || 'gray';
                        const layer = L.geoJSON(JSON.parse(z.geojson), {
                            style: {
                                color: color,
                                fillOpacity: 0.3
                            }
                        }).addTo(zonesLayer);
                        const popupHtml = `<b>${z.nom}</b><br>${z.type_chasse}<br>${z.date_debut} → ${z.date_fin}<br><button onclick="deleteZone(${z.id})" style="margin-top:5px;color:red;">Supprimer</button>`;
                        layer.bindPopup(popupHtml);

                        // Zoom lent sur click 
                        layer.on('click', () => {
                            map.flyToBounds(layer.getBounds(), {
                                padding: [20, 20],
                                duration: 1.5
                            });
                        });
                        lastLayer = layer;
                    });
                    // Zoom sur la dernière trace si demandé
                    if (zoomLast && lastLayer) {
                        map.flyToBounds(lastLayer.getBounds(), {
                            padding: [20, 20],
                            duration: 1.5
                        });
                    }
                });
        }

        // Supprimer une zone
        function deleteZone(id) {
            if (!confirm('Supprimer cette zone ?')) return;
            fetch('zones_api.php?action=delete&id=' + id)
                .then(r => r.text())
                .then(resp => {
                    if (resp.trim() == 'OK') loadZones();
                    else alert(resp);
                });
        }

        // Afficher modale zone
        document.getElementById('btnAddZone').onclick = () => document.getElementById('zoneModal').classList.add('show');

        // Fermer modale zone
        function closeModal() {
            document.getElementById('zoneModal').classList.remove('show');
        }

        // Dessiner la zone
        document.getElementById('startDraw').onclick = () => {
            const nom = document.getElementById('zone_nom').value.trim();
            const type = document.getElementById('zone_type').value;
            const debut = document.getElementById('zone_debut').value;
            const fin = document.getElementById('zone_fin').value;
            const forme = document.getElementById('zone_forme').value;

            if (!nom || !debut || !fin) {
                alert('Veuillez remplir tous les champs.');
                return;
            }

            closeModal();

            let drawControl;
            if (forme === 'circle') {
                drawControl = new L.Draw.Circle(map);
            } else if (forme === 'rectangle') {
                drawControl = new L.Draw.Rectangle(map);
            } else if (forme === 'polygon') {
                drawControl = new L.Draw.Polygon(map);
            }

            drawControl.enable();

            map.once(L.Draw.Event.CREATED, function(e) {
                let wkt;
                if (forme === 'circle') {
                    wkt = circleToWKT(e.layer.getLatLng(), e.layer.getRadius());
                } else {
                    wkt = geoJSONToWKT(e.layer.toGeoJSON());
                }

                if (!wkt) {
                    alert("Erreur de conversion WKT.");
                    return;
                }

                const fd = new FormData();
                fd.append('nom', nom);
                fd.append('type_chasse', type);
                fd.append('date_debut', debut);
                fd.append('date_fin', fin);
                fd.append('wkt', wkt);

                fetch('zones_api.php?action=create', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.text())
                    .then(resp => {
                        if (resp.trim() == 'OK') loadZones(true); // Zoom sur la Zone ajoutée
                        else alert(resp);
                    });
            });
        };

        // Conversion du cercle vers WKT
        function circleToWKT(latlng, radius, points = 64) {
            const R = 6378137;
            const coords = [];
            for (let i = 0; i <= points; i++) {
                const brng = i * 360 / points * Math.PI / 180;
                const lat1 = latlng.lat * Math.PI / 180;
                const lon1 = latlng.lng * Math.PI / 180;
                const lat2 = Math.asin(Math.sin(lat1) * Math.cos(radius / R) +
                    Math.cos(lat1) * Math.sin(radius / R) * Math.cos(brng));
                const lon2 = lon1 + Math.atan2(Math.sin(brng) * Math.sin(radius / R) * Math.cos(lat1),
                    Math.cos(radius / R) - Math.sin(lat1) * Math.sin(lat2));
                coords.push((lon2 * 180 / Math.PI) + ' ' + (lat2 * 180 / Math.PI));
            }
            return 'POLYGON((' + coords.join(',') + '))';
        }

        // Initialisation
        loadZones();
    </script>
</body>
