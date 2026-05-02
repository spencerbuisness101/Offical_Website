<!-- Account Prompt Modal -->
<div class="s-modal-overlay" id="accountPromptModal">
    <div class="s-modal modal-narrow">
        <button type="button" class="s-modal-close" aria-label="Close" data-action="close-account-prompt">×</button>
        <h2 class="s-modal-title">Choose Your Path</h2>
        <div class="modal-grid">
            <div class="modal-card">
                <i class="fas fa-users modal-icon-community"></i>
                <h3 class="modal-title-small">Community</h3>
                <a href="#" class="btn-primary modal-cta-small" data-action="community-modal-close-prompt">Continue Free</a>
            </div>
            <div class="modal-card-premium">
                <i class="fas fa-crown modal-icon-premium"></i>
                <h3 class="modal-title-small">Premium</h3>
                <a href="shop.php" class="btn-primary modal-cta-premium">View Plans</a>
            </div>
        </div>
    </div>
</div>

<!-- Modernized Feature Matrix Modal -->
<div class="s-modal-overlay" id="compareModal" role="dialog" aria-modal="true">
    <div class="s-modal modal-wide comparison-modal" role="document">
        <button class="s-modal-close" aria-label="Close" data-action="close-compare-modal">×</button>
        
        <div class="modal-header-cinematic">
            <h2 class="s-modal-title">Feature Comparison Matrix</h2>
            <p class="modal-subtitle">A detailed breakdown of how we elevate your digital experience.</p>
        </div>

        <div class="comparison-matrix-container">
            <!-- Matrix Headers -->
            <div class="matrix-row matrix-header">
                <div class="feature-col">Feature</div>
                <div class="plan-col">Community</div>
                <div class="plan-col">Monthly</div>
                <div class="plan-col highlight">Yearly</div>
                <div class="plan-col">Lifetime</div>
            </div>

            <!-- Core Access Group -->
            <div class="matrix-group">
                <div class="group-title"><i class="fas fa-gamepad"></i> Core Platform Access</div>
                
                <div class="matrix-row">
                    <div class="feature-col">
                        <span class="feature-name">Browser Games</span>
                        <span class="feature-hint">70+ instant-play HTML5 games</span>
                    </div>
                    <div class="plan-col">Full Access</div>
                    <div class="plan-col">Unlimited</div>
                    <div class="plan-col highlight">Unlimited</div>
                    <div class="plan-col">Unlimited</div>
                </div>

                <div class="matrix-row">
                    <div class="feature-col">
                        <span class="feature-name">Global Rankings</span>
                        <span class="feature-hint">Compete on worldwide leaderboards</span>
                    </div>
                    <div class="plan-col"><i class="fas fa-check-circle text-teal"></i></div>
                    <div class="plan-col"><i class="fas fa-check-circle text-teal"></i></div>
                    <div class="plan-col highlight"><i class="fas fa-check-circle text-gold"></i></div>
                    <div class="plan-col"><i class="fas fa-check-circle text-gold"></i></div>
                </div>
            </div>

            <!-- AI & Social Group -->
            <div class="matrix-group">
                <div class="group-title"><i class="fas fa-brain"></i> AI & Intelligence</div>
                
                <div class="matrix-row">
                    <div class="feature-col">
                        <span class="feature-name">AI Personalities</span>
                        <span class="feature-hint">Chat with unique AI entities</span>
                    </div>
                    <div class="plan-col">30/Day</div>
                    <div class="plan-col">Unlimited</div>
                    <div class="plan-col highlight">Unlimited</div>
                    <div class="plan-col">Unlimited</div>
                </div>

                <div class="matrix-row">
                    <div class="feature-col">
                        <span class="feature-name">Custom Themes</span>
                        <span class="feature-hint">Personalize your UI & Profile</span>
                    </div>
                    <div class="plan-col"><i class="fas fa-times-circle text-dim"></i></div>
                    <div class="plan-col"><i class="fas fa-check-circle text-teal"></i></div>
                    <div class="plan-col highlight"><i class="fas fa-check-circle text-gold"></i></div>
                    <div class="plan-col"><i class="fas fa-check-circle text-gold"></i></div>
                </div>
            </div>

            <!-- Support & Perks Group -->
            <div class="matrix-group">
                <div class="group-title"><i class="fas fa-star"></i> Exclusive Perks</div>
                
                <div class="matrix-row">
                    <div class="feature-col">
                        <span class="feature-name">Support Priority</span>
                    </div>
                    <div class="plan-col">Standard</div>
                    <div class="plan-col">High</div>
                    <div class="plan-col highlight">VIP Lane</div>
                    <div class="plan-col">VIP Dedicated</div>
                </div>

                <div class="matrix-row">
                    <div class="feature-col">
                        <span class="feature-name">Early Beta Access</span>
                    </div>
                    <div class="plan-col"><i class="fas fa-times-circle text-dim"></i></div>
                    <div class="plan-col"><i class="fas fa-times-circle text-dim"></i></div>
                    <div class="plan-col highlight"><i class="fas fa-check-circle text-gold"></i></div>
                    <div class="plan-col"><i class="fas fa-check-circle text-gold"></i></div>
                </div>
            </div>
        </div>

        <div class="matrix-footer">
            <div class="matrix-cta-group">
                <a href="shop.php?plan=monthly" class="matrix-btn">Get Monthly</a>
                <a href="shop.php?plan=yearly" class="matrix-btn primary">Claim Yearly (Best Value)</a>
                <a href="shop.php?plan=lifetime" class="matrix-btn">Get Lifetime</a>
            </div>
            <p class="matrix-footnote">*Prices in USD. Cancel anytime. All tiers contribute to platform growth.</p>
        </div>
    </div>
</div>

<!-- Community Signup Modal -->
<div class="s-modal-overlay" id="communityModal" role="dialog" aria-modal="true">
    <div class="s-modal" role="document">
        <button class="s-modal-close" aria-label="Close" data-action="close-community-modal">×</button>
        <div id="communityMainView">
            <h2 class="s-modal-title">Welcome to the Community</h2>
            <div class="community-tiles">
                <div class="community-tile" data-action="login-close-community">
                    <div class="community-tile-icon">??</div>
                    <div class="community-tile-title">Sign In</div>
                    <button class="community-tile-cta">Sign In ?</button>
                </div>
                <div class="community-tile featured" data-action="show-guest-disclosure">
                    <div class="community-tile-icon">?</div>
                    <div class="community-tile-title">Guest Pass</div>
                    <button class="community-tile-cta">Get Guest Pass ?</button>
                </div>
            </div>
        </div>
    </div>
</div>
