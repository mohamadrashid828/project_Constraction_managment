<?php
require_once __DIR__ . '/../includes/i18n.php';
if (!isset($pageTitle)) {
    $pageTitle = t('construction_management', 'Construction Management');
}
if (!isset($pageCss)) {
    $pageCss = '';
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($currentLanguage); ?>" dir="<?php echo locale_direction(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <?php
    // Version-stamp stylesheets with their modification time so browsers pick
    // up CSS changes immediately instead of serving a stale cached copy.
    $cssDir = dirname(__DIR__, 2) . '/css/';
    ?>
    <link rel="stylesheet" href="../css/style.css?v=<?php echo (int)@filemtime($cssDir . 'style.css'); ?>">
    <?php if ($pageCss): ?>
    <link rel="stylesheet" href="../css/<?php echo htmlspecialchars($pageCss); ?>?v=<?php echo (int)@filemtime($cssDir . $pageCss); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<script>
window.appTranslations = <?php echo translation_json(); ?>;
window.appEnglishTranslations = <?php echo english_translation_json(); ?>;
window.appLanguage = <?php echo json_encode($currentLanguage); ?>;
window.translateStaticUi = function (root) {
    const source = window.appEnglishTranslations || {};
    const target = window.appTranslations || {};
    const lookup = new Map();

    Object.keys(source).forEach(function (key) {
        if (source[key] && target[key] && source[key] !== target[key]) {
            lookup.set(source[key], target[key]);
        }
    });

    const translateValue = function (value) {
        const text = String(value || '');
        const match = text.match(/^(\s*)(.*?)(\s*)$/s);
        return match && lookup.has(match[2]) ? match[1] + lookup.get(match[2]) + match[3] : text;
    };

    const scope = root || document;
    scope.querySelectorAll('button, label, th, h1, h2, h3, h4, p, small, a, span, input[placeholder], textarea[placeholder], [title], [aria-label]').forEach(function (element) {
        if (element.closest('[data-no-i18n]')) return;
        if (element.tagName === 'OPTION' || element.closest('select')) return;
        ['placeholder', 'title', 'aria-label'].forEach(function (attribute) {
            if (element.hasAttribute(attribute)) {
                element.setAttribute(attribute, translateValue(element.getAttribute(attribute)));
            }
        });
        Array.from(element.childNodes).forEach(function (node) {
            if (node.nodeType === Node.TEXT_NODE) node.nodeValue = translateValue(node.nodeValue);
        });
    });
};
document.addEventListener('DOMContentLoaded', function () {
    const languageSelect = document.getElementById('language-select');
    if (!languageSelect) return;
    languageSelect.addEventListener('change', function () {
        const url = new URL(window.location.href);
        url.searchParams.set('lang', this.value);
        window.location.assign(url.toString());
    });
    window.translateStaticUi();
});
</script>
