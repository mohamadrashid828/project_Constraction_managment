<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$localeDir = dirname(__DIR__, 2) . '/locales';
$languageOptions = [
    'en' => 'English',
    'ckb' => 'کوردی',
    'ar' => 'العربية',
];

if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $languageOptions)) {
    $_SESSION['language'] = $_GET['lang'];
}

$currentLanguage = $_SESSION['language'] ?? 'en';
if (!array_key_exists($currentLanguage, $languageOptions)) {
    $currentLanguage = 'en';
}

$loadTranslations = static function (string $language) use ($localeDir): array {
    $file = $localeDir . '/' . $language . '.json';
    if (!is_file($file)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : [];
};

// English is the complete fallback catalogue. This means a new key never
// exposes an internal key name if one of the translated catalogues lags behind.
$englishTranslations = $loadTranslations('en');
$translations = array_replace($englishTranslations, $loadTranslations($currentLanguage));

function t(string $key, ?string $fallback = null, array $replace = []): string
{
    global $translations;
    $text = (string)($translations[$key] ?? $fallback ?? $key);
    return $replace ? strtr($text, $replace) : $text;
}

function translation_json(): string
{
    global $translations;
    return json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function english_translation_json(): string
{
    global $englishTranslations;
    return json_encode($englishTranslations, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function locale_direction(): string
{
    global $currentLanguage;
    return in_array($currentLanguage, ['ar', 'ckb'], true) ? 'rtl' : 'ltr';
}
