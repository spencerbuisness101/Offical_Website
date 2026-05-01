<?php
// includes/games_head.php - generated
// Prevent direct access
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}
$asset_version = '20251109';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($page_title ?? 'Game'); ?></title>
<link rel="icon" type="image/webp" href="/assets/images/favicon.webp">
<link rel="stylesheet" href="/assets/games/common.20251109.min.css?v=20251109">
<?php if(!empty($extra_css)) echo '<link rel="stylesheet" href="'.htmlspecialchars($extra_css).'?v=20251109">'; ?>
</head>
<body>
