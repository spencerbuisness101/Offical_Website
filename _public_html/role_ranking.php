<?php
/**
 * Role Ranking Page - Spencer's Website v7.0
 * Phase 2: Full visual overhaul. Same role data, same privileges, same feature comparison.
 * Glassmorphism pyramid, refined table, slide-in detail panel.
 */

if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';

require_once __DIR__ . '/includes/security.php';
setSecurityHeaders();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$currentRole = $_SESSION['role'] ?? 'community';
$username = htmlspecialchars($_SESSION['username'] ?? 'User');

$roles = [
    'admin' => [
        'name' => 'Admin',
        'icon' => 'fa-crown',
        'color' => '#ef4444',
        'gradient' => 'linear-gradient(135deg, #ef4444, #dc2626)',
        'description' => 'Highest rank. Permanently unobtainable. Full site control. (UNOBTAINABLE!)',
        'privileges' => ['Full admin panel access', 'User management', 'Site configuration', 'All features unlocked', 'Unobtainable and the most powerful', 'Spencers Role'],
        'obtainable' => false,
        'panel' => 'admin.php'
    ],
    'contributor' => [
        'name' => 'Contributor',
        'icon' => 'fa-lightbulb',
        'color' => '#f59e0b',
        'gradient' => 'linear-gradient(135deg, #f59e0b, #d97706)',
        'description' => 'Unobtainable unless very helpful to the owner. Can submit feature ideas.',
        'privileges' => ['Submit feature ideas', 'Priority feedback', 'All User privileges', 'Unobtainable unless valuable', 'Garretts Role'],
        'obtainable' => false,
        'panel' => 'contributor_panel.php'
    ],
    'designer' => [
        'name' => 'Designer',
        'icon' => 'fa-palette',
        'color' => '#ec4899',
        'gradient' => 'linear-gradient(135deg, #ec4899, #db2777)',
        'description' => 'Unobtainable unless very helpful to the owner. Can submit custom backgrounds.',
        'privileges' => ['Submit custom backgrounds', 'Design contributions', 'All User privileges', 'Unobtainable unless valuable', 'My girlfriends and Maias role'],
        'obtainable' => false,
        'panel' => 'designer_panel.php'
    ],
    'user' => [
        'name' => 'User',
        'icon' => 'fa-user',
        'color' => '#3b82f6',
        'gradient' => 'linear-gradient(135deg, #3b82f6, #2563eb)',
        'description' => 'Create an account and unlock all member benefits for just $2. (ALLTIME!)',
        'privileges' => ['Custom backgrounds', 'Chat tags', 'AI assistant access', 'Full site features', 'Very obtainable'],
        'obtainable' => true,
        'panel' => null
    ],
    'community' => [
        'name' => 'Community',
        'icon' => 'fa-users',
        'color' => '#10b981',
        'gradient' => 'linear-gradient(135deg, #10b981, #059669)',
        'description' => 'Free access to all games and basic site features. (FREE)',
        'privileges' => ['All games access', 'Basic site features', 'Community forums', 'Automatic on signup', 'Free forever'],
        'obtainable' => true,
        'panel' => null
    ]
];

