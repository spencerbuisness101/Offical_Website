<?php
/**
 * Internationalization (i18n) System - Spencer's Website v7.0
 * Supports: English (en), Mandarin Chinese (zh), Hindi (hi), Spanish (es), French (fr)
 *
 * Usage: echo t('nav.home');
 * Language detection: ?lang=xx param → session → browser Accept-Language → default 'en'
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

// Supported languages
define('SUPPORTED_LANGUAGES', ['en', 'zh', 'hi', 'es', 'fr']);
define('DEFAULT_LANGUAGE', 'en');

// Language display names (in their own language)
define('LANGUAGE_NAMES', [
    'en' => 'English',
    'zh' => '中文',
    'hi' => 'हिन्दी',
    'es' => 'Español',
    'fr' => 'Français',
]);

/**
 * Detect and set the current language.
 * Priority: URL param > session > browser Accept-Language > default
 */
function detectLanguage(): string {
    // 1. URL parameter
    if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGUAGES)) {
        $lang = $_GET['lang'];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['lang'] = $lang;
        }
        return $lang;
    }

    // 2. Session
    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], SUPPORTED_LANGUAGES)) {
        return $_SESSION['lang'];
    }

    // 3. Browser Accept-Language header
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browserLangs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach ($browserLangs as $browserLang) {
            $code = strtolower(substr(trim(explode(';', $browserLang)[0]), 0, 2));
            if (in_array($code, SUPPORTED_LANGUAGES)) {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['lang'] = $code;
                }
                return $code;
            }
        }
    }

    // 4. Default
    return DEFAULT_LANGUAGE;
}

// Translation strings cache
$_TRANSLATIONS = [];
$_CURRENT_LANG = null;

/**
 * Load translations for a language.
 */
function loadTranslations(string $lang): array {
    global $_TRANSLATIONS;

    if (isset($_TRANSLATIONS[$lang])) {
        return $_TRANSLATIONS[$lang];
    }

    $langFile = __DIR__ . '/../lang/' . $lang . '.php';
    if (file_exists($langFile)) {
        $_TRANSLATIONS[$lang] = require $langFile;
    } else {
        $_TRANSLATIONS[$lang] = [];
    }

    return $_TRANSLATIONS[$lang];
}

/**
 * Translate a key. Supports dot notation (e.g., 'nav.home').
 * Falls back to English if key not found in current language.
 *
 * @param string $key Dot-notation key (e.g., 'nav.home')
 * @param string|null $lang Override language
 * @param array $params Placeholder replacements (e.g., [':name' => 'Spencer'])
 * @return string Translated string or the key itself if not found
 */
function t(string $key, ?string $lang = null, array $params = []): string {
    global $_CURRENT_LANG;

    if ($_CURRENT_LANG === null) {
        $_CURRENT_LANG = detectLanguage();
    }

    $lang = $lang ?? $_CURRENT_LANG;
    $translations = loadTranslations($lang);

    // Dot notation lookup
    $parts = explode('.', $key);
    $value = $translations;
    foreach ($parts as $part) {
        if (is_array($value) && isset($value[$part])) {
            $value = $value[$part];
        } else {
            $value = null;
            break;
        }
    }

    // Fallback to English
    if ($value === null && $lang !== 'en') {
        $enTranslations = loadTranslations('en');
        $value = $enTranslations;
        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                $value = null;
                break;
            }
        }
    }

    // If still not found, return the key
    if ($value === null || is_array($value)) {
        return $key;
    }

    // Replace parameters
    foreach ($params as $placeholder => $replacement) {
        $value = str_replace($placeholder, (string) $replacement, $value);
    }

    return $value;
}

/**
 * Get the current language code.
 */
function getCurrentLanguage(): string {
    global $_CURRENT_LANG;
    if ($_CURRENT_LANG === null) {
        $_CURRENT_LANG = detectLanguage();
    }
    return $_CURRENT_LANG;
}

/**
 * Generate a language switcher HTML snippet.
 * @return string HTML for language switcher dropdown
 */
function languageSwitcherHtml(): string {
    $currentLang = getCurrentLanguage();
    $currentUrl = $_SERVER['REQUEST_URI'] ?? '/';

    // Remove existing lang param
    $parsedUrl = parse_url($currentUrl);
    $path = $parsedUrl['path'] ?? '/';
    parse_str($parsedUrl['query'] ?? '', $queryParams);
    unset($queryParams['lang']);

    $html = '<div class="lang-switcher">';
    $html .= '<select onchange="window.location.href=this.value" aria-label="Select language" style="background:rgba(15,23,42,0.8);color:#e0e0e0;border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:6px 10px;font-size:0.85rem;cursor:pointer;">';

    foreach (SUPPORTED_LANGUAGES as $code) {
        $name = LANGUAGE_NAMES[$code] ?? $code;
        $queryParams['lang'] = $code;
        $url = $path . '?' . http_build_query($queryParams);
        $selected = ($code === $currentLang) ? ' selected' : '';
        $html .= "<option value=\"" . htmlspecialchars($url) . "\"$selected>$name</option>";
    }

    $html .= '</select>';
    $html .= '</div>';

    return $html;
}
