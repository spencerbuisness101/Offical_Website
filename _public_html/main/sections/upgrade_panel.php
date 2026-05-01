<?php
/**
 * Main page — Upgrade panel for free Community users (not guests)
 * Expects: $role, $is_guest
 */
if (!defined('MAIN_PAGE_LOADED')) { http_response_code(403); exit; }
if ($role !== 'community' || $is_guest) return;
?>
<section class="mp-upgrade mp-reveal" aria-label="Upgrade options">
    <div class="mp-upgrade-card">
        <h2 class="mp-upgrade-title">Unlock Full Access</h2>
        <p class="mp-upgrade-sub">Upgrade to User and get the complete experience</p>

        <div class="mp-upgrade-perks">
            <span class="mp-upgrade-perk">🎨 Custom Backgrounds</span>
            <span class="mp-upgrade-perk">🤖 AI Assistant</span>
            <span class="mp-upgrade-perk">💬 Yaps Chat</span>
            <span class="mp-upgrade-perk">🏷 Chat Tags</span>
            <span class="mp-upgrade-perk">🎨 Accent Colors</span>
            <span class="mp-upgrade-perk">☁ Synced Settings</span>
        </div>

        <div class="mp-plan-cards">
            <div id="upgPlanMonthly" class="mp-plan" onclick="selectUpgradePlan('monthly')">
                <div class="mp-plan-price">$3<small>/mo</small></div>
                <div class="mp-plan-meta">Cancel anytime</div>
            </div>
            <div id="upgPlanYearly" class="mp-plan selected" onclick="selectUpgradePlan('yearly')">
                <span class="mp-plan-tag">SAVE 11%</span>
                <div class="mp-plan-price">$32<small>/yr</small></div>
                <div class="mp-plan-meta">$2.67/mo billed yearly</div>
            </div>
            <div id="upgPlanLifetime" class="mp-plan" onclick="selectUpgradePlan('lifetime')">
                <span class="mp-plan-tag hot">BEST</span>
                <div class="mp-plan-price">$100</div>
                <div class="mp-plan-meta">One-time, forever</div>
            </div>
        </div>

        <button id="upgradeStripeBtn" onclick="startUpgradeCheckout('stripe')" class="mp-upgrade-btn">Upgrade Now</button>
        <div id="upgradeError" style="display:none; margin-top:14px; padding:10px; background:rgba(248,113,113,0.15); border:0.5px solid rgba(248,113,113,0.3); border-radius:10px; color:#F87171; font-size:13px;"></div>
    </div>
</section>
