<?php
/**
 * User Management View
 * Modular user list with search, filters, and actions
 */

// Pagination
$usersPerPage = 20;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $usersPerPage;

// Search/filter
$searchTerm = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';

// Build query
$whereClause = "WHERE 1=1";
$params = [];

if ($searchTerm) {
    $whereClause .= " AND (username LIKE ? OR email LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

if ($roleFilter) {
    $whereClause .= " AND role = ?";
    $params[] = $roleFilter;
}

// Get total count
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM users $whereClause");
    $stmt->execute($params);
    $totalUsers = (int)$stmt->fetchColumn();
    $totalPages = ceil($totalUsers / $usersPerPage);
} catch (Exception $e) {
    $totalUsers = 0;
    $totalPages = 1;
}

// Get users
try {
    $sql = "SELECT id, username, email, role, created_at, last_login, 
                   is_active, is_suspended, current_strike_count, status
            FROM users 
            $whereClause 
            ORDER BY created_at DESC 
            LIMIT $usersPerPage OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}

// Get role counts for filter
try {
    $stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $roleCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $roleCounts = [];
}

// Role colors
$roleColors = [
    'admin' => '#ef4444',
    'contributor' => '#f59e0b',
    'designer' => '#ec4899',
    'user' => '#3b82f6',
    'community' => '#10b981'
];
?>

<!-- User Management Header -->
<div class="content-section">
    <div class="content-section__header">
        <h3 class="content-section__title">
            <i class="fas fa-users"></i> User Management
        </h3>
        <div class="content-section__actions">
            <button class="btn btn--primary btn--sm" onclick="showAddUserModal()">
                <i class="fas fa-user-plus"></i> Add User
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="content-section__body" style="padding-bottom: 0;">
        <div style="display: flex; gap: var(--space-3); margin-bottom: var(--space-4); flex-wrap: wrap;">
            <!-- Search -->
            <div style="flex: 1; min-width: 200px;">
                <input type="text" 
                       id="userSearchInput" 
                       placeholder="Search users..."
                       value="<?php echo htmlspecialchars($searchTerm); ?>"
                       style="width: 100%; padding: var(--space-3); background: var(--color-bg-tertiary); border: 1px solid var(--color-border-medium); border-radius: var(--radius-md); color: var(--color-text-primary);">
            </div>
            
            <!-- Role Filter -->
            <select id="roleFilter" 
                    onchange="applyFilters()"
                    style="padding: var(--space-3); background: var(--color-bg-tertiary); border: 1px solid var(--color-border-medium); border-radius: var(--radius-md); color: var(--color-text-primary);">
                <option value="">All Roles</option>
                <?php foreach ($roleCounts as $role => $count): ?>
                    <option value="<?php echo $role; ?>" <?php echo $roleFilter === $role ? 'selected' : ''; ?>>
                        <?php echo ucfirst($role); ?> (<?php echo $count; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button class="btn btn--ghost btn--sm" onclick="applyFilters()">
                <i class="fas fa-search"></i> Search
            </button>
            
            <?php if ($searchTerm || $roleFilter): ?>
                <a href="?tab=users" class="btn btn--ghost btn--sm">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="content-section">
    <div class="content-section__body" style="padding: 0;">
        <?php if (empty($users)): ?>
            <div style="text-align: center; padding: var(--space-12); color: var(--color-text-muted);">
                <i class="fas fa-users fa-3x" style="margin-bottom: var(--space-4); opacity: 0.5;"></i>
                <p>No users found</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Strikes</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): 
                        $roleColor = $roleColors[$user['role']] ?? '#6b7280';
                        $isSuspended = !empty($user['is_suspended']);
                        $isRestricted = $user['status'] === 'restricted';
                    ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: var(--space-3);">
                                    <div class="user-avatar user-avatar--sm">
                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: var(--font-semibold); color: var(--color-text-primary);">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </div>
                                        <div style="font-size: var(--text-sm); color: var(--color-text-muted);">
                                            ID: <?php echo $user['id']; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: var(--space-2);">
                                    <span style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo $roleColor; ?>;"></span>
                                    <span style="text-transform: capitalize;">
                                        <?php echo htmlspecialchars($user['role']); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php if ($isSuspended): ?>
                                    <span class="badge badge--danger">Suspended</span>
                                <?php elseif ($isRestricted): ?>
                                    <span class="badge badge--warning">Lockdown</span>
                                <?php elseif (empty($user['last_login'])): ?>
                                    <span class="badge badge--info">Never Logged In</span>
                                <?php else: ?>
                                    <span class="badge badge--success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['current_strike_count'] > 0): ?>
                                    <span class="badge badge--warning">
                                        <?php echo $user['current_strike_count']; ?>/3
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--color-text-muted);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: var(--color-text-secondary); font-size: var(--text-sm);">
                                    <?php echo $user['last_login'] ? date('M j, Y', strtotime($user['last_login'])) : 'Never'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['id'] != $currentAdmin['id'] && $user['role'] !== 'admin'): ?>
                                    <button class="btn btn--warning btn--sm" 
                                            onclick="showStrikeModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                            style="margin-right: var(--space-2);">
                                        <i class="fas fa-gavel"></i>
                                    </button>
                                    <button class="btn btn--danger btn--sm" 
                                            onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php elseif ($user['role'] === 'admin'): ?>
                                    <span style="color: var(--color-text-muted); font-style: italic;">Protected</span>
                                <?php else: ?>
                                    <span style="color: var(--color-text-muted); font-style: italic;">You</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: center; align-items: center; gap: var(--space-4); padding: var(--space-6);">
                    <div style="color: var(--color-text-muted); font-size: var(--text-sm);">
                        Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?> 
                        (<?php echo $totalUsers; ?> total)
                    </div>
                    <div style="display: flex; gap: var(--space-2);">
                        <?php if ($currentPage > 1): ?>
                            <a href="?tab=users&page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($searchTerm); ?>&role=<?php echo urlencode($roleFilter); ?>" 
                               class="btn btn--outline btn--sm">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                            <a href="?tab=users&page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&role=<?php echo urlencode($roleFilter); ?>" 
                               class="btn btn--<?php echo $i === $currentPage ? 'primary' : 'outline'; ?> btn--sm">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?tab=users&page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($searchTerm); ?>&role=<?php echo urlencode($roleFilter); ?>" 
                               class="btn btn--outline btn--sm">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Strike Modal -->
