<section class="login-page" id="loginPage" aria-hidden="true">
    <aside class="cl-left">
        <a href="#" class="cl-back-home" data-action="landing"><i class="fas fa-arrow-left"></i> Back to home</a>
        <div class="cl-brand-block">
            <h2 class="cl-headline">Step inside.</h2>
            <p class="cl-sub">Games, AI, and a thriving community — all behind a single sign-in.</p>
        </div>
    </aside>
    <div class="cl-right">
        <div class="login-card" id="login">
            <div class="login-brand">
                <i class="fas fa-gem login-brand-icon"></i>
                <h2>Welcome back</h2>
            </div>
            <form id="loginForm" method="POST" action="/auth/login_with_security.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <div class="form-group">
                    <label class="form-label">Email or Username</label>
                    <input type="text" name="identifier" class="form-input" placeholder="Enter your email or username" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn-login">Sign In</button>
            </form>
        </div>
    </div>
</section>
