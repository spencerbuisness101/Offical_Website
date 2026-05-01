<?php
/**
 * Main page — Background selection modal
 * Existing modal markup carried over (JS in common.js / styles.css still drive behavior).
 */
if (!defined('MAIN_PAGE_LOADED')) { http_response_code(403); exit; }
?>
<div id="backgroundModal" class="background-modal">
    <div class="background-modal-content">
        <div class="background-modal-header">
            <h2 class="background-modal-title">🎨 Choose Your Background</h2>
            <button class="background-modal-close" onclick="closeBackgroundModal()" aria-label="Close background picker">&times;</button>
        </div>
        <div class="backgrounds-grid" id="backgroundsGrid">
            <!-- Populated by JS -->
        </div>
        <div style="text-align:center; margin-top:2rem;">
            <button class="btn-remove-background" onclick="removeCustomBackground()" style="padding:12px 24px; font-size:1rem;">
                🗑️ Remove Custom Background
            </button>
        </div>
    </div>
</div>

<!-- Minimal inline CSS to keep the legacy modal styled regardless of tokens -->
<style>
.background-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:10000; backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px); }
.background-modal.open { display:block; }
.background-modal-content { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:rgba(10,10,20,0.97); border:0.5px solid rgba(123,110,246,0.3); border-radius:20px; width:90%; max-width:1000px; max-height:90vh; overflow-y:auto; padding:2rem; }
.background-modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; padding-bottom:1rem; border-bottom:0.5px solid rgba(255,255,255,0.08); }
.background-modal-title { font-size:1.4rem; font-weight:400; color:#E2E8F0; background:linear-gradient(135deg,#7B6EF6,#1DFFC4); -webkit-background-clip:text; background-clip:text; color:transparent; }
.background-modal-close { background:rgba(255,255,255,0.05); border:0.5px solid rgba(255,255,255,0.1); color:#94A3B8; font-size:1.2rem; cursor:pointer; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:all 0.2s; }
.background-modal-close:hover { color:#F87171; border-color:rgba(248,113,113,0.3); background:rgba(248,113,113,0.1); transform:rotate(90deg); }
.backgrounds-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:1.25rem; margin-bottom:2rem; }
.background-item { background:rgba(255,255,255,0.03); border:0.5px solid rgba(255,255,255,0.1); border-radius:12px; overflow:hidden; transition:all 0.3s ease; cursor:pointer; }
.background-item:hover { transform:translateY(-4px); border-color:rgba(123,110,246,0.4); box-shadow:0 10px 30px rgba(123,110,246,0.2); }
.background-item.active { border-color:#1DFFC4; box-shadow:0 0 0 3px rgba(29,255,196,0.2); }
.background-preview { width:100%; height:150px; background-size:cover; background-position:center; }
.background-info { padding:1rem; }
.background-title { font-weight:500; color:#E2E8F0; margin-bottom:0.3rem; font-size:0.95rem; }
.background-designer { color:#94A3B8; font-size:0.8rem; }
.background-actions { display:flex; gap:0.5rem; margin-top:0.8rem; }
.btn-set-background { background:linear-gradient(135deg,#7B6EF6,#1DFFC4); color:#04040A; border:none; padding:8px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s ease; flex:1; }
.btn-set-background:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(29,255,196,0.3); }
.btn-remove-background { background:rgba(248,113,113,0.1); color:#F87171; border:0.5px solid rgba(248,113,113,0.3); padding:8px 14px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; transition:all 0.2s ease; }
.btn-remove-background:hover { background:#F87171; color:#fff; }
.bg-theme-override { position:fixed; top:0; left:0; width:100%; height:100%; z-index:-2; background-size:cover; background-position:center; background-attachment:fixed; transition:background-image 0.5s ease-in-out; background-color:#04040A; }
.bg-theme-override.designer-bg::after { content:''; position:absolute; inset:0; background:rgba(0,0,0,0.55); z-index:-1; }
</style>
