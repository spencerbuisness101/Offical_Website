<?php
if (!defined('APP_RUNNING')) define('APP_RUNNING', true);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: main.php');
    exit;
}

$forced_logout = isset($_GET['forced_logout']) && $_GET['forced_logout'] == '1';
$csrfToken = generateCsrfToken();
$recaptchaSiteKey = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';
?>
<!DOCTYPE html>
<html lang="en">

<?php include 'partials/head.php'; ?>

<body data-cinematic-bg="normal">
    <div class="wipe-overlay" id="wipeOverlay"></div>

    <?php include 'partials/navbar.php'; ?>

    <main class="landing-page" id="landingPage">
        <?php include 'partials/hero.php'; ?>
        <?php include 'partials/features.php'; ?>
        <?php include 'partials/pricing.php'; ?>
        <?php include 'partials/footer.php'; ?>
    </main>

    <?php include 'partials/login.php'; ?>
    <?php include 'partials/modals.php'; ?>

    <!-- Scripts -->
    <script src="js/cinematic-bg.js?v=7.1" defer></script>
    <script src="js/index-main.js?v=7.1" defer></script>
    <script src="js/index-modal.js?v=7.1" defer></script>
</body>
</html>
