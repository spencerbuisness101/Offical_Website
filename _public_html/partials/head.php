<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spencer's Website — Your Digital Universe</title>
    <meta name="description" content="Games, AI chat, and a thriving community.">
    
    <link rel="icon" href="/assets/images/favicon.webp" type="image/webp">
    
    <?php 
    $assetExt = (defined('DEBUG') && DEBUG) ? '' : '.min';
    $siteVersion = defined('SITE_VERSION') ? SITE_VERSION : '7.1';
    ?>

    <!-- Preloads -->
    <link rel="preload" href="js/index-main<?php echo $assetExt; ?>.js?v=<?php echo $siteVersion; ?>" as="script">
    <link rel="preload" href="js/index-modal<?php echo $assetExt; ?>.js?v=<?php echo $siteVersion; ?>" as="script">
    <link rel="preload" href="css/tokens<?php echo $assetExt; ?>.css?v=<?php echo $siteVersion; ?>" as="style">

    <!-- Core Styles -->
    <link rel="stylesheet" href="css/tokens<?php echo $assetExt; ?>.css?v=<?php echo $siteVersion; ?>">
    <link rel="stylesheet" href="css/index<?php echo $assetExt; ?>.css?v=<?php echo $siteVersion; ?>">
    <link rel="stylesheet" href="css/cinematic-bg<?php echo $assetExt; ?>.css?v=<?php echo $siteVersion; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Premium UI Enhancements -->
    <style>
        :root {
            --glass-bg: rgba(10, 10, 20, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
            --accent-glow: rgba(123, 110, 246, 0.4);
        }
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }
        .hero-headline {
            background: linear-gradient(135deg, #fff 0%, #7b6ef6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
        }
        .btn-primary {
            background: linear-gradient(135deg, #7b6ef6 0%, #5e4ef3 100%);
            box-shadow: 0 4px 15px var(--accent-glow);
            border: none;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px var(--accent-glow);
        }
    </style>

    <?php if (isset($recaptchaSiteKey) && $recaptchaSiteKey): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($recaptchaSiteKey); ?>" async defer></script>
    <?php endif; ?>
</head>

