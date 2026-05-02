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

<!-- Plan Comparison Modal -->
<div class="s-modal-overlay" id="compareModal" role="dialog" aria-modal="true">
    <div class="s-modal modal-wide" role="document">
        <button class="s-modal-close" aria-label="Close" data-action="close-compare-modal">×</button>
        <h2 class="s-modal-title">Feature Comparison</h2>
        <div class="compare-table-wrap">
            <table class="compare-table">
                <thead>
                    <tr>
                        <th>Features</th>
                        <th>Community</th>
                        <th>Monthly</th>
                        <th class="col-yearly">Yearly</th>
                        <th>Lifetime</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Unlimited Game Access</td>
                        <td><i class="fas fa-check compare-check"></i></td>
                        <td><i class="fas fa-check compare-check"></i></td>
                        <td><i class="fas fa-check compare-check"></i></td>
                        <td><i class="fas fa-check compare-check"></i></td>
                    </tr>
                    <tr>
                        <td>AI Assistant Access</td>
                        <td><span class="compare-limited">Limited</span></td>
                        <td><i class="fas fa-check compare-check"></i></td>
                        <td><i class="fas fa-check compare-check"></i></td>
                        <td><i class="fas fa-check compare-check"></i></td>
                    </tr>
                    <tr>
                        <td>Custom Profile Themes</td>
                        <td><i class="fas fa-times compare-cross"></i></td>
                        <td><i class="fas fa-check compare-check"></i></td>
                        <td><i class="fas fa-check compare-check"></i></td>
                        <td><i class="fas fa-check compare-check"></i></td>
                    </tr>
                    <tr>
                        <td>Community Badge</td>
                        <td>Basic</td>
                        <td>Premium</td>
                        <td>Elite</td>
                        <td>Lifetime OG</td>
                    </tr>
                    <tr>
                        <td>Priority Support</td>
                        <td><i class="fas fa-times compare-cross"></i></td>
                        <td><i class="fas fa-check compare-check"></i></td>
                        <td><i class="fas fa-check compare-check"></i></td>
                        <td><i class="fas fa-check compare-check"></i></td>
                    </tr>
                    <tr>
                        <td>Exclusive Beta Access</td>
                        <td><i class="fas fa-times compare-cross"></i></td>
                        <td><i class="fas fa-times compare-cross"></i></td>
                        <td><i class="fas fa-check compare-check"></i></td>
                        <td><i class="fas fa-check compare-check"></i></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="compare-actions">
            <a href="shop.php?plan=monthly" class="btn-primary compare-cta">Get Monthly</a>
            <a href="shop.php?plan=yearly" class="btn-primary compare-cta-yearly">Get Yearly (Save 11%)</a>
        </div>
        <p class="compare-footnote">All plans include 24/7 community access and core platform updates. <a href="terms.php">Terms apply</a>.</p>
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
