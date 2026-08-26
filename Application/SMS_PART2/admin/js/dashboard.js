/**
 * Admin Panel Javascript Actions & Map Engine
 */

document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize Map
    initMap();
    
    // 2. Setup Message Detail Panel View listeners
    setupSosActions();
});

/**
 * Initialize Leaflet.js Map with custom dark theme & coordinates
 */
function initMap() {
    const mapElement = document.getElementById('map');
    if (!mapElement) return;

    // Load active markers from globally exported JSON variables from PHP
    const activeAlerts = window.sosAlertsData || [];

    // Initialize Map centered on India centroid
    const map = L.map('map').setView([22.5, 79.5], 5);

    // CartoDB Dark Matter Tiles (Highly premium dark styles matching UI background)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 18
    }).addTo(map);

    // Custom DivIcon marker generator
    const createCustomIcon = (priority) => {
        let color = '#00f0ff'; // Medium priority = Cyan
        if (priority === 'CRITICAL') color = '#ff4c4c'; // Critical = Neon Red
        if (priority === 'HIGH') color = '#ff9900'; // High = Neon Orange
        if (priority === 'LOW') color = '#8b9bb4'; // Low = Muted Grey

        return L.divIcon({
            html: `<div style="
                background-color: ${color}; 
                width: 14px; 
                height: 14px; 
                border: 2px solid #080b11; 
                border-radius: 50%; 
                box-shadow: 0 0 12px ${color}, 0 0 4px ${color};
                animation: pulse 2s infinite ease-in-out;
            "></div>`,
            className: 'custom-map-marker',
            iconSize: [14, 14],
            iconAnchor: [7, 7]
        });
    };

    let markerCount = 0;
    const bounds = [];

    activeAlerts.forEach(alert => {
        if (alert.latitude && alert.longitude) {
            const lat = parseFloat(alert.latitude);
            const lng = parseFloat(alert.longitude);
            bounds.push([lat, lng]);

            const marker = L.marker([lat, lng], {
                icon: createCustomIcon(alert.priority)
            }).addTo(map);

            const popupContent = `
                <div style="
                    font-family: 'Outfit', sans-serif; 
                    color: #f0f4f8; 
                    background: #0f1422; 
                    padding: 8px; 
                    line-height: 1.5;
                    border-radius: 6px;
                ">
                    <strong style="color: #ff4c4c; font-size: 13px; display: block; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 4px; margin-bottom: 4px;">🚨 SOS-${alert.id} (${alert.priority})</strong>
                    <span style="font-size: 11px; text-transform: uppercase; color: #8b9bb4; display:block;">Disaster</span>
                    <strong style="font-size: 12px; text-transform: capitalize; color: #f0f4f8; display:block; margin-bottom: 4px;">${alert.disaster_type}</strong>
                    <span style="font-size: 11px; text-transform: uppercase; color: #8b9bb4; display:block;">Location</span>
                    <strong style="font-size: 12px; color: #f0f4f8; display:block; margin-bottom: 4px;">${alert.location}</strong>
                    <span style="font-size: 12px; display:block; margin-top: 4px;">Trapped: <strong>${alert.people_count}</strong> | Injured: <strong>${alert.injured_count}</strong></span>
                    <a href="sos.php?id=${alert.id}" style="
                        display: inline-block; 
                        margin-top: 8px; 
                        color: #00f0ff; 
                        text-decoration: none; 
                        font-weight: 700; 
                        font-size: 11px;
                    ">RESPOND TO EMERGENCY &gt;</a>
                </div>
            `;

            marker.bindPopup(popupContent);
            markerCount++;
        }
    });

    // Auto fit viewport bounds to active markers
    if (markerCount > 0 && bounds.length > 0) {
        map.fitBounds(bounds, { padding: [60, 60], maxZoom: 10 });
    }
}

/**
 * Handle SOS Alerts page interaction logic
 */
function setupSosActions() {
    const listCards = document.querySelectorAll('.sos-card');
    if (listCards.length === 0) return;

    listCards.forEach(card => {
        card.addEventListener('click', function() {
            const sosId = this.getAttribute('data-id');
            // Navigate to detail view
            window.location.href = 'sos.php?id=' + sosId;
        });
    });
}