$features = [
    'All games'                  => [1,1,1,1,1],
    'Basic site access'          => [1,1,1,1,1],
    'Yaps chat'                  => [0,1,1,1,1],
    'Custom backgrounds'         => [0,1,1,1,1],
    'Accent colors'              => [0,1,1,1,1],
    'Chat name tags'             => [0,1,1,1,1],
    'AI assistant access'        => [0,1,1,1,1],
    'Server-synced settings'     => [0,1,1,1,1],
    'Testing site access'        => [0,1,1,1,1],
    'Submit bug/feature reports'  => [0,1,1,1,1],
    'Submit feature ideas'       => [0,0,1,0,1],
    'Submit custom backgrounds'  => [0,0,0,1,1],
    'Admin panel access'         => [0,0,0,0,1],
    'User management'            => [0,0,0,0,1],
];
$featureRoleOrder = ['community','user','contributor','designer','admin'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Ranking - Spencer's Website</title>
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <style>
        .rr-wrap { max-width: 1060px; margin: 0 auto; padding: 28px 20px 60px; }

        .rr-back { display: inline-flex; align-items: center; gap: 6px; color: #64748b; text-decoration: none; font-size: 0.88rem; margin-bottom: 20px; transition: color .2s; }
        .rr-back:hover { color: #4ECDC4; }

        /* Hero */
        .rr-hero { text-align: center; margin-bottom: 10px; }
        .rr-hero h1 {
            font-size: 2.2rem; font-weight: 800;
            background: linear-gradient(135deg, #6366f1, #4ECDC4);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .rr-hero p { color: #94a3b8; font-size: 0.95rem; margin-top: 4px; }

        /* Current role banner */
        .rr-current {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            padding: 12px 20px; margin-bottom: 28px;
            background: rgba(15,23,42,0.6); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px;
            font-size: 0.9rem; color: #94a3b8;
        }
        .rr-current-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 14px; border-radius: 20px; font-weight: 700; font-size: 0.85rem; color: #fff;
        }

        /* Equivalence info cards */
        .rr-info-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
        @media (max-width: 640px) { .rr-info-row { grid-template-columns: 1fr; } }
        .rr-info-card {
            padding: 16px 18px; border-radius: 12px; border: 1px solid; font-size: 0.88rem;
        }
        .rr-info-card h4 { font-size: 0.92rem; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
        .rr-info-card p { color: #94a3b8; font-size: 0.83rem; margin: 0; line-height: 1.5; }

        /* ==================== PYRAMID ==================== */
        .rr-pyramid { display: flex; flex-direction: column; align-items: center; gap: 8px; margin-bottom: 36px; position: relative; }

        /* Connector lines */
        .rr-pyramid::before {
            content: '';
            position: absolute; top: 0; bottom: 0; left: 50%;
            width: 1px; background: linear-gradient(to bottom, rgba(99,102,241,0.3), rgba(78,205,196,0.15));
            z-index: 0;
        }

        .rr-tier { display: flex; justify-content: center; gap: 10px; z-index: 1; flex-wrap: wrap; }

        .rr-card {
            position: relative; text-align: center; cursor: pointer;
            padding: 18px 28px; min-width: 135px; border-radius: 14px;
            background: rgba(15,23,42,0.65);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border: 1.5px solid rgba(255,255,255,0.08);
            transition: transform .25s, box-shadow .25s, border-color .25s;
        }
        .rr-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 36px rgba(0,0,0,0.4);
        }
        .rr-card.is-current {
            border-width: 2px;
            animation: currentPulse 2.5s ease-in-out infinite;
        }
        @keyframes currentPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(99,102,241,0.3); }
            50% { box-shadow: 0 0 18px 4px rgba(99,102,241,0.2); }
        }
        .rr-card.selected { transform: translateY(-6px) scale(1.04); }
        .rr-card i { font-size: 1.6rem; display: block; margin-bottom: 8px; }
        .rr-card .rc-name { font-weight: 800; font-size: 0.82rem; text-transform: uppercase; letter-spacing: .6px; }
        .rr-card .rc-sub { font-size: 0.68rem; color: #64748b; margin-top: 3px; }

        /* Tier widths */
        .tier-1 .rr-card { min-width: 160px; }
        .tier-4 .rr-card { min-width: 200px; max-width: 360px; width: 100%; }

        /* ==================== DETAIL PANEL ==================== */
        .rr-detail {
            background: rgba(15,23,42,0.75);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.08); border-radius: 16px;
            padding: 28px; margin-bottom: 32px;
            opacity: 0; max-height: 0; overflow: hidden;
            transition: opacity .35s, max-height .4s ease, padding .35s;
        }
        .rr-detail.visible {
            opacity: 1; max-height: 600px; padding: 28px;
        }
        .rr-detail-head {
            display: flex; align-items: center; gap: 16px; margin-bottom: 20px;
            padding-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .rr-detail-icon {
            width: 56px; height: 56px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; flex-shrink: 0;
        }
        .rr-detail-head h2 { font-size: 1.5rem; margin: 0 0 2px; }
        .rr-detail-head p { color: #94a3b8; font-size: 0.9rem; margin: 0; }

        .rr-privs-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; margin-top: 12px;
        }
        .rr-priv {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; background: rgba(0,0,0,0.2); border-radius: 8px; font-size: 0.88rem; color: #cbd5e1;
        }
        .rr-priv i { color: #4ECDC4; font-size: 0.8rem; flex-shrink: 0; }

        .rr-obtain {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 20px; font-size: 0.82rem; font-weight: 700; margin-top: 16px;
        }
        .rr-obtain.yes { background: rgba(16,185,129,0.15); color: #34d399; }
        .rr-obtain.no  { background: rgba(239,68,68,0.15); color: #f87171; }

        .rr-panel-link { margin-top: 16px; }
        .rr-panel-link a {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px; border-radius: 10px;
            background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff;
            font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: box-shadow .2s;
        }
        .rr-panel-link a:hover { box-shadow: 0 6px 24px rgba(99,102,241,0.35); }

        /* ==================== FEATURE TABLE ==================== */
        .rr-table-wrap {
            background: rgba(15,23,42,0.65); border: 1px solid rgba(255,255,255,0.06);
            border-radius: 14px; padding: 20px; margin-bottom: 28px; overflow-x: auto;
        }
        .rr-table-wrap h3 {
            font-size: 1.05rem; font-weight: 700; color: #e2e8f0; margin-bottom: 14px;
            display: flex; align-items: center; gap: 8px;
        }
        .rr-table {
            width: 100%; border-collapse: collapse; min-width: 580px;
        }
        .rr-table thead th {
            padding: 10px 12px; text-align: center; font-size: 0.78rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px;
            border-bottom: 2px solid rgba(255,255,255,0.06); position: sticky; top: 0;
            background: rgba(15,23,42,0.95);
        }
        .rr-table thead th:first-child { text-align: left; }
        .rr-table tbody tr { border-bottom: 1px solid rgba(255,255,255,0.03); }
        .rr-table tbody tr:nth-child(even) { background: rgba(255,255,255,0.015); }
        .rr-table tbody tr:hover { background: rgba(78,205,196,0.04); }
        .rr-table td {
            padding: 9px 12px; font-size: 0.85rem; color: #cbd5e1;
        }
        .rr-table td:first-child { font-weight: 500; }
        .rr-table td:not(:first-child) { text-align: center; }
        .rr-table .t-yes { color: #10b981; }
        .rr-table .t-no  { color: #1e293b; }

        /* ==================== LEGEND ==================== */
        .rr-legend {
            display: flex; flex-wrap: wrap; gap: 16px; justify-content: center;
            padding: 14px 20px; background: rgba(15,23,42,0.4); border-radius: 10px; margin-bottom: 24px;
        }
        .rr-legend-item { display: flex; align-items: center; gap: 6px; font-size: 0.82rem; color: #94a3b8; }
        .rr-legend-dot { width: 10px; height: 10px; border-radius: 3px; }

        /* Nav */
        .rr-nav { display: flex; justify-content: center; margin-top: 20px; }
        .rr-nav a {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 22px; border: 1.5px solid rgba(255,255,255,0.12); border-radius: 10px;
            color: #94a3b8; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: all .2s;
        }
        .rr-nav a:hover { border-color: #6366f1; color: #e2e8f0; background: rgba(99,102,241,0.08); }

        @media (max-width: 640px) {
            .rr-hero h1 { font-size: 1.6rem; }
            .rr-card { min-width: 110px !important; padding: 14px 18px; }
            .rr-card i { font-size: 1.2rem; }
            .rr-card .rc-name { font-size: 0.72rem; }
            .tier-4 .rr-card { max-width: 100%; }
            .rr-detail { padding: 20px; }
            .rr-privs-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>
<div class="rr-wrap">
    <a href="main.php" class="rr-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <div class="rr-hero">
        <h1><i class="fas fa-layer-group"></i> Role Ranking</h1>
        <p>Understanding the hierarchy and privileges of each role</p>
    </div>

    <div class="rr-current">
        <span>Your current role:</span>
        <span class="rr-current-badge" style="background:<?php echo $roles[$currentRole]['gradient']; ?>">
            <i class="fas <?php echo $roles[$currentRole]['icon']; ?>"></i>
            <?php echo $roles[$currentRole]['name']; ?>
        </span>
    </div>

    <!-- Role equivalence info -->
    <div class="rr-info-row">
        <div class="rr-info-card" style="background:rgba(99,102,241,0.06); border-color:rgba(99,102,241,0.2);">
            <h4 style="color:#818cf8;"><i class="fas fa-info-circle"></i> How Roles Work</h4>
            <p>Click any role in the pyramid to see its privileges. Higher tiers inherit all privileges from the tiers below them.</p>
        </div>
        <div class="rr-info-card" style="background:rgba(245,158,11,0.06); border-color:rgba(245,158,11,0.2);">
            <h4 style="color:#fbbf24;"><i class="fas fa-equals"></i> Contributor = Designer</h4>
            <p>These roles have the <strong>same privilege level</strong> but separate panels. Contributors submit ideas; Designers submit backgrounds.</p>
        </div>
    </div>

    <!-- ============ PYRAMID ============ -->
    <div class="rr-pyramid">
        <!-- Tier 1: Admin -->
        <div class="rr-tier tier-1">
            <div class="rr-card <?php echo $currentRole === 'admin' ? 'is-current' : ''; ?>" data-role="admin" style="border-color:<?php echo $roles['admin']['color']; ?>30;">
                <i class="fas <?php echo $roles['admin']['icon']; ?>" style="color:<?php echo $roles['admin']['color']; ?>"></i>
                <div class="rc-name" style="color:<?php echo $roles['admin']['color']; ?>"><?php echo $roles['admin']['name']; ?></div>
                <div class="rc-sub">Permanently Unobtainable</div>
            </div>
        </div>
        <!-- Tier 2: Contributor & Designer -->
        <div class="rr-tier tier-2">
            <div class="rr-card <?php echo $currentRole === 'contributor' ? 'is-current' : ''; ?>" data-role="contributor" style="border-color:<?php echo $roles['contributor']['color']; ?>30;">
                <i class="fas <?php echo $roles['contributor']['icon']; ?>" style="color:<?php echo $roles['contributor']['color']; ?>"></i>
                <div class="rc-name" style="color:<?php echo $roles['contributor']['color']; ?>"><?php echo $roles['contributor']['name']; ?></div>
                <div class="rc-sub">Very Helpful Only</div>
            </div>
            <div class="rr-card <?php echo $currentRole === 'designer' ? 'is-current' : ''; ?>" data-role="designer" style="border-color:<?php echo $roles['designer']['color']; ?>30;">
                <i class="fas <?php echo $roles['designer']['icon']; ?>" style="color:<?php echo $roles['designer']['color']; ?>"></i>
                <div class="rc-name" style="color:<?php echo $roles['designer']['color']; ?>"><?php echo $roles['designer']['name']; ?></div>
                <div class="rc-sub">Very Helpful Only</div>
            </div>
        </div>
        <!-- Tier 3: User -->
        <div class="rr-tier tier-3">
            <div class="rr-card <?php echo $currentRole === 'user' ? 'is-current' : ''; ?>" data-role="user" style="border-color:<?php echo $roles['user']['color']; ?>30;">
                <i class="fas <?php echo $roles['user']['icon']; ?>" style="color:<?php echo $roles['user']['color']; ?>"></i>
                <div class="rc-name" style="color:<?php echo $roles['user']['color']; ?>"><?php echo $roles['user']['name']; ?></div>
                <div class="rc-sub">$2 — Obtainable</div>
            </div>
        </div>
        <!-- Tier 4: Community -->
        <div class="rr-tier tier-4">
            <div class="rr-card <?php echo $currentRole === 'community' ? 'is-current' : ''; ?>" data-role="community" style="border-color:<?php echo $roles['community']['color']; ?>30;">
                <i class="fas <?php echo $roles['community']['icon']; ?>" style="color:<?php echo $roles['community']['color']; ?>"></i>
                <div class="rc-name" style="color:<?php echo $roles['community']['color']; ?>"><?php echo $roles['community']['name']; ?></div>
                <div class="rc-sub">Default — Free</div>
            </div>
        </div>
    </div>

    <!-- ============ DETAIL PANEL ============ -->
    <div class="rr-detail" id="detailPanel">
        <div class="rr-detail-head">
            <div class="rr-detail-icon" id="dIcon"></div>
            <div>
                <h2 id="dName"></h2>
                <p id="dDesc"></p>
            </div>
        </div>
        <h3 style="font-size:0.95rem; color:#94a3b8; margin-bottom:8px;"><i class="fas fa-key" style="margin-right:4px;"></i> Privileges</h3>
        <div class="rr-privs-grid" id="dPrivs"></div>
        <div id="dObtain"></div>
        <div class="rr-panel-link" id="dPanel"></div>
    </div>

    <!-- ============ LEGEND ============ -->
    <div class="rr-legend">
        <?php foreach ($roles as $rk => $r): ?>
        <div class="rr-legend-item">
            <div class="rr-legend-dot" style="background:<?php echo $r['color']; ?>"></div>
            <span><?php echo $r['name']; ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============ FEATURE COMPARISON TABLE ============ -->
    <div class="rr-table-wrap">
        <h3><i class="fas fa-table" style="color:#6366f1;"></i> Feature Comparison</h3>
        <table class="rr-table">
            <thead>
                <tr>
                    <th style="text-align:left;">Feature</th>
                    <?php foreach ($featureRoleOrder as $rk): ?>
                    <th style="color:<?php echo $roles[$rk]['color']; ?>;"><?php echo $roles[$rk]['name']; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($features as $feat => $vals): ?>
                <tr>
                    <td><?php echo htmlspecialchars($feat); ?></td>
                    <?php foreach ($vals as $v): ?>
                    <td>
                        <?php if ($v): ?>
                            <i class="fas fa-check t-yes"></i>
                        <?php else: ?>
                            <i class="fas fa-minus t-no"></i>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Nav -->
    <div class="rr-nav">
        <a href="main.php"><i class="fas fa-arrow-left"></i> Back to Main Site</a>
    </div>
</div>

<?php if (file_exists(__DIR__ . '/includes/consent_banner.php')) include_once __DIR__ . '/includes/consent_banner.php'; ?>
<?php if (file_exists(__DIR__ . '/includes/policy_footer.php')) include_once __DIR__ . '/includes/policy_footer.php'; ?>

<script>
const roles = <?php echo json_encode($roles); ?>;
const currentRole = <?php echo json_encode($currentRole); ?>;

const panel = document.getElementById('detailPanel');
const dIcon = document.getElementById('dIcon');
const dName = document.getElementById('dName');
const dDesc = document.getElementById('dDesc');
const dPrivs = document.getElementById('dPrivs');
const dObtain = document.getElementById('dObtain');
const dPanel = document.getElementById('dPanel');

document.querySelectorAll('.rr-card').forEach(card => {
    card.addEventListener('click', function() {
        const key = this.dataset.role;
        showDetail(key);
        document.querySelectorAll('.rr-card').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
    });
});

function showDetail(key) {
    const r = roles[key];
    if (!r) return;

    dIcon.innerHTML = '<i class="fas ' + r.icon + '"></i>';
    dIcon.style.background = r.gradient;

    dName.textContent = r.name;
    dName.style.color = r.color;
    dDesc.textContent = r.description;

    dPrivs.innerHTML = r.privileges.map(p =>
        '<div class="rr-priv"><i class="fas fa-check"></i><span>' + p + '</span></div>'
    ).join('');

    if (r.obtainable) {
        dObtain.innerHTML = '<div class="rr-obtain yes"><i class="fas fa-unlock"></i> This role can be obtained</div>';
    } else {
        dObtain.innerHTML = '<div class="rr-obtain no"><i class="fas fa-lock"></i> This role is not normally obtainable</div>';
    }

    if (r.panel && (currentRole === key || currentRole === 'admin')) {
        dPanel.innerHTML = '<a href="' + r.panel + '"><i class="fas fa-external-link-alt"></i> Go to ' + r.name + ' Panel</a>';
    } else if (r.panel) {
        dPanel.innerHTML = '<p style="color:#475569; font-size:0.85rem; margin-top:12px;"><i class="fas fa-lock" style="margin-right:4px;"></i> Panel access requires ' + r.name + ' role</p>';
    } else {
        dPanel.innerHTML = '';
    }

    panel.classList.add('visible');
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Auto-show current role on load
document.addEventListener('DOMContentLoaded', function() {
    const el = document.querySelector('.rr-card[data-role="' + currentRole + '"]');
    if (el) el.click();
});
</script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
