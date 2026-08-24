/**
 * Leaflet GIS Interactive Map Controller
 * GeoGuardians - DisasterSafe
 */

let map = null;
let tileLayer = null;
let alertMarkers = {};
let facilityMarkers = [];
let showFacilities = true;

const facilitiesData = [
    { id: 'HOSP-01', name: 'District Multi-Specialty Hospital', type: 'Hospital', lat: 28.4800, lng: 77.4800, beds: 42, icon: '🏥' },
    { id: 'HOSP-02', name: 'Apex Trauma & Emergency Care', type: 'Hospital', lat: 28.5200, lng: 77.3800, beds: 12, icon: '🏥' },
    { id: 'SHELTER-01', name: 'Sector 4 Community Relief Shelter', type: 'Relief Shelter', lat: 28.4400, lng: 77.5200, beds: 120, icon: '⛺' },
    { id: 'SHELTER-02', name: 'Govt. Stadium Evacuation Camp', type: 'Relief Shelter', lat: 28.6000, lng: 77.2200, beds: 80, icon: '⛺' },
    { id: 'FIRE-01', name: 'Central Fire & Rescue Depot', type: 'Fire Station', lat: 28.5400, lng: 77.4100, beds: 0, icon: '🚒' },
    { id: 'POLICE-01', name: 'Sector 12 Police Command Hub', type: 'Police Station', lat: 28.4600, lng: 77.4900, beds: 0, icon: '🚓' }
];

function initCommandMap(elementId = 'commandMap') {
    const mapEl = document.getElementById(elementId);
    if (!mapEl) return;

    if (map) {
        map.invalidateSize();
        return;
    }

    map = L.map(elementId, {
        center: [28.5200, 77.3000],
        zoom: 11,
        zoomControl: false
    });

    L.control.zoom({ position: 'bottomright' }).addTo(map);

    tileLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; CartoDB &copy; OpenStreetMap',
        maxZoom: 19
    }).addTo(map);

    renderFacilityMarkers();
}

function switchMapLayer(layer) {
    if (!map || !tileLayer) return;
    map.removeLayer(tileLayer);
    if (layer === 'sat') {
        tileLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '&copy; Esri'
        }).addTo(map);
    } else if (layer === 'streets') {
        tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
    } else {
        tileLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; CartoDB &copy; OpenStreetMap'
        }).addTo(map);
    }
}

function recenterMap() {
    if (map) map.setView([28.5200, 77.3000], 11);
}

function renderFacilityMarkers() {
    if (!map) return;
    facilityMarkers.forEach(m => map.removeLayer(m));
    facilityMarkers = [];

    if (!showFacilities) return;

    facilitiesData.forEach(fac => {
        const icon = L.divIcon({
            html: `<div style="background:#1e293b; color:white; border:2px solid #38bdf8; border-radius:50%; width:30px; height:30px; display:grid; place-items:center; font-size:14px; box-shadow:0 0 10px rgba(56,189,248,0.5);">${fac.icon}</div>`,
            className: '',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });

        const marker = L.marker([fac.lat, fac.lng], { icon }).addTo(map);
        marker.bindPopup(`
            <div style="font-family:sans-serif; min-width:180px;">
                <h4 style="margin:0 0 4px; color:#0f172a;">${fac.name}</h4>
                <div style="font-size:12px; color:#64748b;">Type: ${fac.type}</div>
                ${fac.beds > 0 ? `<div style="font-size:12px; color:#10b981; font-weight:bold; margin-top:4px;">Available Capacity: ${fac.beds}</div>` : ''}
            </div>
        `);
        facilityMarkers.push(marker);
    });
}

function toggleFacilityMarkers() {
    showFacilities = !showFacilities;
    renderFacilityMarkers();
}

function updateAlertMarkersOnMap(alerts) {
    if (!map) return;
    Object.values(alertMarkers).forEach(m => map.removeLayer(m));
    alertMarkers = {};

    alerts.forEach(a => {
        if (a.status === 'Resolved') return;

        const isCritical = a.severity === 'Critical';
        const markerHtml = `
            <div style="position:relative; display:grid; place-items:center;">
                <div class="${isCritical ? 'radar-pulse-marker' : ''}" style="width:22px; height:22px; background:${isCritical ? '#ef4444' : '#f59e0b'}; border:2px solid white; border-radius:50%; display:grid; place-items:center; color:white; font-size:11px; font-weight:bold;">
                    !
                </div>
            </div>
        `;

        const icon = L.divIcon({
            html: markerHtml,
            className: '',
            iconSize: [22, 22],
            iconAnchor: [11, 11]
        });

        const marker = L.marker([parseFloat(a.latitude), parseFloat(a.longitude)], { icon }).addTo(map);
        marker.bindPopup(`
            <div style="font-family:sans-serif; min-width:200px;">
                <span style="background:${isCritical ? '#ef4444' : '#f59e0b'}; color:white; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold;">${a.severity}</span>
                <h4 style="margin:6px 0 2px; color:#0f172a;">${a.victim_name} (${a.emergency_type})</h4>
                <p style="font-size:12px; color:#475569; margin:4px 0;">${a.message || 'Immediate help needed'}</p>
                <div style="font-size:11px; color:#64748b;">Phone: ${a.phone}</div>
                <button onclick="openDispatchModal('${a.id}')" style="margin-top:8px; width:100%; background:#ef4444; color:white; border:none; padding:6px; border-radius:4px; font-weight:bold; cursor:pointer;">Dispatch Team →</button>
            </div>
        `);
        alertMarkers[a.id] = marker;
    });
}
