<?php
// includes/games_footer.php - generated
// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}
$asset_version = '20251109';
?>
<script defer src="/assets/games/common.20251109.min.js?v=20251109"></script>
<?php if(!empty($extra_js)) echo '<script defer src="'.htmlspecialchars($extra_js).'?v=20251109"></script>'; ?>
</body>
</html>
