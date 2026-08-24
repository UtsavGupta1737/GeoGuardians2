// assets/js/sound.js - 100% Silent Sound Interface (Strict No-Audio Policy)
// Disables all synthesized beeps, siren alarms, and audio chirps. All events trigger visual notifications instead.

const FireAudio = {
    muted: true,
    
    playAlert: function(type = 'alarm') {
        // Strict No-Op adhering to 100% silent audio policy
        return;
    },

    playDispatch: function() {
        return;
    },

    playRadioClick: function() {
        return;
    },

    playAllClear: function() {
        return;
    }
};

window.FireAudio = FireAudio;
