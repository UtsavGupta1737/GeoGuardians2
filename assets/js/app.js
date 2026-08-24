/**
 * Core Application Logic & Audio Alert Synthesizer
 * GeoGuardians - DisasterSafe
 */

let audioMuted = false;

// 1. Live Clock
setInterval(() => {
    const el = document.getElementById('liveClock');
    if (el) {
        el.innerText = new Date().toLocaleTimeString();
    }
}, 1000);

// 2. Audio Siren Alert Synthesizer (Web Audio API)
function playSirenBeep() {
    if (audioMuted) return;
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.3);
        gain.gain.setValueAtTime(0.2, ctx.currentTime);
        gain.gain.linearRampToValueAtTime(0.01, ctx.currentTime + 0.3);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.35);
    } catch(e) {}
}

function toggleAudioAlerts() {
    audioMuted = !audioMuted;
    const btn = document.getElementById('btnAudioToggle');
    if (btn) {
        btn.innerText = audioMuted ? '🔇 Muted' : '🔊 Siren';
    }
}

function toggleTheme() {
    document.body.classList.toggle('light-theme');
    const isLight = document.body.classList.contains('light-theme');
    localStorage.setItem('disastersafe_theme', isLight ? 'light' : 'dark');
}

// Restore saved theme on startup
if (localStorage.getItem('disastersafe_theme') === 'light') {
    document.body.classList.add('light-theme');
}

// 3. User Authentication & Logout
function logoutUser() {
    fetch('api/auth.php?action=logout', { method: 'POST' })
        .finally(() => {
            window.location.href = 'login.php';
        });
}
