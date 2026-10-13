<?php
/**
 * Schedules Management Page (Admin only)
 * Course blocks carry the schedule (days/time), room, instructor, term.
 */

require_once '../includes/functions.php';
requireRole('admin');

$db = getDB();

/**
 * Section codes are derived from the chosen class block now that the separate
 * Block field is gone, so every schedule still gets a readable code (B1, B2...).
 */
function deriveSectionCode(PDO $db, $blockId) {
    $blockId = (int) $blockId;
    if ($blockId <= 0) {
        return '';
    }
    $stmt = $db->prepare("SELECT block_code FROM blocks WHERE id = :id");
    $stmt->execute([':id' => $blockId]);
    $code = $stmt->fetchColumn();
    return ($code === false || $code === null || $code === '') ? '' : 'B' . $code;
}

/** Section codes must stay unique per subject and term. */
function sectionCodeTaken(PDO $db, $subjectId, $termId, $sectionCode, $excludeId = 0) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM course_sections WHERE subject_id = :s AND term_id = :t AND section_code = :c AND id <> :id");
    $stmt->execute([
        ':s'  => (int) $subjectId,
        ':t'  => (int) $termId,
        ':c'  => $sectionCode,
        ':id' => (int) $excludeId
    ]);
    return (int) $stmt->fetchColumn() > 0;
}

// Make sure there is at least one academic term to assign sections to.
$termCount = (int) querySingle("SELECT COUNT(*) FROM academic_terms");
if ($termCount === 0) {
    $year = date('Y');
    $nextYear = $year + 1;
    $stmt = $db->prepare("INSERT INTO academic_terms (term_code, term_name, academic_year, start_date, end_date, is_current) VALUES (:c, :n, :y, :s, :e, 1)");
    $stmt->execute([
        ':c' => 'T' . $year . '-1',
        ':n' => 'First Semester',
        ':y' => $year . '-' . $nextYear,
        ':s' => $year . '-08-01',
        ':e' => $nextYear . '-01-31'
    ]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_term':
            $term_code = sanitize($_POST['term_code'] ?? '');
            $term_name = sanitize($_POST['term_name'] ?? '');
            $academic_year = sanitize($_POST['academic_year'] ?? '');
            $start_date = sanitize($_POST['start_date'] ?? '');
            $end_date = sanitize($_POST['end_date'] ?? '');

            if ($term_code === '' || $term_name === '' || $academic_year === '' || !$start_date || !$end_date) {
                setFlash('All term fields are required.', 'error');
            } else {
                try {
                    $db->prepare("UPDATE academic_terms SET is_current = 0")->execute();
                    $stmt = $db->prepare("INSERT INTO academic_terms (term_code, term_name, academic_year, start_date, end_date, is_current) VALUES (:c, :n, :y, :s, :e, 1)");
                    $stmt->execute([':c' => $term_code, ':n' => $term_name, ':y' => $academic_year, ':s' => $start_date, ':e' => $end_date]);
                    setFlash('Term "' . $term_name . '" added and set as current.');
                } catch (Exception $e) {
                    setFlash('Error adding term: ' . $e->getMessage(), 'error');
                }
            }
            header('Location: schedules.php');
            exit();
            break;

        case 'add_block':
            $subject_id = intval($_POST['subject_id'] ?? 0);
            $instructor_id = intval($_POST['instructor_id'] ?? 0);
            $term_id = intval($_POST['term_id'] ?? 0);
            $block_id = intval($_POST['block_id'] ?? 0);
            $room = sanitize($_POST['room'] ?? '');
            $schedule = sanitize($_POST['schedule'] ?? '');
            $capacity = intval($_POST['capacity'] ?? 0);
            $section_code = deriveSectionCode($db, $block_id);

            if (!$subject_id || !$instructor_id || !$term_id || $section_code === '') {
                setFlash('Subject, instructor, term and class block are required.', 'error');
            } elseif (sectionCodeTaken($db, $subject_id, $term_id, $section_code)) {
                setFlash('This subject already has a class for that block in this term. Edit that schedule instead.', 'error');
            } else {
                // Check for schedule conflicts
                $conflicts = [];
                if ($schedule) {
                    $conflicts = findScheduleConflicts($db, $term_id, $schedule);
                }

                if (!empty($conflicts)) {
                    $msgs = [];
                    foreach ($conflicts as $c) { $msgs[] = $c['message']; }
                    setFlash('Schedule conflict detected: ' . implode(' | ', $msgs), 'error');
                } else {
                    try {
                        $stmt = $db->prepare("INSERT INTO course_sections (subject_id, instructor_id, term_id, block_id, section_code, room, schedule, capacity) VALUES (:s, :i, :t, :b, :c, :r, :sch, :cap)");
                        $stmt->execute([
                            ':s'   => $subject_id,
                            ':i'   => $instructor_id,
                            ':t'   => $term_id,
                            ':b'   => $block_id ?: null,
                            ':c'   => $section_code,
                            ':r'   => $room ?: null,
                            ':sch' => $schedule ?: null,
                            ':cap' => $capacity ?: null
                        ]);
                        setFlash('Schedule created successfully!');
                    } catch (Exception $e) {
                        setFlash('Error creating schedule: ' . $e->getMessage(), 'error');
                    }
                }
            }
            header('Location: schedules.php');
            exit();
            break;

        case 'update_block':
            $id = intval($_POST['id'] ?? 0);
            $subject_id = intval($_POST['subject_id'] ?? 0);
            $instructor_id = intval($_POST['instructor_id'] ?? 0);
            $term_id = intval($_POST['term_id'] ?? 0);
            $block_id = intval($_POST['block_id'] ?? 0);
            $room = sanitize($_POST['room'] ?? '');
            $schedule = sanitize($_POST['schedule'] ?? '');
            $capacity = intval($_POST['capacity'] ?? 0);
            $status = sanitize($_POST['status'] ?? 'Open');

            $section_code = deriveSectionCode($db, $block_id);
            if ($section_code === '') {
                // Rows created before the Block field was removed keep their code.
                $existing = $db->prepare("SELECT section_code FROM course_sections WHERE id = :id");
                $existing->execute([':id' => $id]);
                $section_code = (string) $existing->fetchColumn();
            }

            $validStatuses = ['Open', 'Closed', 'Completed', 'Cancelled'];
            if (!in_array($status, $validStatuses, true)) {
                $status = 'Open';
            }

            if (!$id || !$subject_id || !$instructor_id || !$term_id || $section_code === '') {
                setFlash('Subject, instructor, term and class block are required.', 'error');
            } elseif (sectionCodeTaken($db, $subject_id, $term_id, $section_code, $id)) {
                setFlash('Another class for this subject already uses that block in this term.', 'error');
            } else {
                // Check for schedule conflicts (exclude self)
                $conflicts = [];
                if ($schedule) {
                    $conflicts = findScheduleConflicts($db, $term_id, $schedule, $id);
                }

                if (!empty($conflicts)) {
                    $msgs = [];
                    foreach ($conflicts as $c) { $msgs[] = $c['message']; }
                    setFlash('Schedule conflict detected: ' . implode(' | ', $msgs), 'error');
                } else {
                    try {
                        $stmt = $db->prepare("UPDATE course_sections SET subject_id = :s, instructor_id = :i, term_id = :t, block_id = :b, section_code = :c, room = :r, schedule = :sch, capacity = :cap, status = :st, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                        $stmt->execute([
                            ':s'   => $subject_id,
                            ':i'   => $instructor_id,
                            ':t'   => $term_id,
                            ':b'   => $block_id ?: null,
                            ':c'   => $section_code,
                            ':r'   => $room ?: null,
                            ':sch' => $schedule ?: null,
                            ':cap' => $capacity ?: null,
                            ':st'  => $status,
                            ':id'  => $id
                        ]);
                        setFlash('Schedule updated successfully!');
                    } catch (Exception $e) {
                        setFlash('Error updating schedule: ' . $e->getMessage(), 'error');
                    }
                }
            }
            header('Location: schedules.php');
            exit();
            break;

        case 'delete_block':
            $id = intval($_POST['id'] ?? 0);
            try {
                $db->prepare("DELETE FROM course_sections WHERE id = :id")->execute([':id' => $id]);
                setFlash('Schedule deleted.');
            } catch (Exception $e) {
                setFlash('Cannot delete schedule: ' . $e->getMessage(), 'error');
            }
            header('Location: schedules.php');
            exit();
            break;
    }
}

