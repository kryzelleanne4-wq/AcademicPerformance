<?php
/**
 * AJAX endpoint: check for schedule conflicts in real time.
 * Called by the schedule picker UI before form submission.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$db = getDB();

$termId       = intval($_POST['term_id'] ?? $_GET['term_id'] ?? 0);
$schedule     = trim($_POST['schedule'] ?? $_GET['schedule'] ?? '');
$instructorId = intval($_POST['instructor_id'] ?? $_GET['instructor_id'] ?? 0);
$blockId      = intval($_POST['block_id'] ?? $_GET['block_id'] ?? 0);
$room         = trim($_POST['room'] ?? $_GET['room'] ?? '');
$excludeId    = intval($_POST['exclude_id'] ?? $_GET['exclude_id'] ?? 0);

if (!$termId || $schedule === '') {
    echo json_encode(['ok' => true, 'conflicts' => []]);
    exit();
}

// Temporarily set $_POST so findScheduleConflicts can read room / instructor / block
$_POST['room']         = $room;
$_POST['instructor_id'] = $instructorId;
$_POST['block_id']     = $blockId;

$conflicts = findScheduleConflicts($db, $termId, $schedule, $excludeId ?: null);

echo json_encode([
    'ok'        => true,
    'conflicts' => $conflicts,
]);
