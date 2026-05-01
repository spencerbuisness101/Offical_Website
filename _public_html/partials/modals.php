<!-- Account Prompt Modal -->
<div class="s-modal-overlay" id="accountPromptModal">
    <div class="s-modal modal-narrow">
        <button type="button" class="s-modal-close" aria-label="Close">?</button>
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

<!-- Community Signup Modal -->
<div class="s-modal-overlay" id="communityModal" role="dialog" aria-modal="true">
    <div class="s-modal" role="document">
        <button class="s-modal-close" aria-label="Close">?</button>
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
