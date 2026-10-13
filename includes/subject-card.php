<?php
/**
 * Subject / course card.
 *
 * Rendered from the dashboard and the My Subjects page so both stay identical.
 * Expects a $subject row with any of:
 *   id, subject_name, subject_code, icon_class,
 *   section_id, section_code, schedule, room, room_floor,
 *   department_code, department_name, first_name, last_name
 *
 * The card links to the exact course (section) when one is known, so
 * "Go to course" opens that class rather than a generic subject page.
 */

$cardSubjectId = (int) ($subject['id'] ?? 0);
$cardSectionId = (int) ($subject['section_id'] ?? 0);
$cardHref = ($assetPrefix ?? '') . 'pages/subject-detail.php?id=' . $cardSubjectId
    . ($cardSectionId ? '&section=' . $cardSectionId : '');

$cardMeta = [];

$department = trim(
    ($subject['department_code'] ?? '')
    . (!empty($subject['department_code']) && !empty($subject['department_name']) ? ' — ' : '')
    . ($subject['department_name'] ?? '')
);
if ($department !== '') {
    $cardMeta[] = ['landmark', $department];
}

if (!empty($subject['schedule'])) {
    $cardMeta[] = ['clock', $subject['schedule']];
}

if (!empty($subject['room'])) {
    $cardMeta[] = ['door', $subject['room'] . (!empty($subject['room_floor']) ? ' · ' . $subject['room_floor'] : '')];
}

if (!empty($subject['first_name'])) {
    $cardMeta[] = ['user', 'Prof. ' . $subject['first_name'] . ' ' . $subject['last_name']];
}
?>
<div class="subject-card">
    <div class="subject-card-icon <?php echo htmlspecialchars($subject['icon_class'] ?? 'science'); ?>">
        <?php echo icon(subjectIcon($subject['icon_class'] ?? 'science'), 40); ?>
    </div>
    <div class="subject-card-body">
        <div class="subject-card-top">
            <h3 class="subject-card-title"><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
            <?php if (!empty($subject['section_code'])): ?>
            <span class="subject-card-pill">Section <?php echo htmlspecialchars($subject['section_code']); ?></span>
            <?php endif; ?>
        </div>
        <p class="subject-card-section"><?php echo htmlspecialchars($subject['subject_code']); ?></p>

        <?php if ($cardMeta): ?>
        <ul class="subject-card-meta">
            <?php foreach ($cardMeta as $metaItem): ?>
            <li>
                <span class="subject-card-meta-icon"><?php echo icon($metaItem[0], 14); ?></span>
                <span><?php echo htmlspecialchars($metaItem[1]); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <a href="<?php echo htmlspecialchars($cardHref); ?>" class="subject-card-link">GO TO COURSE</a>
    </div>
</div>
