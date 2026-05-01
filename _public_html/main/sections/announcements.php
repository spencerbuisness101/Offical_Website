<?php
if (!defined('MAIN_PAGE_LOADED')) { http_response_code(403); exit('Direct access not allowed'); }
if (empty($announcements)) return;

$priorityConfig = [
    'critical' => ['label' => 'Critical', 'color' => '#EF4444', 'icon' => 'fa-exclamation-circle'],
    'high'     => ['label' => 'High',     'color' => '#F59E0B', 'icon' => 'fa-exclamation-triangle'],
    'medium'   => ['label' => 'Medium',   'color' => '#7B6EF6', 'icon' => 'fa-info-circle'],
    'low'      => ['label' => 'Low',      'color' => '#64748B', 'icon' => 'fa-bell'],
];
$typeFilters = ['all'=>'All','info'=>'Info','update'=>'Update','warning'=>'Warning','maintenance'=>'Maintenance'];
$tagColors = ['#7B6EF6','#1DFFC4','#FF6BB3','#FBBF24','#22C55E','#F97316','#6366F1'];
$prevFilter = $_COOKIE['ann_filter'] ?? 'all';
if (!isset($typeFilters[$prevFilter])) $prevFilter = 'all';
?>

<section class="mp-ann-section" id="announcements" aria-label="Announcements">
    <div class="mp-ann-header">
        <div class="mp-ann-header-left">
            <i class="fas fa-bullhorn mp-ann-icon" aria-hidden="true"></i>
            <h2>Announcements</h2>
            <?php if (!empty($unread_announcements)): ?>
                <span class="ann-unread-pill" style="margin-left:8px;"><?php echo (int)$unread_announcements; ?> new</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($announcements) > 1): ?>
    <div class="ann-filters" style="padding:0 0 12px;display:flex;gap:6px;flex-wrap:wrap;">
        <?php foreach ($typeFilters as $key => $label): ?>
        <button class="ann-filter <?php echo $key === $prevFilter ? 'active' : ''; ?>" data-filter="<?php echo $key; ?>" onclick="filterMainAnnouncements('<?php echo $key; ?>',this)"><?php echo $label; ?></button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="mp-ann-grid" role="list">
        <?php foreach ($announcements as $ann):
            $p = $ann['priority'] ?? 'medium';
            $pc = $priorityConfig[$p] ?? $priorityConfig['medium'];
            $type = $ann['type'] ?? 'info';
            $annColor = $ann['color'] ?? $pc['color'];
            $isLong = isset($ann['message']) && strlen((string)$ann['message']) > 240;
            $cardId = 'ann-' . (int)$ann['id'];
            $ts = strtotime($ann['created_at'] ?? 'now');
            $diff = time() - $ts;
            if ($diff < 60) $timeStr = 'just now';
            elseif ($diff < 3600) $timeStr = floor($diff / 60) . 'm ago';
            elseif ($diff < 86400) $timeStr = floor($diff / 3600) . 'h ago';
            elseif ($diff < 604800) $timeStr = floor($diff / 86400) . 'd ago';
            else $timeStr = date('M j', $ts);
        ?>
        <article class="mp-ann-card" role="listitem" id="<?php echo $cardId; ?>" data-type="<?php echo htmlspecialchars($type); ?>" style="--ann-accent:<?php echo htmlspecialchars($annColor); ?>;">
            <div class="mp-ann-accent" style="background:<?php echo $pc['color']; ?>;"></div>
            <div class="mp-ann-main">
                <div class="mp-ann-headline">
                    <div class="mp-ann-title-row">
                        <i class="fas <?php echo $pc['icon']; ?> mp-ann-priority-icon" style="color:<?php echo $pc['color']; ?>;" aria-hidden="true"></i>
                        <h3 class="mp-ann-title"><?php echo htmlspecialchars($ann['title'] ?? ''); ?></h3>
                        <span class="mp-ann-priority-badge" style="background:<?php echo $pc['color']; ?>15; color:<?php echo $pc['color']; ?>; border:0.5px solid <?php echo $pc['color']; ?>30;">
                            <?php echo $pc['label']; ?>
                        </span>
                    </div>
                    <button type="button" class="mp-ann-dismiss" onclick="dismissMainAnn(<?php echo (int)$ann['id']; ?>)" title="Dismiss" aria-label="Dismiss"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>

                <div class="mp-ann-body<?php echo $isLong ? ' collapsed' : ''; ?>" id="<?php echo $cardId; ?>-body">
                    <p><?php echo nl2br(htmlspecialchars($ann['message'] ?? '')); ?></p>
                    <?php if ($isLong): ?><div class="mp-ann-fadeout"></div><?php endif; ?>
                </div>

                <?php if ($isLong): ?>
                <button type="button" class="mp-ann-expand-btn" onclick="toggleAnn('<?php echo $cardId; ?>', this)" aria-expanded="false" aria-controls="<?php echo $cardId; ?>-body">
                    <span>Show more</span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </button>
                <?php endif; ?>

                <?php if (!empty($ann['tags'])): ?>
                <div class="mp-ann-tags">
                    <?php $tags = array_map('trim', explode(',', (string)$ann['tags']));
                    foreach (array_slice($tags, 0, 5) as $i => $tag):
                        if ($tag === '') continue;
                        $tc = $tagColors[$i % count($tagColors)]; ?>
                        <span class="mp-ann-tag" style="--tag-color:<?php echo $tc; ?>;">#<?php echo htmlspecialchars($tag); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="mp-ann-footer">
                    <div class="mp-ann-meta">
                        <span><i class="far fa-clock" aria-hidden="true"></i> <?php echo $timeStr; ?></span>
                        <span><i class="far fa-user" aria-hidden="true"></i> <?php echo htmlspecialchars($ann['created_by_name'] ?? 'Admin'); ?></span>
                        <?php if (!empty($ann['expiry_date'])): ?>
                        <span><i class="far fa-calendar" aria-hidden="true"></i> Expires <?php echo date('M j', strtotime($ann['expiry_date'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
