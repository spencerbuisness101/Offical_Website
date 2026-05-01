<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? "Spencer's Website"); ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="/assets/images/favicon.webp">
    <link rel="apple-touch-icon" href="/assets/images/favicon.webp">

    <!-- Performance: Resource Hints -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">

    <!-- Styles -->
    <link rel="stylesheet" href="/styles.css">
    
    <?php if (isset($custom_css)): ?>
    <style><?php echo $custom_css; ?></style>
    <?php endif; ?>
    
    <?php if (isset($additional_css)): ?>
    <link rel="stylesheet" href="<?php echo $additional_css; ?>">
    <?php endif; ?>
</head>
<body>
    <?php if (!isset($hide_navigation) || !$hide_navigation): ?>
    <div class="container">
        <a href="/main.php" class="centered-box">← Back to Main Site</a>
    </div>
    <?php endif; ?>