$terms = $db->query("SELECT * FROM academic_terms ORDER BY is_current DESC, start_date DESC")->fetchAll();
$subjects = $db->query("SELECT * FROM subjects WHERE is_active = 1 ORDER BY subject_code")->fetchAll();
$instructors = $db->query("SELECT * FROM instructors WHERE status = 'Active' ORDER BY last_name")->fetchAll();
$blocks = $db->query("
    SELECT b.*, d.department_code, d.department_name
    FROM blocks b
    JOIN departments d ON b.department_id = d.id
    WHERE b.is_active = 1
    ORDER BY d.department_name, b.year_level, b.block_code
")->fetchAll();

function renderClassBlockOptions(array $blocks) {
    $grouped = [];
    foreach ($blocks as $block) {
        $grouped[$block['department_code']][] = $block;
    }
    foreach ($grouped as $deptCode => $deptBlocks) {
        echo '<optgroup label="' . htmlspecialchars($deptCode . ' — ' . $deptBlocks[0]['department_name']) . '">';
        foreach ($deptBlocks as $block) {
            $label = yearOrdinal($block['year_level']) . ' Year · Block ' . $block['block_code'] . ' (' . $block['block_name'] . ')';
            echo '<option value="' . (int) $block['id'] . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</optgroup>';
    }
}

// Rooms offered in the schedule modal (active rooms, grouped by floor).
$rooms = $db->query("SELECT * FROM rooms WHERE is_active = 1")->fetchAll();
usort($rooms, function ($a, $b) {
    $floorDiff = floorSortIndex($a['floor'] ?? '') <=> floorSortIndex($b['floor'] ?? '');
    return $floorDiff !== 0 ? $floorDiff : strcasecmp($a['room_code'], $b['room_code']);
});

function renderRoomOptions(array $rooms) {
    $grouped = [];
    foreach ($rooms as $room) {
        $floor = ($room['floor'] !== null && $room['floor'] !== '') ? $room['floor'] : 'Not assigned to a floor';
        $grouped[$floor][] = $room;
    }
    uksort($grouped, function ($a, $b) {
        return floorSortIndex($a) <=> floorSortIndex($b);
    });
    foreach ($grouped as $floor => $floorRooms) {
        echo '<optgroup label="' . htmlspecialchars($floor) . '">';
        foreach ($floorRooms as $room) {
            $label = $room['room_code'] . ($room['capacity'] ? ' · seats ' . (int) $room['capacity'] : '');
            echo '<option value="' . htmlspecialchars($room['room_code'], ENT_QUOTES) . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</optgroup>';
    }
}

// Existing bookings, used by the picker to grey out times already taken by the
// selected room or instructor within the selected term.
$bookingRows = $db->query("
    SELECT id, term_id, room, instructor_id, schedule
    FROM course_sections
    WHERE schedule IS NOT NULL AND TRIM(schedule) != ''
      AND status NOT IN ('Cancelled', 'Completed')
")->fetchAll();
$bookings = [];
foreach ($bookingRows as $bookingRow) {
    $parsedBooking = parseSchedule($bookingRow['schedule']);
    if (!$parsedBooking) {
        continue;
    }
    $bookingSessions = $parsedBooking['sessions'] ?? [$parsedBooking];
    foreach ($bookingSessions as $bookingSession) {
        $bookings[] = [
            'id'            => (int) $bookingRow['id'],
            'term_id'       => (int) $bookingRow['term_id'],
            'room'          => $bookingRow['room'] ?? '',
            'instructor_id' => (int) $bookingRow['instructor_id'],
            'days'          => array_values($bookingSession['days']),
            'start'         => (int) $bookingSession['start_minutes'],
            'end'           => (int) $bookingSession['end_minutes'],
        ];
    }
}

$sections = $db->query("
    SELECT cs.*, sub.subject_code, sub.subject_name, ins.first_name, ins.last_name,
           t.term_name, t.academic_year,
           d.department_code, b.year_level AS block_year_level, b.block_code AS block_code,
           (SELECT COUNT(*) FROM enrollments e WHERE e.section_id = cs.id AND e.status = 'Enrolled') AS enrolled_count
    FROM course_sections cs
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN instructors ins ON cs.instructor_id = ins.id
    JOIN academic_terms t ON cs.term_id = t.id
    LEFT JOIN blocks b ON cs.block_id = b.id
    LEFT JOIN departments d ON b.department_id = d.id
    ORDER BY t.is_current DESC, sub.subject_code, cs.section_code
")->fetchAll();

$pageTitle = 'Schedules';
include '../includes/header.php';
displayFlash();
?>

<main>
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2><?php echo icon('calendar', 24); ?> Class Blocks</h2>
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-secondary" onclick="document.getElementById('addTermModal').style.display='block'"><?php echo icon('plus', 14); ?> Add Term</button>
                <button class="btn btn-primary" onclick="document.getElementById('addBlockModal').style.display='block'"><?php echo icon('plus', 14); ?> Add Block</button>
            </div>
        </div>

        <div class="table-search-bar">
            <div class="table-search-wrapper">
                <span class="search-icon"><?php echo icon('search', 16); ?></span>
                <input type="text" id="scheduleSearch" placeholder="Search by subject, instructor, block, room...">
            </div>
            <span class="search-count"></span>
        </div>

        <table data-pagination data-page-size="8" id="scheduleTable">
            <thead>
                <tr>
                    <th>Block</th>
                    <th>Class Block</th>
                    <th>Subject</th>
                    <th>Instructor</th>
                    <th>Term</th>
                    <th>Schedule</th>
                    <th>Room</th>
                    <th>Capacity</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sections as $section): ?>
                <tr data-search="<?php echo htmlspecialchars(strtolower($section['section_code'] . ' ' . $section['subject_code'] . ' ' . $section['subject_name'] . ' ' . $section['first_name'] . ' ' . $section['last_name'] . ' ' . ($section['block_code'] ?? '') . ' ' . ($section['schedule'] ?? '') . ' ' . ($section['room'] ?? ''))); ?>">
                    <td data-label="Block"><code><?php echo htmlspecialchars($section['section_code']); ?></code></td>
                    <td data-label="Class Block"><?php echo htmlspecialchars($section['block_id'] ? blockLabel($section['department_code'], $section['block_year_level'], $section['block_code']) : '—'); ?></td>
                    <td data-label="Subject"><?php echo htmlspecialchars($section['subject_code'] . ' - ' . $section['subject_name']); ?></td>
                    <td data-label="Instructor"><?php echo htmlspecialchars($section['first_name'] . ' ' . $section['last_name']); ?></td>
                    <td data-label="Term"><?php echo htmlspecialchars($section['term_name'] . ' ' . $section['academic_year']); ?></td>
                    <td data-label="Schedule"><?php echo htmlspecialchars($section['schedule'] ?? '—'); ?></td>
                    <td data-label="Room"><?php echo htmlspecialchars($section['room'] ?? '—'); ?></td>
                    <td data-label="Capacity">
                        <?php if ($section['capacity']): ?>
                        <?php
                            $cap = (int) $section['capacity'];
                            $enrolled = (int) $section['enrolled_count'];
                            $pct = $cap > 0 ? min(($enrolled / $cap) * 100, 100) : 0;
                            $remaining = max($cap - $enrolled, 0);
                            if ($pct >= 100) {
                                $capClass = 'cap-full';
                                $capLabel = 'Full';
                            } elseif ($pct >= 80) {
                                $capClass = 'cap-warning';
                                $capLabel = 'Almost Full';
                            } elseif ($pct >= 50) {
                                $capClass = 'cap-moderate';
                                $capLabel = $remaining . ' left';
                            } else {
                                $capClass = 'cap-ok';
                                $capLabel = $remaining . ' left';
                            }
                        ?>
                        <div class="capacity-cell">
                            <div class="capacity-numbers">
                                <strong><?php echo $enrolled; ?></strong> / <?php echo $cap; ?>
                                <span class="capacity-badge <?php echo $capClass; ?>"><?php echo $capLabel; ?></span>
                            </div>
                            <div class="capacity-progress">
                                <div class="capacity-progress-fill <?php echo $capClass; ?>" style="width: <?php echo $pct; ?>%;"></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <span style="color: var(--ink-muted);"><?php echo $section['enrolled_count']; ?> enrolled (no limit)</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Status">
                        <span class="attendance-badge status-<?php echo $section['status'] === 'Open' ? 'present' : 'absent'; ?>">
                            <?php echo htmlspecialchars($section['status']); ?>
                        </span>
                    </td>
                    <td data-label="Actions">
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?php echo $section['id']; ?>"
                                data-block-code="<?php echo htmlspecialchars($section['section_code'], ENT_QUOTES); ?>"
                                data-class-block="<?php echo htmlspecialchars($section['block_id'] ? blockLabel($section['department_code'], $section['block_year_level'], $section['block_code']) : '', ENT_QUOTES); ?>"
                                data-subject="<?php echo htmlspecialchars($section['subject_code'] . ' - ' . $section['subject_name'], ENT_QUOTES); ?>"
                                data-instructor="<?php echo htmlspecialchars($section['first_name'] . ' ' . $section['last_name'], ENT_QUOTES); ?>"
                                data-term="<?php echo htmlspecialchars($section['term_name'] . ' ' . $section['academic_year'], ENT_QUOTES); ?>"
                                data-schedule="<?php echo htmlspecialchars($section['schedule'] ?? '', ENT_QUOTES); ?>"
                                data-room="<?php echo htmlspecialchars($section['room'] ?? '', ENT_QUOTES); ?>"
                                data-capacity="<?php echo $section['capacity'] ?: ''; ?>"
                                data-status="<?php echo htmlspecialchars($section['status'], ENT_QUOTES); ?>"
                                data-enrolled="<?php echo $section['enrolled_count']; ?>"
                                onclick="viewBlock(this)">View</button>
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?php echo $section['id']; ?>"
                                data-subject-id="<?php echo (int) $section['subject_id']; ?>"
                                data-instructor-id="<?php echo (int) $section['instructor_id']; ?>"
                                data-term-id="<?php echo (int) $section['term_id']; ?>"
                                data-block-id="<?php echo (int) $section['block_id']; ?>"
                                data-room="<?php echo htmlspecialchars($section['room'] ?? '', ENT_QUOTES); ?>"
                                data-schedule="<?php echo htmlspecialchars($section['schedule'] ?? '', ENT_QUOTES); ?>"
                                data-capacity="<?php echo $section['capacity'] ?: ''; ?>"
                                data-status="<?php echo htmlspecialchars($section['status'], ENT_QUOTES); ?>"
                                onclick="editBlock(this)">Edit</button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this block?')">
                            <input type="hidden" name="action" value="delete_block">
                            <input type="hidden" name="id" value="<?php echo $section['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Add Block Modal -->
<div id="addBlockModal" class="modal-overlay" style="display: none;">
    <div class="modal-box modal-schedule">
        <h2><?php echo icon('calendar', 20); ?> Add Class Block</h2>
        <form method="POST" id="addBlockForm">
            <input type="hidden" name="action" value="add_block">
            <input type="hidden" name="schedule" id="addScheduleHidden">

            <div class="form-row">
                <div class="form-group">
                    <label>Subject</label>
                    <select name="subject_id" class="form-control" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>">
                            <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Instructor</label>
                    <select name="instructor_id" class="form-control" id="addInstructorId" onchange="updateSchedulePreview('add')" required>
                        <option value="">-- Select Instructor --</option>
                        <?php foreach ($instructors as $instructor): ?>
                        <option value="<?php echo $instructor['id']; ?>">
                            <?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name'] . ' (' . $instructor['employee_id'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Term</label>
                    <select name="term_id" class="form-control" id="addTermId" onchange="updateSchedulePreview('add')" required>
                        <?php foreach ($terms as $term): ?>
                        <option value="<?php echo $term['id']; ?>" <?php echo $term['is_current'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($term['term_name'] . ' ' . $term['academic_year'] . ($term['is_current'] ? ' (Current)' : '')); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Class Block</label>
                    <select name="block_id" class="form-control" id="addBlockId" onchange="updateSchedulePreview('add')" required>
                        <option value="">-- Select Class Block --</option>
                        <?php renderClassBlockOptions($blocks); ?>
                    </select>
                    <span class="form-hint">The block number is taken from this class block.</span>
                </div>
            </div>

            <div class="form-row" style="margin-top: 16px;">
                <div class="form-group">
                    <label>Room</label>
                    <select name="room" class="form-control" id="addRoom" onchange="updateSchedulePreview('add')">
                        <option value="">-- Select Room --</option>
                        <?php renderRoomOptions($rooms); ?>
                    </select>
                    <div class="room-conflict-warning" id="addRoomConflict">This room may have a scheduling conflict.</div>
                </div>
                <div class="form-group">
                    <label>Capacity</label>
                    <input type="number" name="capacity" class="form-control" min="1" placeholder="e.g. 40">
                </div>
            </div>

            <!-- Schedule Picker -->
            <div class="schedule-picker" id="addSchedulePicker">
                <label class="schedule-picker-label">Schedule</label>
                <span class="schedule-picker-hint">Pick the room above first, then add one or more class sessions. Times already booked for that room or instructor are greyed out.</span>

                <div id="addSessions">
                    <!-- Session 1 -->
                    <div class="schedule-session" data-session="0">
                        <div class="schedule-session-header">
                            <span class="schedule-session-number">Session 1</span>
                        </div>

                        <div class="day-shortcuts">
                            <button type="button" class="day-shortcut" data-days="0,2,4" onclick="setDayShortcut(this, 0, 'add')">MWF</button>
                            <button type="button" class="day-shortcut" data-days="1,3" onclick="setDayShortcut(this, 0, 'add')">TTh</button>
                            <button type="button" class="day-shortcut" data-days="0,1,2,3,4" onclick="setDayShortcut(this, 0, 'add')">Mon-Fri</button>
                            <button type="button" class="day-shortcut" data-days="5" onclick="setDayShortcut(this, 0, 'add')">Saturday</button>
                        </div>

                        <div class="day-chips" data-session-days="0">
                            <button type="button" class="day-chip" data-day="0" onclick="toggleDay(this, 0, 'add')">Mon</button>
                            <button type="button" class="day-chip" data-day="1" onclick="toggleDay(this, 0, 'add')">Tue</button>
                            <button type="button" class="day-chip" data-day="2" onclick="toggleDay(this, 0, 'add')">Wed</button>
                            <button type="button" class="day-chip" data-day="3" onclick="toggleDay(this, 0, 'add')">Thu</button>
                            <button type="button" class="day-chip" data-day="4" onclick="toggleDay(this, 0, 'add')">Fri</button>
                            <button type="button" class="day-chip" data-day="5" onclick="toggleDay(this, 0, 'add')">Sat</button>
                        </div>
                        <div class="day-chips-error" data-session-day-error="0">Please select at least one day.</div>

                        <div class="schedule-time-row">
                            <div class="time-select">
                                <label style="font-size:12px;font-weight:600;color:var(--ink-muted);margin-bottom:4px;display:block;">Start Time</label>
                                <select class="form-control" data-session-start="0" onchange="updateSchedulePreview('add')">
                                    <?php
                                    for ($h = 6; $h <= 21; $h++) {
                                        foreach ([0, 30] as $m) {
                                            if ($h === 21 && $m > 0) break;
                                            $val = str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT);
                                            $h12 = $h % 12 ?: 12;
                                            $ampm = $h < 12 ? 'AM' : 'PM';
                                            $label = $h12 . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ' ' . $ampm;
                                            echo '<option value="' . $val . '">' . $label . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <span class="time-separator">to</span>
                            <div class="time-select">
                                <label style="font-size:12px;font-weight:600;color:var(--ink-muted);margin-bottom:4px;display:block;">End Time</label>
                                <select class="form-control" data-session-end="0" onchange="updateSchedulePreview('add')">
                                    <?php
                                    for ($h = 6; $h <= 21; $h++) {
                                        foreach ([0, 30] as $m) {
                                            if ($h === 21 && $m > 0) break;
                                            $val = str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT);
                                            $h12 = $h % 12 ?: 12;
                                            $ampm = $h < 12 ? 'AM' : 'PM';
                                            $label = $h12 . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ' ' . $ampm;
                                            echo '<option value="' . $val . '"' . ($h === 9 && $m === 30 ? ' selected' : '') . '>' . $label . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="time-validation" data-session-time-error="0">End time must be after start time.</div>
                    </div>
                </div>

                <button type="button" class="btn btn-secondary btn-sm" onclick="addSession('add')" style="margin-top: 8px;">
                    <?php echo icon('plus', 14); ?> Add Another Session
                </button>

                <!-- Preview -->
                <div class="schedule-preview" style="margin-top: 12px;">
                    <span class="schedule-preview-label">Generated:</span>
                    <span class="schedule-preview-value empty" id="addSchedulePreview">Select days and time above</span>
                </div>

                <!-- Conflict alert -->
                <div class="conflict-alert" id="addConflictAlert">
                    <span class="conflict-alert-icon">⚠</span>
                    <div>
                        <strong>Schedule Conflict Detected</strong>
                        <ul class="conflict-alert-list" id="addConflictList"></ul>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Schedule</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addBlockModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- View Block Modal -->
<div id="viewBlockModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('calendar', 20); ?> Block Details</h2>            <div class="detail-list">
            <div class="detail-row"><span class="detail-label">Block</span><span class="detail-value" id="viewBlockCode"></span></div>
            <div class="detail-row"><span class="detail-label">Class Block</span><span class="detail-value" id="viewSecClassBlock"></span></div>
            <div class="detail-row"><span class="detail-label">Subject</span><span class="detail-value" id="viewSecSubject"></span></div>
            <div class="detail-row"><span class="detail-label">Instructor</span><span class="detail-value" id="viewSecInstructor"></span></div>
            <div class="detail-row"><span class="detail-label">Term</span><span class="detail-value" id="viewSecTerm"></span></div>
            <div class="detail-row"><span class="detail-label">Schedule</span><span class="detail-value" id="viewSecSchedule"></span></div>
            <div class="detail-row"><span class="detail-label">Room</span><span class="detail-value" id="viewSecRoom"></span></div>
            <div class="detail-row"><span class="detail-label">Capacity</span><span class="detail-value" id="viewSecCapacity"></span></div>
            <div class="detail-row"><span class="detail-label">Enrolled</span><span class="detail-value" id="viewSecEnrolled"></span></div>
            <div class="detail-row"><span class="detail-label">Availability</span><span class="detail-value" id="viewSecAvail"></span></div>
            <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value" id="viewSecStatus"></span></div>
        </div>
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('viewBlockModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<!-- Edit Block Modal -->
<div id="editBlockModal" class="modal-overlay" style="display: none;">
    <div class="modal-box modal-schedule">
        <h2><?php echo icon('pen-line', 20); ?> Edit Schedule</h2>
        <form method="POST" id="editBlockForm">
            <input type="hidden" name="action" value="update_block">
            <input type="hidden" name="id" id="editSecId">
            <input type="hidden" name="schedule" id="editScheduleHidden">

            <div class="form-row">
                <div class="form-group">
                    <label>Subject</label>
                    <select name="subject_id" id="editSecSubject" class="form-control" required>
                        <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>">
                            <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Instructor</label>
                    <select name="instructor_id" id="editSecInstructor" class="form-control" onchange="updateSchedulePreview('edit')" required>
                        <?php foreach ($instructors as $instructor): ?>
                        <option value="<?php echo $instructor['id']; ?>">
                            <?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name'] . ' (' . $instructor['employee_id'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Term</label>
                    <select name="term_id" id="editSecTerm" class="form-control" onchange="updateSchedulePreview('edit')" required>
                        <?php foreach ($terms as $term): ?>
                        <option value="<?php echo $term['id']; ?>">
                            <?php echo htmlspecialchars($term['term_name'] . ' ' . $term['academic_year'] . ($term['is_current'] ? ' (Current)' : '')); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Class Block</label>
                    <select name="block_id" id="editSecBlock" class="form-control" onchange="updateSchedulePreview('edit')" required>
                        <option value="">-- Select Class Block --</option>
                        <?php renderClassBlockOptions($blocks); ?>
                    </select>
                    <span class="form-hint">The block number is taken from this class block.</span>
                </div>
            </div>

            <div class="form-row" style="margin-top: 16px;">
                <div class="form-group">
                    <label>Room</label>
                    <select name="room" id="editSecRoom" class="form-control" onchange="updateSchedulePreview('edit')">
                        <option value="">-- Select Room --</option>
                        <?php renderRoomOptions($rooms); ?>
                    </select>
                    <div class="room-conflict-warning" id="editRoomConflict">This room may have a scheduling conflict.</div>
                </div>
                <div class="form-group">
                    <label>Capacity</label>
                    <input type="number" name="capacity" id="editSecCapacity" class="form-control" min="1" placeholder="e.g. 40">
                </div>
            </div>

            <!-- Schedule Picker -->
            <div class="schedule-picker" id="editSchedulePicker">
                <label class="schedule-picker-label">Schedule</label>
                <span class="schedule-picker-hint">Pick the room above first, then add one or more class sessions. Times already booked for that room or instructor are greyed out.</span>

                <div id="editSessions">
                    <!-- Session 1 -->
                    <div class="schedule-session" data-session="0">
                        <div class="schedule-session-header">
                            <span class="schedule-session-number">Session 1</span>
                        </div>

                        <div class="day-shortcuts">
                            <button type="button" class="day-shortcut" data-days="0,2,4" onclick="setDayShortcut(this, 0, 'edit')">MWF</button>
                            <button type="button" class="day-shortcut" data-days="1,3" onclick="setDayShortcut(this, 0, 'edit')">TTh</button>
                            <button type="button" class="day-shortcut" data-days="0,1,2,3,4" onclick="setDayShortcut(this, 0, 'edit')">Mon-Fri</button>
                            <button type="button" class="day-shortcut" data-days="5" onclick="setDayShortcut(this, 0, 'edit')">Saturday</button>
                        </div>

                        <div class="day-chips" data-session-days="0">
                            <button type="button" class="day-chip" data-day="0" onclick="toggleDay(this, 0, 'edit')">Mon</button>
                            <button type="button" class="day-chip" data-day="1" onclick="toggleDay(this, 0, 'edit')">Tue</button>
                            <button type="button" class="day-chip" data-day="2" onclick="toggleDay(this, 0, 'edit')">Wed</button>
                            <button type="button" class="day-chip" data-day="3" onclick="toggleDay(this, 0, 'edit')">Thu</button>
                            <button type="button" class="day-chip" data-day="4" onclick="toggleDay(this, 0, 'edit')">Fri</button>
                            <button type="button" class="day-chip" data-day="5" onclick="toggleDay(this, 0, 'edit')">Sat</button>
                        </div>
                        <div class="day-chips-error" data-session-day-error="0">Please select at least one day.</div>

                        <div class="schedule-time-row">
                            <div class="time-select">
                                <label style="font-size:12px;font-weight:600;color:var(--ink-muted);margin-bottom:4px;display:block;">Start Time</label>
                                <select class="form-control" data-session-start="0" onchange="updateSchedulePreview('edit')">
                                    <?php
                                    for ($h = 6; $h <= 21; $h++) {
                                        foreach ([0, 30] as $m) {
                                            if ($h === 21 && $m > 0) break;
                                            $val = str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT);
                                            $h12 = $h % 12 ?: 12;
                                            $ampm = $h < 12 ? 'AM' : 'PM';
                                            $label = $h12 . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ' ' . $ampm;
                                            echo '<option value="' . $val . '">' . $label . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <span class="time-separator">to</span>
                            <div class="time-select">
                                <label style="font-size:12px;font-weight:600;color:var(--ink-muted);margin-bottom:4px;display:block;">End Time</label>
                                <select class="form-control" data-session-end="0" onchange="updateSchedulePreview('edit')">
                                    <?php
                                    for ($h = 6; $h <= 21; $h++) {
                                        foreach ([0, 30] as $m) {
                                            if ($h === 21 && $m > 0) break;
                                            $val = str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT);
                                            $h12 = $h % 12 ?: 12;
                                            $ampm = $h < 12 ? 'AM' : 'PM';
                                            $label = $h12 . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ' ' . $ampm;
                                            echo '<option value="' . $val . '">' . $label . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="time-validation" data-session-time-error="0">End time must be after start time.</div>
                    </div>
                </div>

                <button type="button" class="btn btn-secondary btn-sm" onclick="addSession('edit')" style="margin-top: 8px;">
                    <?php echo icon('plus', 14); ?> Add Another Session
                </button>

                <!-- Preview -->
                <div class="schedule-preview" style="margin-top: 12px;">
                    <span class="schedule-preview-label">Generated:</span>
                    <span class="schedule-preview-value empty" id="editSchedulePreview">Select days and time above</span>
                </div>

                <!-- Conflict alert -->
                <div class="conflict-alert" id="editConflictAlert">
                    <span class="conflict-alert-icon">⚠</span>
                    <div>
                        <strong>Schedule Conflict Detected</strong>
                        <ul class="conflict-alert-list" id="editConflictList"></ul>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="editSecStatus" class="form-control">
                        <?php foreach (['Open', 'Closed', 'Completed', 'Cancelled'] as $status): ?>
                        <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"></div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Changes</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('editBlockModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Term Modal -->
<div id="addTermModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2>Add Academic Term</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_term">

            <div class="form-row">
                <div class="form-group">
                    <label>Term Code</label>
                    <input type="text" name="term_code" class="form-control" placeholder="e.g. T2026-2" required>
                </div>
                <div class="form-group">
                    <label>Term Name</label>
                    <input type="text" name="term_name" class="form-control" placeholder="e.g. Second Semester" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Academic Year</label>
                    <input type="text" name="academic_year" class="form-control" placeholder="e.g. 2026-2027" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" class="form-control" required>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Term</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addTermModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Existing bookings (room + instructor) used to grey out taken times.
    window.SCHEDULE_BOOKINGS = <?php echo json_encode($bookings, JSON_UNESCAPED_SLASHES); ?>;
</script>

<script>
    // =====================================================
    // Multi-Session Schedule Picker
    // =====================================================
    const DAY_NAMES = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const BOOKINGS = window.SCHEDULE_BOOKINGS || [];
    const conflictTimers = {};
    var sessionCounters = { add: 1, edit: 1 };

    /** Get the sessions container element for a prefix. */
    function getSessionsContainer(prefix) {
        return document.getElementById(prefix + 'Sessions');
    }

    /** Get all session elements in a container. */
    function getSessions(prefix) {
        return getSessionsContainer(prefix).querySelectorAll('.schedule-session');
    }

    /** Toggle a day chip on/off and refresh the preview. */
    function toggleDay(chip, sessionIdx, prefix) {
        chip.classList.toggle('selected');
        var errEl = document.querySelector('[data-session-day-error="' + sessionIdx + '"]');
        if (errEl) errEl.classList.remove('visible');
        updateSchedulePreview(prefix);
    }

    /** Apply a day shortcut (MWF, TTh, etc.). */
    function setDayShortcut(btn, sessionIdx, prefix) {
        var days = btn.dataset.days.split(',').map(Number);
        var container = document.querySelector('[data-session-days="' + sessionIdx + '"]');
        if (!container) return;
        container.querySelectorAll('.day-chip').forEach(function(chip) {
            if (days.indexOf(parseInt(chip.dataset.day)) !== -1) {
                chip.classList.add('selected');
            } else {
                chip.classList.remove('selected');
            }
        });
        var errEl = document.querySelector('[data-session-day-error="' + sessionIdx + '"]');
        if (errEl) errEl.classList.remove('visible');
        updateSchedulePreview(prefix);
    }

    /** Get selected day values from a specific session's chips. */
    function getSelectedDaysForSession(sessionIdx) {
        var container = document.querySelector('[data-session-days="' + sessionIdx + '"]');
        if (!container) return [];
        var chips = container.querySelectorAll('.day-chip.selected');
        var days = [];
        chips.forEach(function(c) { days.push(parseInt(c.dataset.day)); });
        return days.sort(function(a, b) { return a - b; });
    }

    /** Convert "HH:MM" to readable "H:MM AM/PM". */
    function formatTime12(h24) {
        var parts = h24.split(':');
        var h = parseInt(parts[0]);
        var m = parts[1] || '00';
        var suffix = h >= 12 ? 'PM' : 'AM';
        var h12 = h % 12;
        if (h12 === 0) h12 = 12;
        return h12 + ':' + m + ' ' + suffix;
    }

    /** "HH:MM" -> minutes from midnight. */
    function timeToMinutes(value) {
        var parts = (value || '').split(':');
        return (parseInt(parts[0], 10) || 0) * 60 + (parseInt(parts[1], 10) || 0);
    }

    /** Set a select's value, adding a temporary option when it is not listed. */
    function setSelectValue(select, value) {
        if (!select) return;
        value = (value === null || value === undefined) ? '' : String(value);
        var exists = Array.prototype.some.call(select.options, function(option) {
            return option.value === value;
        });
        if (!exists && value !== '') {
            var option = document.createElement('option');
            option.value = value;
            option.textContent = value + ' (not in list)';
            select.appendChild(option);
        }
        select.value = value;
    }

    /** Disable/grey the options a callback reports as unavailable. */
    function applyTimeAvailability(select, isUnavailable) {
        Array.prototype.forEach.call(select.options, function(option) {
            if (option.dataset.baseLabel === undefined) {
                option.dataset.baseLabel = option.textContent;
            }
            var busy = isUnavailable(timeToMinutes(option.value));
            var keepSelectable = option.value === select.value;
            option.disabled = busy && !keepSelectable;
            option.textContent = busy ? option.dataset.baseLabel + ' — booked' : option.dataset.baseLabel;
        });
    }

    /**
     * Grey out times already booked for the selected room or instructor on the
     * days chosen for each session. Mirrors the server-side conflict check.
     */
    function refreshTimeAvailability(prefix) {
        var roomEl = document.getElementById(prefix === 'add' ? 'addRoom' : 'editSecRoom');
        var instructorEl = document.getElementById(prefix === 'add' ? 'addInstructorId' : 'editSecInstructor');
        var termEl = document.getElementById(prefix === 'add' ? 'addTermId' : 'editSecTerm');
        var excludeEl = document.getElementById('editSecId');

        var room = roomEl ? roomEl.value : '';
        var instructorId = instructorEl ? (parseInt(instructorEl.value, 10) || 0) : 0;
        var termId = termEl ? (parseInt(termEl.value, 10) || 0) : 0;
        var excludeId = (prefix === 'edit' && excludeEl) ? (parseInt(excludeEl.value, 10) || 0) : 0;

        getSessions(prefix).forEach(function(session) {
            var idx = parseInt(session.dataset.session, 10);
            var days = getSelectedDaysForSession(idx);
            var startEl = session.querySelector('[data-session-start="' + idx + '"]');
            var endEl = session.querySelector('[data-session-end="' + idx + '"]');
            if (!startEl || !endEl) return;

            var busy = BOOKINGS.filter(function(booking) {
                if (termId && booking.term_id !== termId) return false;
                if (excludeId && booking.id === excludeId) return false;
                var sameRoom = room !== '' && booking.room !== '' && booking.room === room;
                var sameInstructor = instructorId > 0 && booking.instructor_id === instructorId;
                if (!sameRoom && !sameInstructor) return false;
                return booking.days.some(function(day) { return days.indexOf(day) !== -1; });
            });

            if (days.length === 0 || busy.length === 0) {
                applyTimeAvailability(startEl, function() { return false; });
                applyTimeAvailability(endEl, function() { return false; });
                return;
            }

            var startMin = timeToMinutes(startEl.value);
            var endMin = timeToMinutes(endEl.value);

            applyTimeAvailability(startEl, function(minute) {
                return busy.some(function(booking) {
                    // Starts inside a booking, or the chosen range spans one.
                    return (minute >= booking.start && minute < booking.end) ||
                           (minute < booking.end && booking.start < endMin);
                });
            });

            applyTimeAvailability(endEl, function(minute) {
                return busy.some(function(booking) {
                    // Ends inside a booking, or the chosen range spans one.
                    return (minute > booking.start && minute <= booking.end) ||
                           (startMin < booking.end && booking.start < minute);
                });
            });
        });
    }

    /** Build the schedule string from all sessions and update the preview. */
    function updateSchedulePreview(prefix) {
        var sessions = getSessions(prefix);
        var previewEl = document.getElementById(prefix + 'SchedulePreview');
        var hiddenEl = document.getElementById(prefix + 'ScheduleHidden');
        var scheduleParts = [];

        sessions.forEach(function(session, idx) {
            var days = getSelectedDaysForSession(idx);
            var startEl = session.querySelector('[data-session-start="' + idx + '"]');
            var endEl = session.querySelector('[data-session-end="' + idx + '"]');
            var timeValEl = session.querySelector('[data-session-time-error="' + idx + '"]');
            var startTime = startEl ? startEl.value : '08:00';
            var endTime = endEl ? endEl.value : '09:30';

            // Validate time for this session
            if (startTime >= endTime) {
                if (timeValEl) timeValEl.classList.add('visible');
            } else {
                if (timeValEl) timeValEl.classList.remove('visible');
            }

            if (days.length > 0) {
                var dayStr = formatDayString(days);
                var timeStr = formatTime12(startTime) + ' - ' + formatTime12(endTime);
                scheduleParts.push(dayStr + ', ' + timeStr);
            }
        });

        var schedule = scheduleParts.join(' ; ');

        if (schedule) {
            previewEl.textContent = schedule;
            previewEl.classList.remove('empty');
        } else {
            previewEl.textContent = 'Select days and time above';
            previewEl.classList.add('empty');
        }
        hiddenEl.value = schedule;

        refreshTimeAvailability(prefix);
        debounceConflictCheck(prefix);
    }

    /** Format day numbers into a readable string with ranges. */
    function formatDayString(days) {
        if (days.length === 0) return '';
        if (days.length === 1) return DAY_NAMES[days[0]];
        var ranges = [];
        var start = days[0];
        var end = days[0];
        for (var i = 1; i < days.length; i++) {
            if (days[i] === end + 1) {
                end = days[i];
            } else {
                ranges.push(start === end ? DAY_NAMES[start] : DAY_NAMES[start] + '-' + DAY_NAMES[end]);
                start = days[i];
                end = days[i];
            }
        }
        ranges.push(start === end ? DAY_NAMES[start] : DAY_NAMES[start] + '-' + DAY_NAMES[end]);
        return ranges.join(', ');
    }

    /** Add a new session row to the picker. */
    function addSession(prefix) {
        var idx = sessionCounters[prefix]++;
        var container = getSessionsContainer(prefix);
        var html = '<div class="schedule-session" data-session="' + idx + '">' +
            '<div class="schedule-session-header">' +
                '<span class="schedule-session-number">Session ' + (idx + 1) + '</span>' +
                '<button type="button" class="btn btn-danger btn-sm session-remove-btn" onclick="removeSession(this, ' + idx + ', \'' + prefix + '\')">Remove</button>' +
            '</div>' +

            '<div class="day-shortcuts">' +
                '<button type="button" class="day-shortcut" data-days="0,2,4" onclick="setDayShortcut(this, ' + idx + ', \'' + prefix + '\')">MWF</button>' +
                '<button type="button" class="day-shortcut" data-days="1,3" onclick="setDayShortcut(this, ' + idx + ', \'' + prefix + '\')">TTh</button>' +
                '<button type="button" class="day-shortcut" data-days="0,1,2,3,4" onclick="setDayShortcut(this, ' + idx + ', \'' + prefix + '\')">Mon-Fri</button>' +
                '<button type="button" class="day-shortcut" data-days="5" onclick="setDayShortcut(this, ' + idx + ', \'' + prefix + '\')">Saturday</button>' +
            '</div>' +

            '<div class="day-chips" data-session-days="' + idx + '">' +
                '<button type="button" class="day-chip" data-day="0" onclick="toggleDay(this, ' + idx + ', \'' + prefix + '\')">Mon</button>' +
                '<button type="button" class="day-chip" data-day="1" onclick="toggleDay(this, ' + idx + ', \'' + prefix + '\')">Tue</button>' +
                '<button type="button" class="day-chip" data-day="2" onclick="toggleDay(this, ' + idx + ', \'' + prefix + '\')">Wed</button>' +
                '<button type="button" class="day-chip" data-day="3" onclick="toggleDay(this, ' + idx + ', \'' + prefix + '\')">Thu</button>' +
                '<button type="button" class="day-chip" data-day="4" onclick="toggleDay(this, ' + idx + ', \'' + prefix + '\')">Fri</button>' +
                '<button type="button" class="day-chip" data-day="5" onclick="toggleDay(this, ' + idx + ', \'' + prefix + '\')">Sat</button>' +
            '</div>' +
            '<div class="day-chips-error" data-session-day-error="' + idx + '">Please select at least one day.</div>' +

            '<div class="schedule-time-row">' +
                '<div class="time-select">' +
                    '<label style="font-size:12px;font-weight:600;color:var(--ink-muted);margin-bottom:4px;display:block;">Start Time</label>' +
                    '<select class="form-control" data-session-start="' + idx + '" onchange="updateSchedulePreview(\'' + prefix + '\')">' +
                        getTimeOptions('08:00') +
                    '</select>' +
                '</div>' +
                '<span class="time-separator">to</span>' +
                '<div class="time-select">' +
                    '<label style="font-size:12px;font-weight:600;color:var(--ink-muted);margin-bottom:4px;display:block;">End Time</label>' +
                    '<select class="form-control" data-session-end="' + idx + '" onchange="updateSchedulePreview(\'' + prefix + '\')">' +
                        getTimeOptions('09:30') +
                    '</select>' +
                '</div>' +
            '</div>' +
            '<div class="time-validation" data-session-time-error="' + idx + '">End time must be after start time.</div>' +
        '</div>';

        container.insertAdjacentHTML('beforeend', html);
        updateSchedulePreview(prefix);
    }

    /** Remove a session row. */
    function removeSession(btn, sessionIdx, prefix) {
        var session = btn.closest('.schedule-session');
        if (session) {
            session.remove();
            renumberSessions(prefix);
            updateSchedulePreview(prefix);
        }
    }

    /** Renumber session labels after removal. */
    function renumberSessions(prefix) {
        var sessions = getSessions(prefix);
        sessions.forEach(function(s, i) {
            var numEl = s.querySelector('.schedule-session-number');
            if (numEl) numEl.textContent = 'Session ' + (i + 1);
        });
    }

    /** Generate HTML options for time selects. */
    function getTimeOptions(selected) {
        var html = '';
        for (var h = 6; h <= 21; h++) {
            for (var m = 0; m < 60; m += 30) {
                if (h === 21 && m > 0) break;
                var val = (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
                var h12 = h % 12 || 12;
                var ampm = h < 12 ? 'AM' : 'PM';
                var label = h12 + ':' + (m < 10 ? '0' : '') + m + ' ' + ampm;
                html += '<option value="' + val + '"' + (val === selected ? ' selected' : '') + '>' + label + '</option>';
            }
        }
        return html;
    }

    /** Debounced AJAX conflict check. */
    function debounceConflictCheck(prefix) {
        if (conflictTimers[prefix]) clearTimeout(conflictTimers[prefix]);
        conflictTimers[prefix] = setTimeout(function() {
            checkConflicts(prefix);
        }, 400);
    }

    /** AJAX: check for schedule conflicts against existing sections. */
    function checkConflicts(prefix) {
        var schedule = document.getElementById(prefix + 'ScheduleHidden').value;
        var termEl = document.getElementById(prefix === 'add' ? 'addTermId' : 'editSecTerm');
        var instructorEl = document.getElementById(prefix === 'add' ? 'addInstructorId' : 'editSecInstructor');
        var blockEl = document.getElementById(prefix === 'add' ? 'addBlockId' : 'editSecBlock');
        var roomEl = document.getElementById(prefix === 'add' ? 'addRoom' : 'editSecRoom');
        var alertEl = document.getElementById(prefix + 'ConflictAlert');
        var listEl = document.getElementById(prefix + 'ConflictList');

        if (!schedule) {
            alertEl.classList.remove('visible');
            return;
        }

        var formData = new FormData();
        formData.append('term_id', termEl.value);
        formData.append('schedule', schedule);
        formData.append('instructor_id', instructorEl.value);
        formData.append('block_id', blockEl.value);
        formData.append('room', roomEl.value);
        if (prefix === 'edit') {
            formData.append('exclude_id', document.getElementById('editSecId').value);
        }

        fetch('../api/check-conflicts.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok && data.conflicts.length > 0) {
                    listEl.innerHTML = '';
                    data.conflicts.forEach(function(c) {
                        var li = document.createElement('li');
                        li.textContent = c.message;
                        listEl.appendChild(li);
                    });
                    alertEl.classList.add('visible');
                } else {
                    alertEl.classList.remove('visible');
                }
            })
            .catch(function() {
                alertEl.classList.remove('visible');
            });
    }

    /** Validate room field on input. */
    function checkRoomConflict(prefix) {
        debounceConflictCheck(prefix);
    }

    // =====================================================
    // Form submission: validate all sessions
    // =====================================================
    function validateScheduleForm(prefix) {
        var sessions = getSessions(prefix);
        var valid = true;
        var hiddenEl = document.getElementById(prefix + 'ScheduleHidden');
        var hasAtLeastOneSession = false;

        sessions.forEach(function(session, idx) {
            var days = getSelectedDaysForSession(idx);
            var startEl = session.querySelector('[data-session-start="' + idx + '"]');
            var endEl = session.querySelector('[data-session-end="' + idx + '"]');
            var dayErrorEl = session.querySelector('[data-session-day-error="' + idx + '"]');
            var timeValEl = session.querySelector('[data-session-time-error="' + idx + '"]');

            if (days.length === 0) {
                dayErrorEl.classList.add('visible');
                valid = false;
            } else {
                dayErrorEl.classList.remove('visible');
                hasAtLeastOneSession = true;
            }

            if (startEl.value >= endEl.value) {
                timeValEl.classList.add('visible');
                valid = false;
            } else {
                timeValEl.classList.remove('visible');
            }
        });

        if (!hasAtLeastOneSession) {
            valid = false;
        }

        if (valid) {
            updateSchedulePreview(prefix);
        }

        return valid;
    }

    document.getElementById('addBlockForm').addEventListener('submit', function(e) {
        if (!validateScheduleForm('add')) {
            e.preventDefault();
        }
    });
    document.getElementById('editBlockForm').addEventListener('submit', function(e) {
        if (!validateScheduleForm('edit')) {
            e.preventDefault();
        }
    });

    // =====================================================
    // View Block modal
    // =====================================================
    function viewBlock(btn) {
        document.getElementById('viewBlockCode').textContent = btn.dataset.blockCode;
        document.getElementById('viewSecClassBlock').textContent = btn.dataset.classBlock || '—';
        document.getElementById('viewSecSubject').textContent = btn.dataset.subject;
        document.getElementById('viewSecInstructor').textContent = btn.dataset.instructor;
        document.getElementById('viewSecTerm').textContent = btn.dataset.term;
        document.getElementById('viewSecSchedule').textContent = btn.dataset.schedule || '—';
        document.getElementById('viewSecRoom').textContent = btn.dataset.room || '—';
        var cap = parseInt(btn.dataset.capacity) || 0;
        var enrolled = parseInt(btn.dataset.enrolled) || 0;
        document.getElementById('viewSecCapacity').textContent = cap ? cap : '—';
        document.getElementById('viewSecEnrolled').textContent = enrolled;

        var availEl = document.getElementById('viewSecAvail');
        if (cap > 0) {
            var pct = Math.min((enrolled / cap) * 100, 100);
            var remaining = Math.max(cap - enrolled, 0);
            var cls = pct >= 100 ? 'cap-full' : (pct >= 80 ? 'cap-warning' : (pct >= 50 ? 'cap-moderate' : 'cap-ok'));
            var label = pct >= 100 ? 'FULL — No more slots' : (pct >= 80 ? 'Almost full — ' + remaining + ' slot' + (remaining !== 1 ? 's' : '') + ' left' : remaining + ' of ' + cap + ' slots available');
            availEl.innerHTML = '<div class="capacity-view">' +
                '<span class="capacity-badge ' + cls + '" style="margin-bottom:4px">' + label + '</span>' +
                '<div class="capacity-progress">' +
                    '<div class="capacity-progress-fill ' + cls + '" style="width:' + pct + '%"></div>' +
                '</div>' +
            '</div>';
        } else {
            availEl.textContent = 'No capacity limit set';
        }

        document.getElementById('viewBlockModal').style.display = 'block';
    }

    // =====================================================
    // Edit Block: parse existing multi-session schedule into picker
    // =====================================================
    function parseSingleSession(str) {
        if (!str) return null;
        var result = { days: [], startTime: '08:00', endTime: '09:30' };

        var timeMatch = str.match(/(\d{1,2}:\d{2}\s*(?:AM|PM)?)\s*-\s*(\d{1,2}:\d{2}\s*(?:AM|PM)?)/i);
        if (timeMatch) {
            result.startTime = convertTo24h(timeMatch[1].trim());
            result.endTime = convertTo24h(timeMatch[2].trim());
        }

        var dayPart = str.split(/,\s*/)[0].trim();
        var segments = dayPart.split(/[\s,]+/);
        segments.forEach(function(seg) {
            seg = seg.trim();
            if (seg.indexOf('-') !== -1) {
                var rangeParts = seg.split('-');
                var startIdx = DAY_NAMES.indexOf(rangeParts[0]);
                var endIdx = DAY_NAMES.indexOf(rangeParts[1]);
                if (startIdx !== -1 && endIdx !== -1) {
                    for (var i = Math.min(startIdx, endIdx); i <= Math.max(startIdx, endIdx); i++) {
                        if (result.days.indexOf(i) === -1) result.days.push(i);
                    }
                }
            } else {
                var idx = DAY_NAMES.indexOf(seg);
                if (idx !== -1 && result.days.indexOf(idx) === -1) {
                    result.days.push(idx);
                }
            }
        });

        return result;
    }

    function convertTo24h(timeStr) {
        var match = timeStr.match(/(\d{1,2}):(\d{2})\s*(AM|PM)?/i);
        if (!match) return '08:00';
        var h = parseInt(match[1]);
        var m = match[2];
        var suffix = match[3] ? match[3].toUpperCase() : '';
        if (suffix === 'PM' && h < 12) h += 12;
        if (suffix === 'AM' && h === 12) h = 0;
        return (h < 10 ? '0' : '') + h + ':' + m;
    }

    function editBlock(btn) {
        document.getElementById('editSecId').value = btn.dataset.id;
        document.getElementById('editSecSubject').value = btn.dataset.subjectId;
        document.getElementById('editSecInstructor').value = btn.dataset.instructorId;
        document.getElementById('editSecTerm').value = btn.dataset.termId;
        setSelectValue(document.getElementById('editSecBlock'), btn.dataset.blockId || '');
        setSelectValue(document.getElementById('editSecRoom'), btn.dataset.room || '');
        document.getElementById('editSecCapacity').value = btn.dataset.capacity || '';
        document.getElementById('editSecStatus').value = btn.dataset.status;

        // Parse existing schedule into sessions
        var scheduleStr = btn.dataset.schedule || '';
        var sessionStrings = scheduleStr.split(';').map(function(s) { return s.trim(); }).filter(function(s) { return s; });
        var container = getSessionsContainer('edit');

        // Clear existing sessions
        container.innerHTML = '';
        sessionCounters.edit = 0;

        if (sessionStrings.length === 0) {
            sessionStrings = [''];
        }

        sessionStrings.forEach(function(sessionStr, i) {
            var idx = sessionCounters.edit++;
            var parsed = sessionStr ? parseSingleSession(sessionStr) : { days: [], startTime: '08:00', endTime: '09:30' };

            var dayBtns = '';
            for (var d = 0; d < 6; d++) {
                var selected = parsed.days.indexOf(d) !== -1 ? ' selected' : '';
                dayBtns += '<button type="button" class="day-chip' + selected + '" data-day="' + d + '" onclick="toggleDay(this, ' + idx + ', \'' + 'edit' + '\')">' + DAY_NAMES[d] + '</button>';
            }

            var html = '<div class="schedule-session" data-session="' + idx + '">' +
                '<div class="schedule-session-header">' +
                    '<span class="schedule-session-number">Session ' + (i + 1) + '</span>' +
                    (i > 0 ? '<button type="button" class="btn btn-danger btn-sm session-remove-btn" onclick="removeSession(this, ' + idx + ', \'' + 'edit' + '\')">Remove</button>' : '') +
                '</div>' +
                '<div class="day-shortcuts">' +
                    '<button type="button" class="day-shortcut" data-days="0,2,4" onclick="setDayShortcut(this, ' + idx + ', \'' + 'edit' + '\')">MWF</button>' +
                    '<button type="button" class="day-shortcut" data-days="1,3" onclick="setDayShortcut(this, ' + idx + ', \'' + 'edit' + '\')">TTh</button>' +
                    '<button type="button" class="day-shortcut" data-days="0,1,2,3,4" onclick="setDayShortcut(this, ' + idx + ', \'' + 'edit' + '\')">Mon-Fri</button>' +
                    '<button type="button" class="day-shortcut" data-days="5" onclick="setDayShortcut(this, ' + idx + ', \'' + 'edit' + '\')">Saturday</button>' +
                '</div>' +
                '<div class="day-chips" data-session-days="' + idx + '">' + dayBtns + '</div>' +
                '<div class="day-chips-error" data-session-day-error="' + idx + '">Please select at least one day.</div>' +
                '<div class="schedule-time-row">' +
                    '<div class="time-select">' +
                        '<label style="font-size:12px;font-weight:600;color:var(--ink-muted);margin-bottom:4px;display:block;">Start Time</label>' +
                        '<select class="form-control" data-session-start="' + idx + '" onchange="updateSchedulePreview(\'' + 'edit' + '\')">' +
                            getTimeOptions(parsed.startTime) +
                        '</select>' +
                    '</div>' +
                    '<span class="time-separator">to</span>' +
                    '<div class="time-select">' +
                        '<label style="font-size:12px;font-weight:600;color:var(--ink-muted);margin-bottom:4px;display:block;">End Time</label>' +
                        '<select class="form-control" data-session-end="' + idx + '" onchange="updateSchedulePreview(\'' + 'edit' + '\')">' +
                            getTimeOptions(parsed.endTime) +
                        '</select>' +
                    '</div>' +
                '</div>' +
                '<div class="time-validation" data-session-time-error="' + idx + '">End time must be after start time.</div>' +
            '</div>';

            container.insertAdjacentHTML('beforeend', html);
        });

        document.getElementById('editScheduleHidden').value = scheduleStr;
        updateSchedulePreview('edit');

        document.getElementById('editConflictAlert').classList.remove('visible');

        document.getElementById('editBlockModal').style.display = 'block';
    }

    // =====================================================
    // Modal overlay close
    // =====================================================
    ['addBlockModal', 'addTermModal', 'viewBlockModal', 'editBlockModal'].forEach(function(id) {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });

    // Reset add modal state when opened
    document.querySelector('[onclick="document.getElementById(\'addBlockModal\').style.display=\'block\'"]')
        .addEventListener('click', function() {
            // Reset to single session
            var container = getSessionsContainer('add');
            container.innerHTML = '';
            sessionCounters.add = 0;
            addSession('add');
            document.getElementById('addScheduleHidden').value = '';
            document.getElementById('addSchedulePreview').textContent = 'Select days and time above';
            document.getElementById('addSchedulePreview').classList.add('empty');
            document.getElementById('addConflictAlert').classList.remove('visible');
            document.getElementById('addRoomConflict').classList.remove('visible');
        });

    // Normalise the time labels on first paint.
    updateSchedulePreview('add');

</script>

<?php include '../includes/footer.php'; ?>
