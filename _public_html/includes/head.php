<?php
/**
 * Shared <head> include — Spencer's Website v7.0
 *
 * Renders the canonical <head> section used by every page.
 * Emits: charset, viewport, title, description, favicon, theme color,
 * tokens.css, preloaded fonts, reCAPTCHA (conditional), CSRF meta.
 *
 * Usage:
 *   $page_title = 'Shop — Spencer's Website';
 *   $page_description = 'Subscribe to premium features.';
 *   $page_extra_css = ['/css/shop.css'];    // optional per-page stylesheets
 *   $enable_recaptcha = true;               // opt-in for pages with forms
 *   require __DIR__ . '/includes/head.php';
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}
// Only load init if not already loaded (idempotent)
if (!defined('INIT_LOADED')) {
    require_once __DIR__ . '/init.php';
}
if (!function_exists('generateCsrfToken')) {
    require_once __DIR__ . '/csrf.php';
}
if (!function_exists('asset')) {
    require_once __DIR__ . '/asset.php';
}

// Defaults
$page_title       = $page_title       ?? "Spencer's Website";
$page_description = $page_description ?? "Games, AI chat, and a thriving community — all in one immersive platform.";
$page_extra_css   = $page_extra_css   ?? [];
$page_extra_js    = $page_extra_js    ?? [];
$enable_recaptcha = $enable_recaptcha ?? false;
$page_theme_color = $page_theme_color ?? '#04040A';
$defer_js         = $defer_js         ?? true; // Default defer non-critical JS

$__csrfToken = generateCsrfToken();
$__recaptchaSiteKey = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';
$__siteVersion = defined('SITE_VERSION') ? SITE_VERSION : '7.0';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="<?php echo htmlspecialchars($page_theme_color); ?>">
<meta name="color-scheme" content="dark">
<meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
<meta name="csrf-token" content="<?php echo htmlspecialchars($__csrfToken); ?>">
<meta name="site-version" content="<?php echo htmlspecialchars($__siteVersion); ?>">

<title><?php echo htmlspecialchars($page_title); ?></title>
<link rel="icon" href="/favicon.ico" type="image/x-icon">

<!-- Preload Inter font for faster first paint -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"></noscript>

<!-- Design tokens (must load first, synchronously) -->
<link rel="stylesheet" href="<?php echo asset('css/tokens.css'); ?>">

<?php foreach ($page_extra_css as $__css): ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($__css); ?>">
<?php endforeach; ?>

<?php if ($enable_recaptcha && $__recaptchaSiteKey): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($__recaptchaSiteKey); ?>" async defer></script>
<script>
    window.RECAPTCHA_SITE_KEY = '<?php echo htmlspecialchars($__recaptchaSiteKey); ?>';
</script>
<?php endif; ?>

<?php foreach ($page_extra_js as $__js): ?>
<?php if (is_array($__js)): ?>
<script src="<?php echo htmlspecialchars($__js['src'] ?? $__js[0]); ?>" <?php echo ($__js['defer'] ?? $defer_js) ? 'defer' : ''; ?> <?php echo ($__js['async'] ?? false) ? 'async' : ''; ?>></script>
<?php else: ?>
<script src="<?php echo htmlspecialchars($__js); ?>" <?php echo $defer_js ? 'defer' : ''; ?>></script>
<?php endif; ?>
<?php endforeach; ?>
