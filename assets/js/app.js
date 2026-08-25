// assets/js/app.js - Main Application Controller & CAD State Engine for Fire & Rescue

const FireApp = {
    state: {
        activeTab: 'cad_board',
        selectedIncidentId: null,
        data: {
            stations: [],
            incidents: [],
            vehicles: [],
            firefighters: [],
            hydrants: [],
            dispatches: []
        },
        autoPollTimer: null
    },

    init: function() {
        this.bindEvents();
        this.loadData(() => {
            // If incidents exist, select first active incident
            const active = this.state.data.incidents.find(i => i.status === 'Active') || this.state.data.incidents[0];
            if (active) {
                this.selectIncident(active.id);
            }
        });

        // Initialize Tactical Map in background
        TacticalMap.init('tacticalLeafletMap');

        // Auto Poll Data every 8 seconds
        this.state.autoPollTimer = setInterval(() => {
            this.loadData();
        }, 8000);

        // Start Live Clock Ticker
        setInterval(() => this.updateClock(), 1000);
    },

    updateClock: function() {
        const el = document.getElementById('cadLiveClock');
        if (el) {
            const now = new Date();
            el.textContent = now.toTimeString().split(' ')[0] + ' UTC' + (now.getTimezoneOffset() <= 0 ? '+' : '-') + Math.abs(now.getTimezoneOffset() / 60);
        }
    },

    bindEvents: function() {
        // Tab Buttons
        document.querySelectorAll('.tab-nav-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = btn.dataset.tab;
                this.switchTab(target);
            });
        });

        // GPS Auto-detect Button
        const geoBtn = document.getElementById('btnGeoDetect');
        if (geoBtn) {
            geoBtn.addEventListener('click', () => this.detectUserLocation());
        }

        // Intake Form Submission
        const intakeForm = document.getElementById('fireIntakeForm');
        if (intakeForm) {
            intakeForm.addEventListener('submit', (e) => this.handleIntakeSubmit(e));
        }
    },

    switchTab: function(tabName) {
        this.state.activeTab = tabName;

        document.querySelectorAll('.tab-nav-btn').forEach(btn => {
            if (btn.dataset.tab === tabName) {
                btn.classList.add('bg-rose-950/70', 'text-rose-400', 'border-rose-700/80', 'shadow-xs');
                btn.classList.remove('text-slate-400', 'hover:text-slate-200', 'border-transparent');
            } else {
                btn.classList.remove('bg-rose-950/70', 'text-rose-400', 'border-rose-700/80', 'shadow-xs');
                btn.classList.add('text-slate-400', 'hover:text-slate-200', 'border-transparent');
            }
        });

        document.querySelectorAll('.cad-tab-view').forEach(view => {
            if (view.id === 'view_' + tabName) {
                view.classList.remove('hidden');
            } else {
                view.classList.add('hidden');
            }
        });

        if (tabName === 'gis_radar') {
            TacticalMap.invalidateSize();
        }
    },

    loadData: function(callback = null) {
        fetch('api/fire_cad_api.php?action=get_all_data')
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.state.data = res;
                    this.renderUI();
                    TacticalMap.plotAll(res);
                    if (callback) callback();
                }
            })
            .catch(err => {
                console.error("CAD Data Fetch Error:", err);
            });
    },

    renderUI: function() {
        this.renderMetrics();
        this.renderIncidentsList();
        this.renderSelectedIncident();
        this.renderApparatus();
        this.renderFirefighters();
        this.renderHydrants();
    },

    renderMetrics: function() {
        const m = this.state.data.metrics || {};
        const elActive = document.getElementById('metricActiveIncidents');
        const elRolling = document.getElementById('metricUnitsRolling');
        const elStaff = document.getElementById('metricActiveFirefighters');
        const elHydrants = document.getElementById('metricHydrantsReady');

        if (elActive) elActive.textContent = m.active_incidents || '0';
        if (elRolling) elRolling.textContent = m.units_rolling || '0';
        if (elStaff) elStaff.textContent = m.active_firefighters || '0';
        if (elHydrants) elHydrants.textContent = m.hydrants_ready || '0';
    },

    renderIncidentsList: function() {
        const listEl = document.getElementById('cadIncidentsList');
        if (!listEl) return;

        const incidents = this.state.data.incidents || [];
        if (incidents.length === 0) {
            listEl.innerHTML = `
                <div class="p-8 text-center text-slate-500">
                    <i class="fa-solid fa-fire-extinguisher text-2xl mb-2 text-slate-600"></i>
                    <p class="text-xs font-mono">No active distress fire alarms reported.</p>
                </div>
            `;
            return;
        }

        const stagesMap = [
            '', 'Paged', 'Rolling', 'En Route', 'On Scene', 'Knockdown'
        ];

        listEl.innerHTML = incidents.map(inc => {
            const isSelected = this.state.selectedIncidentId === inc.id;
            const isResolved = inc.status === 'Resolved';
            const stageText = stagesMap[inc.stage_index] || 'Stage ' + inc.stage_index;

            return `
                <div onclick="FireApp.selectIncident(${inc.id})" 
                     class="p-3.5 rounded-2xl border transition-all cursor-pointer ${isSelected ? 'bg-rose-950/40 border-rose-600/80 shadow-md shadow-rose-950/50' : 'bg-slate-900/60 border-slate-800/80 hover:border-slate-700'} space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono ${isResolved ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-rose-950 text-rose-300 border border-rose-800'}">
                            ${inc.incident_number}
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono ${isResolved ? 'bg-emerald-900/40 text-emerald-400 border border-emerald-700/50' : 'bg-amber-900/40 text-amber-400 border border-amber-700/50'}">
                            <i class="fa-solid ${isResolved ? 'fa-check' : 'fa-spinner fa-spin'} mr-1 text-[9px]"></i>${stageText} (${inc.stage_index}/5)
                        </span>
                    </div>

                    <div class="min-w-0">
                        <h4 class="font-extrabold text-sm text-slate-100 truncate">${inc.fire_type}</h4>
                        <p class="text-xs text-slate-400 truncate flex items-center gap-1 mt-0.5">
                            <i class="fa-solid fa-location-dot text-rose-500 text-[10px]"></i>
                            <span>${inc.address}</span>
                        </p>
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-slate-500 font-mono pt-1 border-t border-slate-800/60">
                        <span>Caller: ${inc.caller_name.split(' ')[0]}</span>
                        <span class="text-slate-400">${new Date(inc.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                    </div>
                </div>
            `;
        }).join('');
    },

    selectIncident: function(incidentId) {
        this.state.selectedIncidentId = incidentId;
        this.renderIncidentsList();
        this.renderSelectedIncident();

        const inc = this.state.data.incidents.find(i => i.id === incidentId);
        if (inc && inc.lat && inc.lng) {
            TacticalMap.focus(inc.lat, inc.lng, 15);
        }
    },

    renderSelectedIncident: function() {
        const detailEl = document.getElementById('cadIncidentDetailPane');
        if (!detailEl) return;

        const inc = this.state.data.incidents.find(i => i.id === this.state.selectedIncidentId);
        if (!inc) {
            detailEl.innerHTML = `
                <div class="p-12 text-center text-slate-500 space-y-2">
                    <i class="fa-solid fa-tower-broadcast text-3xl text-slate-600"></i>
                    <p class="text-xs font-mono">Select an incident from the queue to open live 5-Stage CAD controls.</p>
                </div>
            `;
            return;
        }

        const stage = inc.stage_index || 1;
        const isResolved = inc.status === 'Resolved';

        const stagesList = [
            { idx: 1, name: 'Alarm Paged & Geocoded', desc: 'Distress call geocoded & broadcasted on VHF' },
            { idx: 2, name: 'Primary Units Rolling', desc: 'Assigned engine wheels rolling out of station bay' },
            { idx: 3, name: 'En Route', desc: 'Active high-speed code-3 sirens en route to ground zero' },
            { idx: 4, name: 'On Scene & Water Connected', desc: 'Engine coupled to municipal hydrant, attack lines charged' },
            { idx: 5, name: 'Under Control / Fire Knockdown', desc: 'Thermal overhaul complete, perimeter declared cold & safe' }
        ];

        detailEl.innerHTML = `
            <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-5 sm:p-6 space-y-6">
                
                <!-- Title & Meta Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-4">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <span class="px-2.5 py-0.5 rounded-lg text-xs font-bold font-mono bg-rose-950 text-rose-300 border border-rose-800">
                                ${inc.incident_number}
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono ${isResolved ? 'bg-emerald-900/40 text-emerald-400 border border-emerald-700/50' : 'bg-amber-900/40 text-amber-400 border border-amber-700/50'}">
                                ${isResolved ? 'RESOLVED / ALL CLEAR' : 'ACTIVE EMERGENCY'}
                            </span>
                        </div>
                        <h3 class="text-xl font-extrabold text-white mt-1">${inc.fire_type}</h3>
                        <p class="text-xs text-slate-400 flex items-center gap-1.5 mt-0.5">
                            <i class="fa-solid fa-location-dot text-rose-500"></i>
                            <span>${inc.address}</span>
                            <span class="font-mono text-slate-500">(GPS: ${Number(inc.lat).toFixed(4)}, ${Number(inc.lng).toFixed(4)})</span>
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button onclick="TacticalMap.focus(${inc.lat}, ${inc.lng}, 16); FireApp.switchTab('gis_radar');" 
                                class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-sky-400 text-xs font-bold transition-all flex items-center gap-1.5 shadow-2xs">
                            <i class="fa-solid fa-crosshairs"></i> Locate on Radar
                        </button>
                    </div>
                </div>

                <!-- 5-Stage Live CAD Stepper Pipeline -->
                <div class="space-y-3 bg-slate-950/80 p-4 rounded-2xl border border-slate-800/80">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-rose-400 font-mono flex items-center gap-2">
                            <i class="fa-solid fa-stairs"></i> 5-Stage Live CAD Dispatch Pipeline
                        </span>
                        <span class="text-xs font-mono font-bold text-slate-400">Current: Stage ${stage} of 5</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-5 gap-2.5 pt-1">
                        ${stagesList.map(stg => {
                            const isDone = stage > stg.idx;
                            const isCurrent = stage === stg.idx;
                            const isPending = stage < stg.idx;

                            let cardStyle = 'bg-slate-900/60 border-slate-800 text-slate-500';
                            let iconBg = 'bg-slate-800 text-slate-400';
                            let icon = `<i class="fa-solid fa-lock text-[10px]"></i>`;

                            if (isCurrent) {
                                cardStyle = 'bg-rose-950/40 border-rose-600 text-white shadow-lg shadow-rose-950/60 ring-1 ring-rose-500';
                                iconBg = 'bg-rose-600 text-white animate-pulse';
                                icon = `<i class="fa-solid fa-spinner fa-spin text-xs"></i>`;
                            } else if (isDone) {
                                cardStyle = 'bg-emerald-950/30 border-emerald-800 text-emerald-300';
                                iconBg = 'bg-emerald-600 text-white';
                                icon = `<i class="fa-solid fa-check text-xs"></i>`;
                            }

                            return `
                                <div class="p-3 rounded-2xl border transition-all ${cardStyle} flex flex-col justify-between space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-[10px] ${iconBg}">
                                            ${icon}
                                        </span>
                                        <span class="text-[10px] font-mono font-bold text-slate-400">Step ${stg.idx}</span>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold leading-tight ${isCurrent ? 'text-white' : (isDone ? 'text-emerald-300' : 'text-slate-400')}">${stg.name}</p>
                                        <p class="text-[10px] text-slate-500 leading-tight mt-1 line-clamp-2">${stg.desc}</p>
                                    </div>
                                    ${(!isDone && !isResolved) ? `
                                        <button onclick="FireApp.advanceStage(${inc.id}, ${stg.idx})" class="w-full py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-wider transition-all ${isCurrent ? 'bg-rose-600 hover:bg-rose-500 text-white shadow-xs' : 'bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700'}">
                                            ${isCurrent ? 'Advance &rarr;' : 'Jump Stage'}
                                        </button>
                                    ` : `
                                        <div class="text-[10px] font-bold font-mono text-emerald-400 text-center py-1">
                                            ${isDone ? 'COMPLETED' : 'RESOLVED'}
                                        </div>
                                    `}
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>

                <!-- Tactical Details Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                    <!-- Assigned Apparatus -->
                    <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800 space-y-1.5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 font-mono">Assigned Apparatus</p>
                        <h5 class="text-sm font-extrabold text-amber-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-truck-droplet"></i>
                            <span>${inc.assigned_vehicle_name || 'Engine 41 (Type-1 Pumper)'}</span>
                        </h5>
                        <p class="text-slate-400 text-[11px]">Primary Station: Delhi Central HQ-01</p>
                    </div>

                    <!-- Water Supply & Hydrant -->
                    <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800 space-y-1.5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 font-mono">Coupled Municipal Hydrant</p>
                        <h5 class="text-sm font-extrabold text-sky-400 flex items-center gap-1.5">
                            <i class="fa-solid fa-faucet"></i>
                            <span>${inc.assigned_hydrant_code || 'HYD-DEL-101'}</span>
                        </h5>
                        <p class="text-slate-400 text-[11px] font-mono">${inc.assigned_hydrant_psi || '82'} PSI • ${inc.assigned_hydrant_gpm || '1400'} GPM Flow</p>
                    </div>

                    <!-- Caller & Life Safety -->
                    <div class="p-3.5 rounded-2xl bg-slate-950/60 border border-slate-800 space-y-1.5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 font-mono">Caller & Occupants</p>
                        <h5 class="text-sm font-extrabold text-slate-200">${inc.caller_name} (${inc.caller_phone})</h5>
                        <p class="text-[11px] ${inc.trapped_count > 0 ? 'text-rose-400 font-bold' : 'text-slate-400'}">
                            ${inc.trapped_count > 0 ? `${inc.trapped_count} Occupants Reported Trapped` : 'No confirmed entrapments'}
                        </p>
                    </div>
                </div>

                <!-- Hazardous Notes -->
                <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 text-xs">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 font-mono mb-1">Hazardous Notes & Tactics</p>
                    <p class="text-slate-300 leading-relaxed">${inc.notes || 'Standard structural firefighting protocol in effect. SCBA gear mandatory.'}</p>
                </div>

            </div>
        `;
    },

    advanceStage: function(incidentId, targetStage) {
        const formData = new FormData();
        formData.append('action', 'advance_stage');
        formData.append('incident_id', incidentId);
        formData.append('target_stage', targetStage);

        fetch('api/fire_cad_api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                this.showToast(res.message, 'success');
                this.loadData();
            } else {
                this.showToast(res.message || 'Failed to update CAD stage', 'error');
            }
        })
        .catch(err => {
            this.showToast('Network error updating CAD stage', 'error');
        });
    },

    detectUserLocation: function() {
        if (!navigator.geolocation) {
            this.showToast('Geolocation is not supported by your browser', 'error');
            return;
        }

        const btn = document.getElementById('btnGeoDetect');
        if (btn) btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i> Detecting GPS...`;

        navigator.geolocation.getCurrentPosition(
            pos => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;

                const latInput = document.getElementById('intake_lat');
                const lngInput = document.getElementById('intake_lng');
                const addrInput = document.getElementById('intake_address');

                if (latInput) latInput.value = lat.toFixed(6);
                if (lngInput) lngInput.value = lng.toFixed(6);
                if (addrInput && !addrInput.value) {
                    addrInput.value = `Near GPS Lat: ${lat.toFixed(4)}, Lng: ${lng.toFixed(4)}`;
                }

                if (btn) btn.innerHTML = `<i class="fa-solid fa-check text-emerald-400 mr-1"></i> GPS Locked`;
                this.showToast(`GPS Position Locked: ${lat.toFixed(4)} N, ${lng.toFixed(4)} E`, 'success');
            },
            err => {
                if (btn) btn.innerHTML = `<i class="fa-solid fa-location-crosshairs mr-1"></i> Auto-Detect GPS`;
                this.showToast('Could not acquire GPS position. Using default coordinates.', 'warning');
            }
        );
    },

    handleIntakeSubmit: function(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        formData.append('action', 'create_incident');

        fetch('api/fire_cad_api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                this.showToast(`${res.incident_number} Dispatched! ${res.message}`, 'success');
                
                // Cross-window postMessage for parent dashboard embeds
                try {
                    window.parent.postMessage({
                        type: 'FIRE_INCIDENT_REPORTED',
                        incident: {
                            id: res.incident_id,
                            incident_number: res.incident_number,
                            fire_type: formData.get('fire_type'),
                            address: formData.get('address'),
                            lat: formData.get('lat'),
                            lng: formData.get('lng'),
                            timestamp: new Date().toISOString()
                        }
                    }, '*');
                } catch (pe) {}

                form.reset();
                this.loadData(() => {
                    this.selectIncident(res.incident_id);
                    this.switchTab('cad_board');
                });
            } else {
                this.showToast(res.message || 'Error logging fire incident.', 'error');
            }
        })
        .catch(err => {
            this.showToast('Network error while dispatching distress alarm.', 'error');
        });
    },

    renderApparatus: function() {
        const container = document.getElementById('apparatusCardsGrid');
        if (!container) return;

        const vehicles = this.state.data.vehicles || [];
        container.innerHTML = vehicles.map(v => {
            const isService = v.status === 'In Service';
            const isEnRoute = v.status === 'En Route';

            return `
                <div class="p-5 rounded-3xl bg-slate-900/80 border border-slate-800 hover:border-slate-700 transition-all flex flex-col justify-between space-y-4 shadow-sm">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-amber-950/60 text-amber-400 border border-amber-800/80 flex items-center justify-center text-lg shrink-0">
                                    <i class="fa-solid fa-truck-droplet"></i>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-100 text-sm">${v.unit_name}</h4>
                                    <p class="text-[11px] text-slate-400 font-mono">${v.type} &bull; ${v.station_name || 'Central HQ'}</p>
                                </div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold font-mono ${isService ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : (isEnRoute ? 'bg-amber-950 text-amber-300 border border-amber-800 animate-pulse' : 'bg-slate-800 text-slate-300 border border-slate-700')}">
                                ${v.status}
                            </span>
                        </div>

                        <div class="space-y-2 pt-3 text-xs">
                            <!-- Water Tank Gauge -->
                            <div>
                                <div class="flex justify-between text-[10px] font-mono text-slate-400 mb-1">
                                    <span>Water Reserve</span>
                                    <span class="text-sky-400 font-bold">${v.current_water_gal} / ${v.water_capacity_gal} Gal</span>
                                </div>
                                <div class="w-full h-2 bg-slate-950 rounded-full overflow-hidden border border-slate-800">
                                    <div class="h-full bg-sky-500 rounded-full transition-all" style="width: ${(v.current_water_gal / v.water_capacity_gal) * 100}%"></div>
                                </div>
                            </div>

                            <!-- Foam Reserve -->
                            <div>
                                <div class="flex justify-between text-[10px] font-mono text-slate-400 mb-1">
                                    <span>AFFF Foam Inductor</span>
                                    <span class="text-amber-400 font-bold">${v.current_foam_gal} / ${v.foam_capacity_gal} Gal</span>
                                </div>
                                <div class="w-full h-2 bg-slate-950 rounded-full overflow-hidden border border-slate-800">
                                    <div class="h-full bg-amber-500 rounded-full transition-all" style="width: ${(v.current_foam_gal / v.foam_capacity_gal) * 100}%"></div>
                                </div>
                            </div>

                            <p class="text-[11px] text-slate-400 pt-1">
                                Crew: <strong class="text-white">${v.crew_count} Firefighters</strong> | Commander: <span class="text-slate-300">${v.commander_name || 'Capt. Marcus Vance'}</span>
                            </p>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex items-center justify-between gap-2">
                        <button onclick="FireApp.refillApparatus(${v.id})" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-colors">
                            <i class="fa-solid fa-gas-pump mr-1"></i> Refill Tank
                        </button>
                        <a href="tel:101" class="px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition-all shadow-xs flex items-center gap-1">
                            <i class="fa-solid fa-phone text-[10px]"></i> Radio Call
                        </a>
                    </div>
                </div>
            `;
        }).join('');
    },

    renderFirefighters: function() {
        const tableBody = document.getElementById('firefightersTableBody');
        if (!tableBody) return;

        const ffList = this.state.data.firefighters || [];
        tableBody.innerHTML = ffList.map(ff => `
            <tr class="hover:bg-slate-800/40 border-b border-slate-800/60 transition-colors">
                <td class="py-3 px-4 font-bold text-white">${ff.name}</td>
                <td class="py-3 px-4 font-mono text-slate-400">${ff.badge_number}</td>
                <td class="py-3 px-4 text-sky-400 font-semibold">${ff.rank}</td>
                <td class="py-3 px-4 font-mono text-slate-400">${ff.phone || '+91 98110 44550'}</td>
                <td class="py-3 px-4">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold font-mono ${ff.status === 'On Duty' ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-slate-800 text-slate-400'}">
                        ${ff.status}
                    </span>
                </td>
                <td class="py-3 px-4 text-slate-400 text-[11px] truncate max-w-xs">${ff.certifications || 'Structural Firefighting Level 2'}</td>
            </tr>
        `).join('');
    },

    renderHydrants: function() {
        const grid = document.getElementById('hydrantsListGrid');
        if (!grid) return;

        const hydrants = this.state.data.hydrants || [];
        grid.innerHTML = hydrants.map(h => `
            <div class="p-3.5 rounded-2xl bg-slate-900/60 border border-slate-800 flex items-center justify-between space-y-1">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-xl bg-blue-950/70 border border-blue-800/80 text-blue-400 flex items-center justify-center text-xs">
                            <i class="fa-solid fa-droplet"></i>
                        </span>
                        <h5 class="font-extrabold text-sm text-slate-100 font-mono">${h.hydrant_code}</h5>
                    </div>
                    <p class="text-xs text-slate-400 font-mono mt-1">Pressure: <strong class="text-white">${h.pressure_psi} PSI</strong> | Flow: <strong class="text-sky-300">${h.flow_gpm} GPM</strong></p>
                </div>
                <button onclick="TacticalMap.focus(${h.lat}, ${h.lng}, 17); FireApp.switchTab('gis_radar');" class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-sky-400 border border-slate-700" title="Locate on Map">
                    <i class="fa-solid fa-crosshairs text-xs"></i>
                </button>
            </div>
        `).join('');
    },

    refillApparatus: function(vehicleId) {
        const formData = new FormData();
        formData.append('action', 'refill_apparatus');
        formData.append('vehicle_id', vehicleId);
        formData.append('water_gal', 750);
        formData.append('foam_gal', 50);

        fetch('api/fire_cad_api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                this.showToast(res.message, 'success');
                this.loadData();
            }
        });
    },

    showToast: function(message, type = 'info') {
        if (window.Swal) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true,
                background: '#0f172a',
                color: '#f8fafc'
            });
            Toast.fire({
                icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'),
                title: message
            });
        } else {
            alert(message);
        }
    }
};

window.FireApp = FireApp;

document.addEventListener('DOMContentLoaded', () => {
    FireApp.init();
});
