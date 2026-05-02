<!-- Page Header -->
        <header class="page-header">
            <h1><i class="fa-solid fa-gamepad"></i> Game Center</h1>
            <p>Discover our collection of handpicked HTML5 games and interactive experiences</p>
        </header>

        <!-- Announcements Section -->
        <?php include_once 'includes/announcements.php'; ?>

        <!-- Active Background Info -->
        <?php if ($active_designer_background): ?>
        <div class="background-info-section">
            <h3><i class="fa-solid fa-palette"></i> Active Community Background</h3>
            <p><strong>"<?php echo htmlspecialchars($active_designer_background['title']); ?>"</strong></p>
            <p>Designed by: <?php echo htmlspecialchars($active_designer_background['designer_name']); ?></p>
            <button class="btn-set-background" onclick="setAsBackground('<?php echo $active_designer_background['image_url']; ?>', '<?php echo htmlspecialchars($active_designer_background['title']); ?>')" style="margin-top: 10px;">
                Use This Background
            </button>
        </div>
        <?php endif; ?>

        <!-- ========================================
             Hero Carousel - Featured Games
             ======================================== -->
        <section class="hero-carousel" aria-label="Featured Games">
            <!-- Card 1: Crazy Cattle 3D -->
            <div class="hero-card active" data-hero="0">
                <div class="hero-card-inner">
                    <div class="hero-emoji"><i class="fa-solid fa-cow"></i></div>
                    <h2>Crazy Cattle 3D</h2>
                    <p>Wild cattle action in 3D! One of our most popular games with fast-paced gameplay and hilarious physics.</p>
                    <div class="hero-stats">
                        <div class="hero-stat"><div class="hero-stat-val">4.9/5</div><div class="hero-stat-lbl">Rating</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">POPULAR</div><div class="hero-stat-lbl">Status</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">3D</div><div class="hero-stat-lbl">Graphics</div></div>
                    </div>
                    <a href="Games/crazycattle3d.php" class="hero-play-btn"><i class="fa-solid fa-play"></i> Play Crazy Cattle 3D</a>
                </div>
            </div>

            <!-- Card 2: Tomb of the Mask -->
            <div class="hero-card" data-hero="1">
                <div class="hero-card-inner">
                    <div class="hero-emoji"><i class="fa-solid fa-crosshairs"></i></div>
                    <h2>Tomb of the Mask</h2>
                    <p>Navigate through challenging maze-like levels in this fast-paced arcade adventure. Collect power-ups and avoid traps!</p>
                    <div class="hero-stats">
                        <div class="hero-stat"><div class="hero-stat-val">4.8/5</div><div class="hero-stat-lbl">Rating</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">Easy</div><div class="hero-stat-lbl">Difficulty</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">HTML5</div><div class="hero-stat-lbl">Engine</div></div>
                    </div>
                    <a href="Games/tomb.php" class="hero-play-btn"><i class="fa-solid fa-play"></i> Play Tomb of the Mask</a>
                </div>
            </div>

            <!-- Card 3: Solar Smash -->
            <div class="hero-card" data-hero="2">
                <div class="hero-card-inner">
                    <div class="hero-emoji"><i class="fa-solid fa-earth-americas"></i></div>
                    <h2>Solar Smash</h2>
                    <p>An explosive planetary destruction simulator where you wield cosmic weapons to obliterate celestial bodies. Brand new!</p>
                    <div class="hero-stats">
                        <div class="hero-stat"><div class="hero-stat-val">NEW</div><div class="hero-stat-lbl">Status</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">4.7/5</div><div class="hero-stat-lbl">Rating</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">Sandbox</div><div class="hero-stat-lbl">Genre</div></div>
                    </div>
                    <a href="Games/solar.php" class="hero-play-btn"><i class="fa-solid fa-play"></i> Play Solar Smash</a>
                </div>
            </div>

            <!-- Card 4: Retro Bowl -->
            <div class="hero-card" data-hero="3">
                <div class="hero-card-inner">
                    <div class="hero-emoji"><i class="fa-solid fa-football"></i></div>
                    <h2>Retro Bowl</h2>
                    <p>Classic football action with retro-style graphics. Build your team, manage your roster, and compete for the championship!</p>
                    <div class="hero-stats">
                        <div class="hero-stat"><div class="hero-stat-val">4.9/5</div><div class="hero-stat-lbl">Rating</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">Fan Fav</div><div class="hero-stat-lbl">Status</div></div>
                        <div class="hero-stat"><div class="hero-stat-val">Retro</div><div class="hero-stat-lbl">Style</div></div>
                    </div>
                    <a href="Games/retro.php" class="hero-play-btn"><i class="fa-solid fa-play"></i> Play Retro Bowl</a>
                </div>
            </div>

            <!-- Carousel Dots -->
            <div class="carousel-dots">
                <button class="carousel-dot active" data-dot="0" aria-label="Featured game 1"></button>
                <button class="carousel-dot" data-dot="1" aria-label="Featured game 2"></button>
                <button class="carousel-dot" data-dot="2" aria-label="Featured game 3"></button>
                <button class="carousel-dot" data-dot="3" aria-label="Featured game 4"></button>
            </div>
        </section>