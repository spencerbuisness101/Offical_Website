<?php
require_once __DIR__ . '/includes/init.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Design Guidelines - Spencer's Website</title>
    <link rel="icon" type="image/webp" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #f8fafc;
            line-height: 1.6;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(30, 41, 59, 0.8);
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        h1 {
            text-align: center;
            color: #8b5cf6;
            margin-bottom: 2rem;
        }
        
        .guideline-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 8px;
            border-left: 4px solid #8b5cf6;
        }
        
        .guideline-section h3 {
            color: #e2e8f0;
            margin-bottom: 1rem;
        }
        
        ul {
            padding-left: 1.5rem;
        }
        
        li {
            margin-bottom: 0.5rem;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .navigation {
            text-align: center;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎨 Design Guidelines</h1>
        
        <div class="guideline-section">
            <h3>📏 Image Requirements</h3>
            <ul>
                <li>Use high-quality images with good resolution</li>
                <li>Recommended size: 1920x1080 pixels or larger</li>
                <li>File formats: JPG, PNG, WebP</li>
                <li>Max file size: 5MB (for direct uploads)</li>
                <li>Aspect ratio: 16:9 works best</li>
            </ul>
        </div>
        
        <div class="guideline-section">
            <h3>🎯 Content Guidelines</h3>
            <ul>
                <li>Images must be appropriate for all audiences</li>
                <li>No copyrighted material without permission</li>
                <li>Landscape orientation works best</li>
                <li>Avoid text-heavy images</li>
                <li>Consider how the image will look with overlay content</li>
            </ul>
        </div>
        
        <div class="guideline-section">
            <h3>🌈 Style Recommendations</h3>
            <ul>
                <li>Use images with good contrast</li>
                <li>Softer, less busy backgrounds work better</li>
                <li>Consider the dark theme of the website</li>
                <li>Test how text appears over your background</li>
                <li>Seasonal/themed backgrounds are encouraged!</li>
            </ul>
        </div>
        
        <div class="guideline-section">
            <h3>⚡ Technical Notes</h3>
            <ul>
                <li>Use reliable image hosting services</li>
                <li>Ensure URLs are permanent/don't expire</li>
                <li>Compress images for faster loading</li>
                <li>Test your background on different screen sizes</li>
                <li>Provide descriptive titles and descriptions</li>
            </ul>
        </div>
        
        <div class="navigation">
            <a href="main.php" class="btn">Back to Main Site</a>
            <a href="main.php" class="btn" style="background: linear-gradient(135deg, #6366f1, #4f46e5); margin-left: 1rem;">Back to Main Site</a>
        </div>
    </div>
</body>
</html>