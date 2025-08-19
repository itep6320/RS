<?php
$pageActive = "traces";
require_once __DIR__ . "/../commun/header.php";
?>

<body>
  <!-- Sélecteur de date -->
  <input type="date" id="dateSelector">

  <div id="map"></div>
  <button id="btnAddTrace">＋</button>

  <div id="traceModal" class="modal">
    <h3>Ajouter trace GPX</h3>
    <input type="text" id="trace_nom" placeholder="Nom">
    <input type="file" id="trace_file" accept=".gpx">
    <button id="uploadTrace">Ajouter</button>
    <button onclick="closeTraceModal()">Annuler</button>
  </div>

  <script>
    // Récupération depuis localStorage
    const savedLat = localStorage.getItem('mapCenterLat');
    const savedLng = localStorage.getItem('mapCenterLng');
    const savedZoom = localStorage.getItem('mapZoom');

    const map = L.map('map').setView(
      savedLat && savedLng ? [savedLat, savedLng] : [46.8, 2.5],
      savedZoom ? savedZoom : 6
    );

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19
    }).addTo(map);

    const zonesLayer = L.featureGroup().addTo(map);
    const tracesLayer = L.featureGroup().addTo(map);

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

    // Affichage des zones
    function loadZones() {
      fetch('trace_api.php?action=zones')
        .then(r => r.json())
        .then(data => {
          zonesLayer.clearLayers();

          // Filtrer en fonction de la date choisie
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
                color,
                fillOpacity: 0.3
              }
            }).addTo(zonesLayer);

            // Affichage Pop-up détail + suppression
            layer.bindPopup(`<b>${z.nom}</b><br>${z.type_chasse}<br>${z.date_debut} → ${z.date_fin}`);

            // Zoom lent sur click 
            layer.on('click', () => {
              map.flyToBounds(layer.getBounds(), {
                padding: [20, 20],
                duration: 1.5
              });
            });
          });
        });
    }

    // ---- Gestion traces en localStorage si pas connecté ----
    function getLocalTraces() {
      return JSON.parse(localStorage.getItem("local_traces") || "[]");
    }

    function saveLocalTraces(traces) {
      localStorage.setItem("local_traces", JSON.stringify(traces));
    }

    // Affichage des traces
    function loadTraces(zoomLast = false) {
      fetch('trace_api.php?action=read')
        .then(r => r.json())
        .then(data => {
          // Si vide => essayer localStorage
          if (!data || data.length === 0) {
            data = getLocalTraces();
          }

          tracesLayer.clearLayers();
          let lastLayer = null;

          data.forEach(t => {
            const layer = L.geoJSON(JSON.parse(t.geojson), {
              color: 'blue',
              weight: 3
            }).addTo(tracesLayer);

            // Extraire les coordonnées
            let coords = [];
            layer.eachLayer(l => {
              if (l.feature && l.feature.geometry.type === "LineString") {
                coords = coords.concat(l.feature.geometry.coordinates);
              }
            });

            // Calculs : distance, dénivelés
            let distance = 0;
            let posGain = 0;
            let negGain = 0;
            let minAlt = Infinity;
            let maxAlt = -Infinity;

            for (let i = 1; i < coords.length; i++) {
              const [lon1, lat1, ele1] = coords[i - 1];
              const [lon2, lat2, ele2] = coords[i];

              // Distance Haversine
              const R = 6371;
              const dLat = (lat2 - lat1) * Math.PI / 180;
              const dLon = (lon2 - lon1) * Math.PI / 180;
              const a = Math.sin(dLat / 2) ** 2 +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) ** 2;
              const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
              distance += R * c;

              // Dénivelés si altitudes présentes
              if (ele1 !== undefined && ele2 !== undefined) {
                const diff = ele2 - ele1;
                if (diff > 0) posGain += diff;
                else negGain += diff;
                minAlt = Math.min(minAlt, ele1, ele2);
                maxAlt = Math.max(maxAlt, ele1, ele2);
              }
            }

            distance = distance.toFixed(2);
            posGain = Math.round(posGain);
            negGain = Math.round(negGain);
            minAlt = minAlt === Infinity ? "?" : Math.round(minAlt);
            maxAlt = maxAlt === -Infinity ? "?" : Math.round(maxAlt);

            // Popup formatée comme ton exemple
            layer.bindPopup(`
              <div style="font-size:14px;">
                <b>${t.nom}</b><br>
                <span>📍 ${distance} km</span><br>
                <span>⬆️ +${posGain} m</span> &nbsp; <span>⬇️ ${negGain} m</span><br>
                <span>⛰️ Haut : ${maxAlt} m / Bas : ${minAlt} m</span><br>
                <button onclick="deleteTrace(${t.id})" style="color:red;margin-top:5px;">Supprimer</button>
              </div>
            `);

            layer.on('click', () => {
              map.flyToBounds(layer.getBounds(), {
                padding: [20, 20],
                duration: 1.5
              });
            });

            lastLayer = layer;
          });

          if (zoomLast && lastLayer) {
            map.flyToBounds(lastLayer.getBounds(), {
              padding: [20, 20],
              duration: 1.5
            });
          }
        });
    }

    // Suppression d’une trace
    function deleteTrace(id) {
      fetch('trace_api.php?action=delete&id=' + id)
        .then(r => r.text())
        .then(resp => {
          if (resp.trim() === 'OK') {
            loadTraces();
          } else if (resp.trim() === 'MODE_LOCALSTORAGE') {
            let traces = getLocalTraces();
            traces = traces.filter(t => t.id !== id);
            saveLocalTraces(traces);
            loadTraces();
          } else {
            alert(resp);
          }
        });
    }

    // Afficher Modale Trace
    document.getElementById('btnAddTrace').onclick = () => {
      document.getElementById('traceModal').classList.add('show');
    };

    // Fermer modale trace
    function closeTraceModal() {
      document.getElementById('traceModal').classList.remove('show');
    }

    // Ajouter une trace
    document.getElementById('uploadTrace').onclick = () => {
      const nom = document.getElementById('trace_nom').value.trim();
      const file = document.getElementById('trace_file').files[0];
      if (!nom || !file) {
        alert('Veuillez remplir tous les champs');
        return;
      }

      const formData = new FormData();
      formData.append('nom', nom);
      formData.append('gpx', file);

      fetch('trace_api.php?action=create', {
          method: 'POST',
          body: formData
        })
        .then(r => r.text())
        .then(resp => {
          if (resp.trim() === 'OK') {
            closeTraceModal();
            loadTraces(true);
          } else if (resp.trim() === 'MODE_LOCALSTORAGE') {
            // Lecture du GPX localement
            const reader = new FileReader();
            reader.onload = function(e) {
              const gpx = new DOMParser().parseFromString(e.target.result, "application/xml");
              const coords = [];
              gpx.querySelectorAll("trkpt").forEach(pt => {
                const lat = parseFloat(pt.getAttribute("lat"));
                const lon = parseFloat(pt.getAttribute("lon"));
                const ele = pt.querySelector("ele") ? parseFloat(pt.querySelector("ele").textContent) : undefined;
                coords.push([lon, lat, ele]);
              });

              if (coords.length > 1) {
                const geojson = {
                  type: "FeatureCollection",
                  features: [{
                    type: "Feature",
                    geometry: {
                      type: "LineString",
                      coordinates: coords
                    },
                    properties: {}
                  }]
                };
                let traces = getLocalTraces();
                const newTrace = {
                  id: Date.now(), // identifiant unique local
                  nom: nom,
                  geojson: JSON.stringify(geojson)
                };
                traces.push(newTrace);
                saveLocalTraces(traces);
                closeTraceModal();
                loadTraces(true);
              } else {
                alert("Pas de données GPX valides");
              }
            };
            reader.readAsText(file);
          } else {
            alert(resp);
          }
        });
    };

    // Initialisation
    loadZones();
    loadTraces();
  </script>
</body>