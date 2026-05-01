<section class="login-page" id="loginPage" aria-hidden="true">
    <aside class="cl-left">
        <a href="#" class="cl-back-home" data-action="landing">
            <i class="fas fa-arrow-left"></i> Back to home
        </a>
        <div class="cl-brand-block">
            <h2 class="cl-headline" id="clRotatorText">Step inside.</h2>
            <p class="cl-sub">Games, AI, and a thriving community - all behind a single sign-in.</p>
        </div>
    </aside>
    <div class="cl-right">
        <div class="login-card glassmorphism-premium" id="login">
            <div class="login-brand">
                <div class="icon-glow-wrap">
                    <i class="fas fa-gem login-brand-icon"></i>
                </div>
                <h2>Welcome back</h2>
                <p class="brand-subtitle">Enter your credentials to access your dashboard.</p>
            </div>
            
            <form id="loginForm" method="POST" action="/auth/login_with_security.php">
                <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" id="loginRecaptchaToken" name="recaptcha_token" value="">
                
                <div id="networkBanner" class="alert-banner error-banner"></div>
                
                <div class="form-group">
                    <label class="form-label" for="username">Email or Username</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" id="username" name="identifier" class="form-input premium-input" placeholder="Enter your email or username" required autocomplete="username">
                    </div>
                    <div id="usernameError" class="input-error-msg"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" class="form-input premium-input" placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" id="togglePassword" class="password-toggle-btn" aria-label="Toggle password visibility">
                            <i class="fas fa-eye" id="eyeOpen" style="display:none;"></i>
                            <i class="fas fa-eye-slash" id="eyeClosed"></i>
                        </button>
                    </div>
                    <div id="passwordError" class="input-error-msg"></div>
                </div>
                
                <div class="form-actions-row">
                    <label class="premium-checkbox">
                        <input type="checkbox" id="rememberMe" name="remember_me" value="1">
                        <span class="checkmark"></span>
                        <span class="checkbox-text">Remember me</span>
                    </label>
                    <a href="/auth/forgot_password.php" class="forgot-link">Forgot password?</a>
                </div>
                
                <button type="submit" id="loginBtn" class="btn-premium-submit">
                    Sign In
                </button>
                
                <div class="login-footer-links">
                    <p>Don't have an account? <a href="#" data-action="create-guest" class="highlight-link">Play as Guest</a> or <a href="/auth/register.php" class="highlight-link">Sign Up</a></p>
                </div>
            </form>
        </div>
    </div>
</section>
