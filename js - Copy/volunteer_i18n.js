/**
 * DisasterSafe Volunteer Portal - Multi-Language Translation Engine
 * Supported Languages: English (en), Hindi (hi), Bengali (bn), Tamil (ta), Telugu (te), Marathi (mr)
 */

// Function to protect all icons and code elements from being translated
function protectIconsAndCode() {
  document.querySelectorAll('.material-symbols-outlined, code, pre, svg, .mono, #languageSelect, .fa-solid, .fa-regular, .fa-brands').forEach(el => {
    el.classList.add('notranslate');
    el.setAttribute('translate', 'no');
  });
}

// Continuously protect any new dynamic icons
const iconObserver = new MutationObserver(() => {
  protectIconsAndCode();
  cleanupGoogleWidgets();
});

function cleanupGoogleWidgets() {
  // Remove or hide any rogue Google translation popups/tooltips
  const badSelectors = [
    '.goog-te-banner-frame',
    '.goog-te-balloon-frame',
    '#goog-gt-tt',
    '.goog-tooltip',
    '.VIpgJd-yAWNEb-VIpgJd-fmcmS-sn54Q',
    '.VIpgJd-ZVi9C-bHOHAd',
    '.VIpgJd-yAWNEb-L7lbkb',
    '.VIpgJd-ZVi9C-aZ2wEe'
  ];
  badSelectors.forEach(sel => {
    document.querySelectorAll(sel).forEach(el => {
      el.style.display = 'none';
      el.style.visibility = 'hidden';
      el.style.opacity = '0';
      el.style.pointerEvents = 'none';
    });
  });

  if (document.body.style.top && document.body.style.top !== '0px') {
    document.body.style.top = '0px';
  }
}

// Google Translate Initialization
function googleTranslateElementInit() {
  if (window.google && google.translate) {
    new google.translate.TranslateElement({
      pageLanguage: 'en',
      includedLanguages: 'en,hi,bn,ta,te,mr',
      autoDisplay: false,
      layout: google.translate.TranslateElement.InlineLayout.SIMPLE
    }, 'google_translate_element');
  }
  protectIconsAndCode();
}

/**
 * Handle user changing the language dropdown
 */
function changeLanguage(langCode) {
  if (!langCode) return;
  localStorage.setItem('volunteer_lang', langCode);

  protectIconsAndCode();

  // Set Google Translate Cookie
  const cookieVal = langCode === 'en' ? '' : `/en/${langCode}`;
  document.cookie = `googtrans=${cookieVal}; path=/;`;
  document.cookie = `googtrans=${cookieVal}; domain=${window.location.hostname}; path=/;`;
  document.cookie = `googtrans=${cookieVal}; domain=.${window.location.hostname}; path=/;`;

  // Trigger Google Translate select
  const selectCombo = document.querySelector('.goog-te-combo');
  if (selectCombo) {
    selectCombo.value = langCode;
    selectCombo.dispatchEvent(new Event('change'));
  } else {
    window.location.reload();
  }

  setTimeout(() => {
    protectIconsAndCode();
    cleanupGoogleWidgets();
  }, 200);
}

// Run protection as early as possible
document.addEventListener('DOMContentLoaded', () => {
  protectIconsAndCode();
  cleanupGoogleWidgets();
  iconObserver.observe(document.body, { childList: true, subtree: true });

  const savedLang = localStorage.getItem('volunteer_lang') || 'en';
  const select = document.getElementById('languageSelect');
  if (select && select.value !== savedLang) {
    select.value = savedLang;
  }
});

// Periodic check to ensure icons stay intact
setInterval(() => {
  protectIconsAndCode();
  cleanupGoogleWidgets();
}, 600);
