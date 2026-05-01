<?php
/**
 * Designer Panel - Spencer's Website v7.0
 * Submit custom backgrounds for the website. No announcements.
 * Access: Designer + Admin roles only.
 */
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';

header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$access_denied = false;
if ($_SESSION['role'] !== 'designer' && $_SESSION['role'] !== 'admin') {
    $access_denied = true;
}

$user_id = (int)$_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username'] ?? 'User');
$role = $_SESSION['role'] ?? 'community';
$csrfToken = generateCsrfToken();

$db = null;
$myBackgrounds = [];

try {
    $db = (new Database())->getConnection();

    // Table schema is maintained by migrations/016_create_missing_tables.sql
} catch (Exception $e) {
    error_log("Designer panel DB error: " . $e->getMessage());
}

// Handle AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $db) {
    header('Content-Type: application/json');

    $postCsrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($postCsrf)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request token. Refresh the page.']);
        exit;
    }
    if ($access_denied) {
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'submit_background') {
            $imageUrl = trim($_POST['image_url'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                echo json_encode(['success' => false, 'message' => 'Please enter a valid image URL.']);
                exit;
            }

            $parsed = parse_url($imageUrl);
            if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
                echo json_encode(['success' => false, 'message' => 'URL must use http or https.']);
                exit;
            }

            if (empty($title) || strlen($title) < 3 || strlen($title) > 255) {
                echo json_encode(['success' => false, 'message' => 'Title must be 3–255 characters.']);
                exit;
            }

            if (strlen($description) > 500) {
                echo json_encode(['success' => false, 'message' => 'Description must be under 500 characters.']);
                exit;
            }

            // Rate limit: max 10 backgrounds per user
            $countStmt = $db->prepare("SELECT COUNT(*) FROM designer_backgrounds WHERE user_id = ?");
            $countStmt->execute([$user_id]);
            if ((int)$countStmt->fetchColumn() >= 10) {
                echo json_encode(['success' => false, 'message' => 'You can submit up to 10 backgrounds.']);
                exit;
            }

            $imageUrl = htmlspecialchars($imageUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $description = htmlspecialchars($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $stmt = $db->prepare("INSERT INTO designer_backgrounds (user_id, image_url, title, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $imageUrl, $title, $description]);

            echo json_encode(['success' => true, 'message' => 'Background submitted for review!']);
            exit;
        }

        if ($action === 'delete_background') {
            $bgId = (int)($_POST['background_id'] ?? 0);
            $stmt = $db->prepare("SELECT id, status FROM designer_backgrounds WHERE id = ? AND user_id = ?");
            $stmt->execute([$bgId, $user_id]);
            $bg = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bg) {
                echo json_encode(['success' => false, 'message' => 'Background not found.']);
                exit;
            }
            if ($bg['status'] !== 'pending') {
                echo json_encode(['success' => false, 'message' => 'Only pending backgrounds can be deleted.']);
                exit;
            }

            $db->prepare("DELETE FROM designer_backgrounds WHERE id = ? AND user_id = ?")->execute([$bgId, $user_id]);
            echo json_encode(['success' => true, 'message' => 'Background deleted.']);
            exit;
        }

    } catch (Exception $e) {
        error_log("Designer panel error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Try again.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// Load user's backgrounds
if ($db && !$access_denied) {
    try {
        $stmt = $db->prepare("SELECT id, title, status, created_at FROM designer_backgrounds WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $myBackgrounds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Designer panel load error: " . $e->getMessage());
    }
}

$pendingCount = count(array_filter($myBackgrounds, fn($b) => $b['status'] === 'pending'));
$approvedCount = count(array_filter($myBackgrounds, fn($b) => $b['status'] === 'approved'));
$rejectedCount = count(array_filter($myBackgrounds, fn($b) => $b['status'] === 'rejected'));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Designer Panel - Spencer's Website</title>
    <link rel="icon" href="/assets/images/favicon.webp">
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="control_buttons.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/all.min.css">
    <style>
        .panel-wrap { max-width: 960px; margin: 0 auto; padding: 1.5rem; }

        .panel-head { margin-bottom: 2rem; }
        .panel-head h1 {
            font-size: 2rem; font-weight: 800; margin-bottom: 0.3rem;
            color: #ec4899;
        }
        .panel-head p { color: #94a3b8; font-size: 0.95rem; }

        .panel-nav { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .panel-nav a {
            color: #94a3b8; text-decoration: none; padding: 0.4rem 0.9rem;
            border-radius: 8px; font-size: 0.85rem; transition: all 0.2s;
            border: 1px solid transparent;
        }
        .panel-nav a:hover { color: #f0f0f0; background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.08); }

        .stat-row {
            display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;
        }
        .stat-pill {
            padding: 0.5rem 1.1rem; border-radius: 10px; font-size: 0.82rem; font-weight: 600;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .stat-pill.pending { background: rgba(245,158,11,0.1); color: #fbbf24; border-color: rgba(245,158,11,0.2); }
        .stat-pill.approved { background: rgba(16,185,129,0.1); color: #34d399; border-color: rgba(16,185,129,0.2); }
        .stat-pill.rejected { background: rgba(239,68,68,0.1); color: #f87171; border-color: rgba(239,68,68,0.2); }

        /* Submit Form */
        .submit-card {
            background: rgba(15,23,42,0.75); border: 1px solid rgba(236,72,153,0.15);
            border-radius: 14px; padding: 1.5rem; margin-bottom: 2rem;
        }
        .submit-card h2 { font-size: 1.15rem; font-weight: 700; color: #f0f0f0; margin-bottom: 1rem; }

        .field { margin-bottom: 1rem; }
        .field label { display: block; font-size: 0.85rem; font-weight: 600; color: #cbd5e1; margin-bottom: 0.3rem; }
        .field input, .field textarea {
            width: 100%; padding: 0.65rem 0.85rem; background: rgba(15,23,42,0.5);
            border: 1px solid rgba(148,163,184,0.18); border-radius: 8px;
            color: #e2e8f0; font-size: 0.9rem; font-family: inherit;
        }
        .field input:focus, .field textarea:focus { outline: none; border-color: #ec4899; }
        .field textarea { resize: vertical; min-height: 70px; }
        .field .hint { font-size: 0.75rem; color: #64748b; margin-top: 0.2rem; }

        .btn-submit-bg {
            padding: 0.7rem 1.6rem; border: none; border-radius: 10px; font-size: 0.9rem;
            font-weight: 600; cursor: pointer; color: #fff;
            background: linear-gradient(135deg, #ec4899, #db2777); transition: box-shadow 0.2s;
        }
        .btn-submit-bg:hover { box-shadow: 0 4px 18px rgba(236,72,153,0.35); }
        .btn-submit-bg:disabled { opacity: 0.5; cursor: not-allowed; }

        .form-msg { margin-top: 0.5rem; font-size: 0.85rem; min-height: 1.1em; }
        .form-msg.ok { color: #34d399; }
        .form-msg.err { color: #f87171; }

        .preview-thumb {
            width: 100%; max-height: 160px; object-fit: cover; border-radius: 8px;
            margin-top: 0.5rem; display: none; border: 1px solid rgba(255,255,255,0.06);
        }

        /* Backgrounds Grid */
        .bg-list-title { font-size: 1.1rem; font-weight: 700; color: #e2e8f0; margin-bottom: 1rem; }

        .bg-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem; }

        .bg-card {
            background: rgba(15,23,42,0.7); border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px; overflow: hidden; transition: border-color 0.2s, transform 0.2s;
        }
        .bg-card:hover { border-color: rgba(236,72,153,0.3); transform: translateY(-2px); }

        .bg-card-img {
            width: 100%; height: 140px; object-fit: cover; display: block;
            background: rgba(0,0,0,0.3);
        }

        .bg-card-body { padding: 0.85rem; }
        .bg-card-title { font-weight: 700; font-size: 0.95rem; color: #f0f0f0; margin-bottom: 0.25rem; }
        .bg-card-desc { font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.5rem; line-height: 1.4; }

        .bg-card-footer { display: flex; justify-content: space-between; align-items: center; }

        .status-badge {
            padding: 2px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
        }
        .status-badge.pending { background: rgba(245,158,11,0.15); color: #fbbf24; }
        .status-badge.approved { background: rgba(16,185,129,0.15); color: #34d399; }
        .status-badge.rejected { background: rgba(239,68,68,0.15); color: #f87171; }

        .bg-card-date { font-size: 0.7rem; color: #64748b; }

        .btn-delete-bg {
            background: none; border: none; color: #64748b; cursor: pointer;
            font-size: 0.8rem; padding: 2px 6px; border-radius: 4px; transition: color 0.2s;
        }
        .btn-delete-bg:hover { color: #f87171; }

        .admin-note {
            margin-top: 0.5rem; padding: 0.5rem 0.7rem; background: rgba(59,130,246,0.08);
            border-left: 2px solid #3b82f6; border-radius: 0 6px 6px 0;
            font-size: 0.78rem; color: #93c5fd;
        }

        .empty-state { text-align: center; padding: 3rem 1rem; color: #64748b; }
        .empty-state i { font-size: 2.5rem; margin-bottom: 0.8rem; display: block; opacity: 0.25; }

        .access-denied-box {
            max-width: 500px; margin: 4rem auto; text-align: center; padding: 2.5rem;
            background: rgba(15,23,42,0.8); border: 1px solid rgba(239,68,68,0.2);
            border-radius: 14px; color: #f87171;
        }
        .access-denied-box h2 { margin-bottom: 0.5rem; }
        .access-denied-box p { color: #94a3b8; font-size: 0.9rem; }

        @media (max-width: 640px) {
            .bg-grid { grid-template-columns: 1fr; }
            .panel-head h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/identity_bar.php'; ?>

<div class="panel-wrap">
    <nav class="panel-nav">
        <a href="main.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <a href="game.php"><i class="fas fa-gamepad"></i> Games</a>
        <a href="set.php"><i class="fas fa-cog"></i> Settings</a>
    </nav>

    <?php if ($access_denied): ?>
        <div class="access-denied-box">
            <h2><i class="fas fa-lock"></i> Access Denied</h2>
            <p>The Designer Panel is available to users with the <strong>Designer</strong> or <strong>Admin</strong> role.</p>
            <a href="main.php" style="color:#ec4899; display:inline-block; margin-top:1rem;">Back to Dashboard</a>
        </div>
    <?php else: ?>

    <div class="panel-head">
        <h1><i class="fas fa-palette"></i> Designer Panel</h1>
        <p>Submit custom backgrounds for the website. Approved backgrounds become available to all users.</p>
    </div>

    <div class="stat-row">
        <span class="stat-pill pending"><i class="fas fa-clock"></i> <?php echo $pendingCount; ?> Pending</span>
        <span class="stat-pill approved"><i class="fas fa-check"></i> <?php echo $approvedCount; ?> Approved</span>
        <span class="stat-pill rejected"><i class="fas fa-times"></i> <?php echo $rejectedCount; ?> Rejected</span>
    </div>

    <!-- Submit New Background -->
    <div class="submit-card">
        <h2><i class="fas fa-plus-circle"></i> Submit New Background</h2>
        <form id="bgForm" onsubmit="return submitBackground(event)">
            <div class="field">
                <label for="bgUrl">Image URL</label>
                <input type="url" id="bgUrl" placeholder="https://example.com/my-background.jpg" required>
                <p class="hint">Direct link to an image (JPG, PNG, WebP). Use a permanent/reliable host.</p>
                <img id="bgPreview" class="preview-thumb" alt="Preview">
            </div>
            <div class="field">
                <label for="bgTitle">Title</label>
                <input type="text" id="bgTitle" placeholder="Sunset Mountains" minlength="3" maxlength="255" required>
            </div>
            <div class="field">
                <label for="bgDesc">Description <span style="color:#64748b">(optional)</span></label>
                <textarea id="bgDesc" maxlength="500" placeholder="A warm sunset over the Rocky Mountains..."></textarea>
            </div>
            <button type="submit" class="btn-submit-bg" id="btnSubmitBg">
                <i class="fas fa-upload"></i> Submit for Review
            </button>
            <div class="form-msg" id="bgFormMsg"></div>
        </form>
    </div>

    <!-- My Backgrounds -->
    <h3 class="bg-list-title"><i class="fas fa-images"></i> My Submissions (<?php echo count($myBackgrounds); ?>)</h3>

    <?php if (empty($myBackgrounds)): ?>
        <div class="empty-state">
            <i class="fas fa-image"></i>
            <p>You haven't submitted any backgrounds yet. Use the form above to get started.</p>
        </div>
    <?php else: ?>
        <div class="bg-grid">
            <?php foreach ($myBackgrounds as $bg): ?>
                <div class="bg-card" id="bg-card-<?php echo $bg['id']; ?>">
                    <img class="bg-card-img" src="<?php echo htmlspecialchars($bg['image_url']); ?>"
                         alt="<?php echo htmlspecialchars($bg['title']); ?>"
                         onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22140%22><rect fill=%22%231e293b%22 width=%22400%22 height=%22140%22/><text fill=%22%2364748b%22 x=%2250%25%22 y=%2250%25%22 dominant-baseline=%22middle%22 text-anchor=%22middle%22 font-size=%2214%22>Image unavailable</text></svg>'">
                    <div class="bg-card-body">
                        <div class="bg-card-title"><?php echo htmlspecialchars($bg['title']); ?></div>
                        <?php if ($bg['description']): ?>
                            <div class="bg-card-desc"><?php echo htmlspecialchars(substr($bg['description'], 0, 120)); ?><?php echo strlen($bg['description']) > 120 ? '...' : ''; ?></div>
                        <?php endif; ?>
                        <div class="bg-card-footer">
                            <span class="status-badge <?php echo $bg['status']; ?>"><?php echo $bg['status']; ?></span>
                            <span class="bg-card-date"><?php echo date('M j, Y', strtotime($bg['created_at'])); ?></span>
                            <?php if ($bg['status'] === 'pending'): ?>
                                <button class="btn-delete-bg" onclick="deleteBg(<?php echo $bg['id']; ?>)" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($bg['admin_notes']): ?>
                            <div class="admin-note"><strong>Admin:</strong> <?php echo nl2br(htmlspecialchars($bg['admin_notes'])); ?></div>
                        <?php endif; ?>
                        <?php if ($bg['is_active']): ?>
                            <div style="margin-top:0.4rem;font-size:0.75rem;color:#34d399;font-weight:600;">
                                <i class="fas fa-star"></i> Currently Active
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
const csrfToken = '<?php echo htmlspecialchars($csrfToken); ?>';

// Live preview
document.getElementById('bgUrl')?.addEventListener('input', function() {
    const preview = document.getElementById('bgPreview');
    const url = this.value.trim();
    if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
        preview.src = url;
        preview.style.display = 'block';
        preview.onerror = () => { preview.style.display = 'none'; };
    } else {
        preview.style.display = 'none';
    }
});

async function submitBackground(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitBg');
    const msg = document.getElementById('bgFormMsg');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    msg.textContent = '';
    msg.className = 'form-msg';

    const body = new URLSearchParams({
        ajax: '1',
        action: 'submit_background',
        csrf_token: csrfToken,
        image_url: document.getElementById('bgUrl').value.trim(),
        title: document.getElementById('bgTitle').value.trim(),
        description: document.getElementById('bgDesc').value.trim(),
    });

    try {
        const resp = await fetch('designer_panel.php', { method: 'POST', credentials: 'same-origin', body });
        const data = await resp.json();
        msg.textContent = data.message || '';
        msg.className = 'form-msg ' + (data.success ? 'ok' : 'err');
        if (data.success) {
            document.getElementById('bgForm').reset();
            document.getElementById('bgPreview').style.display = 'none';
            setTimeout(() => location.reload(), 1200);
        }
    } catch (err) {
        msg.textContent = 'Network error. Try again.';
        msg.className = 'form-msg err';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-upload"></i> Submit for Review';
    return false;
}

async function deleteBg(id) {
    if (!confirm('Delete this pending background?')) return;
    const body = new URLSearchParams({
        ajax: '1', action: 'delete_background', csrf_token: csrfToken, background_id: id
    });
    try {
        const resp = await fetch('designer_panel.php', { method: 'POST', credentials: 'same-origin', body });
        const data = await resp.json();
        if (data.success) {
            const card = document.getElementById('bg-card-' + id);
            if (card) card.style.display = 'none';
        } else {
            alert(data.message || 'Failed to delete.');
        }
    } catch (err) {
        alert('Network error.');
    }
}
</script>

    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
