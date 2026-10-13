<?php
/**
 * Rooms Management Page (Admin only)
 * Rooms carry a location (floor) and optional capacity. Active rooms are
 * offered in the schedule picker's Room dropdown.
 */

require_once '../includes/functions.php';
requireRole('admin');

$db = getDB();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $room_code = sanitize($_POST['room_code'] ?? '');
            $floor = sanitize($_POST['floor'] ?? '');
            $capacity = intval($_POST['capacity'] ?? 0);

            if ($room_code === '') {
                setFlash('Room name is required.', 'error');
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO rooms (room_code, floor, capacity) VALUES (:r, :f, :c)");
                    $stmt->execute([':r' => $room_code, ':f' => $floor ?: null, ':c' => $capacity ?: null]);
                    setFlash('Room "' . $room_code . '" added.');
                } catch (Exception $e) {
                    setFlash('Error adding room: ' . $e->getMessage(), 'error');
                }
            }
            header('Location: rooms.php');
            exit();
            break;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $room_code = sanitize($_POST['room_code'] ?? '');
            $floor = sanitize($_POST['floor'] ?? '');
            $capacity = intval($_POST['capacity'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (!$id || $room_code === '') {
                setFlash('Room name is required.', 'error');
            } else {
                try {
                    $current = $db->prepare("SELECT room_code FROM rooms WHERE id = :id");
                    $current->execute([':id' => $id]);
                    $oldCode = $current->fetchColumn();

                    $db->beginTransaction();

                    $stmt = $db->prepare("UPDATE rooms SET room_code = :r, floor = :f, capacity = :c, is_active = :a, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $stmt->execute([
                        ':r' => $room_code,
                        ':f' => $floor ?: null,
                        ':c' => $capacity ?: null,
                        ':a' => $is_active,
                        ':id' => $id,
                    ]);

                    // Keep schedules pointing at the renamed room.
                    if ($oldCode !== false && $oldCode !== '' && $oldCode !== $room_code) {
                        $db->prepare("UPDATE course_sections SET room = :new WHERE room = :old")
                           ->execute([':new' => $room_code, ':old' => $oldCode]);
                    }

                    $db->commit();
                    setFlash('Room updated.');
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    setFlash('Error updating room: ' . $e->getMessage(), 'error');
                }
            }
            header('Location: rooms.php');
            exit();
            break;

        case 'toggle_active':
            $id = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("UPDATE rooms SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([':id' => $id]);
            setFlash('Room status updated.');
            header('Location: rooms.php');
            exit();
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("SELECT room_code FROM rooms WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $roomCode = $stmt->fetchColumn();

            if ($roomCode === false) {
                setFlash('Room not found.', 'error');
            } else {
                $inUse = $db->prepare("SELECT COUNT(*) FROM course_sections WHERE room = :r");
                $inUse->execute([':r' => $roomCode]);
                $used = (int) $inUse->fetchColumn();

                if ($used > 0) {
                    setFlash('Cannot delete "' . $roomCode . '": it is used by ' . $used . ' schedule' . ($used === 1 ? '' : 's') . '. Deactivate it instead.', 'error');
                } else {
                    $db->prepare("DELETE FROM rooms WHERE id = :id")->execute([':id' => $id]);
                    setFlash('Room deleted.');
                }
            }
            header('Location: rooms.php');
            exit();
            break;
    }
}

$rooms = $db->query("
    SELECT r.*,
           (SELECT COUNT(*) FROM course_sections cs WHERE cs.room = r.room_code) AS schedule_count
    FROM rooms r
    ORDER BY r.floor, r.room_code
")->fetchAll();

usort($rooms, function ($a, $b) {
    $floorDiff = floorSortIndex($a['floor'] ?? '') <=> floorSortIndex($b['floor'] ?? '');
    return $floorDiff !== 0 ? $floorDiff : strcasecmp($a['room_code'], $b['room_code']);
});

$activeCount = 0;
foreach ($rooms as $room) {
    if ((int) $room['is_active'] === 1) {
        $activeCount++;
    }
}

$pageTitle = 'Rooms';
include '../includes/header.php';
displayFlash();
?>

<main>
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2><?php echo icon('door', 24); ?> Rooms</h2>
            <button class="btn btn-primary" onclick="document.getElementById('addRoomModal').style.display='block'"><?php echo icon('plus', 14); ?> Add Room</button>
        </div>

        <div class="table-search-bar">
            <div class="table-search-wrapper">
                <span class="search-icon"><?php echo icon('search', 16); ?></span>
                <input type="text" id="roomSearch" placeholder="Search by room, floor...">
            </div>
            <span class="search-count"></span>
        </div>

        <table data-pagination data-page-size="8" id="roomTable">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Location</th>
                    <th>Capacity</th>
                    <th>Schedules</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $room): ?>
                <tr data-search="<?php echo htmlspecialchars(strtolower($room['room_code'] . ' ' . ($room['floor'] ?? '') . ' ' . ((int) $room['is_active'] === 1 ? 'active' : 'inactive'))); ?>">
                    <td data-label="Room"><code><?php echo htmlspecialchars($room['room_code']); ?></code></td>
                    <td data-label="Location"><?php echo htmlspecialchars($room['floor'] ?: '—'); ?></td>
                    <td data-label="Capacity"><?php echo $room['capacity'] ? (int) $room['capacity'] : '—'; ?></td>
                    <td data-label="Schedules"><?php echo (int) $room['schedule_count']; ?></td>
                    <td data-label="Status">
                        <span class="attendance-badge status-<?php echo (int) $room['is_active'] === 1 ? 'present' : 'absent'; ?>">
                            <?php echo (int) $room['is_active'] === 1 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td data-label="Actions">
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?php echo $room['id']; ?>"
                                data-code="<?php echo htmlspecialchars($room['room_code'], ENT_QUOTES); ?>"
                                data-floor="<?php echo htmlspecialchars($room['floor'] ?? '', ENT_QUOTES); ?>"
                                data-capacity="<?php echo $room['capacity'] ? (int) $room['capacity'] : ''; ?>"
                                data-active="<?php echo (int) $room['is_active']; ?>"
                                onclick="editRoom(this)">Edit</button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="id" value="<?php echo $room['id']; ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <?php echo (int) $room['is_active'] === 1 ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </form>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this room?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $room['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Add Room Modal -->
<div id="addRoomModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('door', 20); ?> Add New Room</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label>Room Name / Code</label>
                <input type="text" name="room_code" class="form-control" placeholder="e.g. CL 7" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Location (Floor)</label>
                    <select name="floor" class="form-control">
                        <option value="">-- Select Floor --</option>
                        <?php foreach (floorOptions() as $floor): ?>
                        <option value="<?php echo htmlspecialchars($floor); ?>"><?php echo htmlspecialchars($floor); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Capacity</label>
                    <input type="number" name="capacity" class="form-control" min="1" placeholder="e.g. 40">
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Room</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addRoomModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Room Modal -->
<div id="editRoomModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('pen-line', 20); ?> Edit Room</h2>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editRoomId">

            <div class="form-group">
                <label>Room Name / Code</label>
                <input type="text" name="room_code" id="editRoomCode" class="form-control" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Location (Floor)</label>
                    <select name="floor" id="editRoomFloor" class="form-control">
                        <option value="">-- Select Floor --</option>
                        <?php foreach (floorOptions() as $floor): ?>
                        <option value="<?php echo htmlspecialchars($floor); ?>"><?php echo htmlspecialchars($floor); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Capacity</label>
                    <input type="number" name="capacity" id="editRoomCapacity" class="form-control" min="1" placeholder="e.g. 40">
                </div>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="editRoomActive" value="1" style="width: auto;">
                    Available for scheduling
                </label>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Changes</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('editRoomModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function editRoom(btn) {
        document.getElementById('editRoomId').value = btn.dataset.id;
        document.getElementById('editRoomCode').value = btn.dataset.code;
        document.getElementById('editRoomFloor').value = btn.dataset.floor || '';
        document.getElementById('editRoomCapacity').value = btn.dataset.capacity || '';
        document.getElementById('editRoomActive').checked = btn.dataset.active === '1';
        document.getElementById('editRoomModal').style.display = 'block';
    }

    ['addRoomModal', 'editRoomModal'].forEach(function(id) {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