<div id="strikeModal" class="modal">
    <div class="modal__backdrop" onclick="closeStrikeModal()"></div>
    <div class="modal__content" style="max-width: 500px;">
        <div class="modal__header">
            <h3 class="modal__title"><i class="fas fa-gavel"></i> Apply Strike</h3>
            <button class="modal__close" onclick="closeStrikeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal__body">
            <p style="margin-bottom: var(--space-4);">
                User: <strong id="strikeUsername"></strong>
            </p>
            
            <input type="hidden" id="strikeUserId">
            
            <div style="margin-bottom: var(--space-4);">
                <label style="display: block; margin-bottom: var(--space-2); color: var(--color-text-secondary);">
                    Rule Violated:
                </label>
                <select id="strikeRule" style="width: 100%; padding: var(--space-3); background: var(--color-bg-tertiary); border: 1px solid var(--color-border-medium); border-radius: var(--radius-md); color: var(--color-text-primary);">
                    <option value="">Select rule...</option>
                    <optgroup label="Respect & Conduct (A)">
                        <option value="A1">A1 - Harassment</option>
                        <option value="A2">A2 - Hate Speech (Zero Tolerance)</option>
                    </optgroup>
                    <optgroup label="Content & Safety (B)">
                        <option value="B1">B1 - NSFW (Lockdown)</option>
                        <option value="B2">B2 - Gore/Extremism (Termination)</option>
                    </optgroup>
                    <optgroup label="Security & Privacy (C)">
                        <option value="C1">C1 - Doxxing (Lockdown)</option>
                        <option value="C2">C2 - Impersonation</option>
                    </optgroup>
                    <optgroup label="Platform Integrity (D)">
                        <option value="D1">D1 - Spamming</option>
                        <option value="D2">D2 - Unauthorized Advertising</option>
                        <option value="D3">D3 - Ban Evasion</option>
                    </optgroup>
                    <optgroup label="Legal (E)">
                        <option value="E1">E1 - Illegal Activity</option>
                    </optgroup>
                </select>
            </div>
            
            <div style="margin-bottom: var(--space-4);">
                <label style="display: block; margin-bottom: var(--space-2); color: var(--color-text-secondary);">
                    Evidence / Description:
                </label>
                <textarea id="strikeEvidence" rows="4" 
                          placeholder="Provide specific evidence of the violation..."
                          style="width: 100%; padding: var(--space-3); background: var(--color-bg-tertiary); border: 1px solid var(--color-border-medium); border-radius: var(--radius-md); color: var(--color-text-primary); resize: vertical;"></textarea>
                <small style="color: var(--color-text-muted); display: block; margin-top: var(--space-1);">
                    This will be shown to the user. Do not include sensitive investigation details.
                </small>
            </div>
        </div>
        <div class="modal__footer">
            <button class="btn btn--ghost" onclick="closeStrikeModal()">Cancel</button>
            <button class="btn btn--danger" onclick="submitStrike()">
                <i class="fas fa-gavel"></i> Apply Strike
            </button>
        </div>
    </div>
</div>

<script>
function applyFilters() {
    const search = document.getElementById('userSearchInput').value;
    const role = document.getElementById('roleFilter').value;
    let url = '?tab=users';
    if (search) url += '&search=' + encodeURIComponent(search);
    if (role) url += '&role=' + encodeURIComponent(role);
    location.href = url;
}

// Enter key to search
document.getElementById('userSearchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') applyFilters();
});

// Strike Modal
function showStrikeModal(userId, username) {
    document.getElementById('strikeUserId').value = userId;
    document.getElementById('strikeUsername').textContent = username;
    document.getElementById('strikeModal').classList.add('active');
}

function closeStrikeModal() {
    document.getElementById('strikeModal').classList.remove('active');
}

function submitStrike() {
    const userId = document.getElementById('strikeUserId').value;
    const ruleId = document.getElementById('strikeRule').value;
    const evidence = document.getElementById('strikeEvidence').value;
    
    if (!ruleId || !evidence) {
        alert('Please select a rule and provide evidence.');
        return;
    }
    
    fetch('/api/strike_user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'user_id=' + encodeURIComponent(userId) 
            + '&rule_id=' + encodeURIComponent(ruleId)
            + '&evidence=' + encodeURIComponent(evidence)
            + '&csrf_token=' + encodeURIComponent('<?php echo $_SESSION['csrf_token']; ?>')
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Strike applied successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to apply strike'));
        }
    })
    .catch(err => {
        alert('Network error. Please try again.');
    });
}

function deleteUser(userId, username) {
    if (!confirm('Are you sure you want to delete ' + username + '?\n\nThis action cannot be undone.')) {
        return;
    }
    
    // Submit delete form
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" value="${userId}">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>
