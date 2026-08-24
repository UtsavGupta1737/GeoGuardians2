<?php
/**
 * Volunteer Disaster Relief Grid
 * GeoGuardians - DisasterSafe
 */
require_once __DIR__ . '/header.php';
?>

<div style="max-width:1100px; margin:2rem auto; padding:0 1rem;">
    <div style="background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--radius-lg); padding:2rem; margin-bottom:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
                <h2>Volunteer Relief Coordination Grid</h2>
                <p style="color:var(--text-muted);">Coordinate relief supply logistics, shelter volunteer shifts, and ground citizen welfare.</p>
            </div>
            <a href="dashboard.php" class="btn btn-primary">Open Live Command Map →</a>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.25rem;">
        <!-- Card 1: Relief Supplies -->
        <div style="background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:1.5rem;">
            <h3>📦 Relief Supply Distribution</h3>
            <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1rem;">Sector 4 Community Shelter Depot</p>
            <ul style="list-style:none; display:flex; flex-direction:column; gap:10px; font-size:0.9rem;">
                <li style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border-color); padding-bottom:6px;">
                    <span>🍞 Ready-to-Eat Food Packets</span>
                    <strong style="color:#10b981;">400 Dispatched</strong>
                </li>
                <li style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border-color); padding-bottom:6px;">
                    <span>💧 Potable Mineral Water (1L)</span>
                    <strong style="color:#10b981;">600 Stored</strong>
                </li>
                <li style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border-color); padding-bottom:6px;">
                    <span>🩹 Trauma First-Aid Kits</span>
                    <strong style="color:#f59e0b;">50 In Transit</strong>
                </li>
                <li style="display:flex; justify-content:space-between; padding-top:4px;">
                    <span>🧥 Thermal Blankets</span>
                    <strong style="color:#38bdf8;">150 Ready</strong>
                </li>
            </ul>
        </div>

        <!-- Card 2: Shelter Occupancy -->
        <div style="background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:1.5rem;">
            <h3>🏥 Evacuation Camp Status</h3>
            <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1rem;">Govt. Stadium Evacuation Camp</p>
            <div style="font-size:1.8rem; font-weight:800; color:#38bdf8; font-family:var(--font-mono); margin-bottom:6px;">420 / 500</div>
            <div style="width:100%; height:8px; background:rgba(255,255,255,0.1); border-radius:4px; overflow:hidden;">
                <div style="width:84%; height:100%; background:var(--warning);"></div>
            </div>
            <p style="color:var(--text-muted); font-size:0.8rem; margin-top:8px;">Occupancy at 84% • 3 volunteer medics currently on site.</p>
        </div>

        <!-- Card 3: Volunteer Task List -->
        <div style="background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:1.5rem;">
            <h3>🤝 Volunteer Tasks Today</h3>
            <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1rem;">Assigned Missions</p>
            <div style="display:flex; flex-direction:column; gap:8px; font-size:0.85rem;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" checked style="accent-color:var(--primary);"> Verify victim count at Sector 4 shelter
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" checked style="accent-color:var(--primary);"> Distribute water bottles to flooded areas
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" style="accent-color:var(--primary);"> Assist NDRF Team Alpha with stretcher transport
                </label>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
