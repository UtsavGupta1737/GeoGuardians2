// assets/js/map.js - Leaflet.js Tactical Response GIS Map Module for Fire & Rescue CAD

let tacticalMap = null;
let tileLayer = null;
let stationLayer = null;
let incidentLayer = null;
let hydrantLayer = null;
let apparatusLayer = null;

const TacticalMap = {
    init: function(elementId = 'tacticalLeafletMap', initialLat = 28.6315, initialLng = 77.2167) {
        if (tacticalMap) {
            tacticalMap.remove();
        }

        const mapEl = document.getElementById(elementId);
        if (!mapEl) return;

        tacticalMap = L.map(elementId, {
            center: [initialLat, initialLng],
            zoom: 13,
            zoomControl: false
        });

        L.control.zoom({ position: 'bottomright' }).addTo(tacticalMap);

        // CartoDB Dark Tactical Tile Layer Default
        tileLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; CartoDB &copy; OpenStreetMap contributors',
            maxZoom: 19,
            subdomains: 'abcd'
        }).addTo(tacticalMap);

        stationLayer = L.layerGroup().addTo(tacticalMap);
        incidentLayer = L.layerGroup().addTo(tacticalMap);
        hydrantLayer = L.layerGroup().addTo(tacticalMap);
        apparatusLayer = L.layerGroup().addTo(tacticalMap);

        // Try getting real user geolocation if available
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                pos => {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    L.circleMarker([lat, lng], {
                        radius: 8,
                        color: '#38bdf8',
                        fillColor: '#0284c7',
                        fillOpacity: 0.8
                    }).bindPopup("<b>Your Current Position</b><br>GPS Geocoded").addTo(tacticalMap);
                },
                err => { /* fallback gracefully to default coordinates */ }
            );
        }
    },

    setTileStyle: function(style = 'dark') {
        if (!tacticalMap) return;
        if (tileLayer) tacticalMap.removeLayer(tileLayer);

        if (style === 'dark') {
            tileLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 19 }).addTo(tacticalMap);
        } else if (style === 'street') {
            tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(tacticalMap);
        } else if (style === 'satellite') {
            tileLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19 }).addTo(tacticalMap);
        }
    },

    plotAll: function(data) {
        if (!tacticalMap) return;

        // 1. PLOT STATIONS
        stationLayer.clearLayers();
        if (Array.isArray(data.stations)) {
            data.stations.forEach(stn => {
                const icon = L.divIcon({
                    className: 'custom-station-icon',
                    html: `<div class="station-hq-marker"><i class="fa-solid fa-shield-halved"></i></div>`,
                    iconSize: [34, 34],
                    iconAnchor: [17, 17]
                });

                const m = L.marker([stn.lat, stn.lng], { icon })
                    .bindPopup(`
                        <div class="p-1 space-y-1 text-xs">
                            <div class="flex items-center gap-1.5 text-sky-400 font-bold">
                                <i class="fa-solid fa-building-shield"></i>
                                <span>${stn.name}</span>
                            </div>
                            <p class="text-slate-300">${stn.address}</p>
                            <p class="font-mono text-slate-400">Code: <strong class="text-white">${stn.code}</strong> | Phone: ${stn.phone}</p>
                            <p class="text-[10px] text-emerald-400 font-bold">Status: ${stn.active_status}</p>
                        </div>
                    `);
                stationLayer.addLayer(m);

                // 2000m Coverage Perimeter Circle
                const circle = L.circle([stn.lat, stn.lng], {
                    radius: (stn.coverage_radius_km || 2.0) * 1000,
                    color: '#38bdf8',
                    weight: 1,
                    fillColor: '#0284c7',
                    fillOpacity: 0.08,
                    dashArray: '4, 4'
                });
                stationLayer.addLayer(circle);
            });
        }

        // 2. PLOT HYDRANTS
        hydrantLayer.clearLayers();
        if (Array.isArray(data.hydrants)) {
            data.hydrants.forEach(hyd => {
                const icon = L.divIcon({
                    className: 'custom-hydrant-icon',
                    html: `<div class="hydrant-pin-marker"><i class="fa-solid fa-droplet"></i></div>`,
                    iconSize: [26, 26],
                    iconAnchor: [13, 13]
                });

                const m = L.marker([hyd.lat, hyd.lng], { icon })
                    .bindPopup(`
                        <div class="p-1 space-y-1 text-xs">
                            <div class="flex items-center gap-1.5 text-blue-400 font-bold">
                                <i class="fa-solid fa-faucet"></i>
                                <span>Municipal Hydrant: ${hyd.hydrant_code}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 pt-1 font-mono text-[11px]">
                                <div class="bg-slate-800 p-1.5 rounded-lg border border-slate-700">
                                    <p class="text-slate-400 text-[9px] uppercase">Pressure</p>
                                    <p class="text-white font-bold">${hyd.pressure_psi} PSI</p>
                                </div>
                                <div class="bg-slate-800 p-1.5 rounded-lg border border-slate-700">
                                    <p class="text-slate-400 text-[9px] uppercase">Flow Rate</p>
                                    <p class="text-sky-300 font-bold">${hyd.flow_gpm} GPM</p>
                                </div>
                            </div>
                            <p class="text-[10px] text-emerald-400 font-bold mt-1">Status: ${hyd.status}</p>
                        </div>
                    `);
                hydrantLayer.addLayer(m);
            });
        }

        // 3. PLOT INCIDENTS
        incidentLayer.clearLayers();
        if (Array.isArray(data.incidents)) {
            data.incidents.forEach(inc => {
                if (inc.status === 'Resolved') return;

                const icon = L.divIcon({
                    className: 'custom-fire-icon',
                    html: `<div class="fire-pulse-marker"><i class="fa-solid fa-fire"></i></div>`,
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                });

                const m = L.marker([inc.lat, inc.lng], { icon })
                    .bindPopup(`
                        <div class="p-1 space-y-1.5 text-xs">
                            <div class="flex items-center justify-between gap-2 border-b border-slate-700 pb-1">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-950 text-rose-300 border border-rose-800 mono">
                                    ${inc.incident_number}
                                </span>
                                <span class="text-rose-400 font-bold">Stage ${inc.stage_index}/5</span>
                            </div>
                            <p class="font-extrabold text-white text-sm">${inc.fire_type}</p>
                            <p class="text-slate-300 text-[11px]"><i class="fa-solid fa-location-dot text-rose-500 mr-1"></i>${inc.address}</p>
                            ${inc.trapped_count > 0 ? `<p class="text-rose-400 font-bold text-[11px] animate-pulse"><i class="fa-solid fa-person-circle-exclamation mr-1"></i>${inc.trapped_count} Occupants Trapped Reported</p>` : ''}
                            <p class="text-slate-400 text-[11px] italic bg-slate-900/90 p-1.5 rounded border border-slate-800">${inc.notes || 'No hazardous notes.'}</p>
                            <button onclick="window.FireApp.selectIncident(${inc.id})" class="w-full mt-1.5 py-1 px-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-bold text-center text-xs">
                                Open in CAD Dispatch Board &rarr;
                            </button>
                        </div>
                    `);
                incidentLayer.addLayer(m);
            });
        }

        // 4. PLOT RESPONDING VEHICLES
        apparatusLayer.clearLayers();
        if (Array.isArray(data.vehicles)) {
            data.vehicles.forEach(veh => {
                if (!veh.lat || !veh.lng) return;

                const icon = L.divIcon({
                    className: 'custom-engine-icon',
                    html: `<div class="apparatus-rolling-marker"><i class="fa-solid fa-truck"></i></div>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                });

                const m = L.marker([veh.lat, veh.lng], { icon })
                    .bindPopup(`
                        <div class="p-1 space-y-1 text-xs">
                            <div class="flex items-center gap-1.5 text-amber-400 font-bold">
                                <i class="fa-solid fa-truck-droplet"></i>
                                <span>${veh.unit_name}</span>
                            </div>
                            <p class="font-mono text-slate-300">Type: ${veh.type} | Status: <strong class="text-amber-300">${veh.status}</strong></p>
                            <p class="text-slate-400">Commander: ${veh.commander_name || 'Captain'}</p>
                            <div class="flex items-center gap-2 pt-1 font-mono text-[10px]">
                                <span class="text-blue-300">Water: ${veh.current_water_gal}/${veh.water_capacity_gal} Gal</span>
                                <span class="text-amber-300">Foam: ${veh.current_foam_gal} Gal</span>
                            </div>
                        </div>
                    `);
                apparatusLayer.addLayer(m);
            });
        }
    },

    focus: function(lat, lng, zoom = 15) {
        if (!tacticalMap) return;
        tacticalMap.setView([lat, lng], zoom, { animate: true, duration: 1 });
    },

    invalidateSize: function() {
        if (tacticalMap) {
            setTimeout(() => tacticalMap.invalidateSize(), 150);
        }
    }
};

window.TacticalMap = TacticalMap;
