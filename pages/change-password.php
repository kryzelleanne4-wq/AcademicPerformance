<?php
/**
 * Change Password Page
 * Any logged-in user can update their own password.
 */

require_once '../includes/functions.php';
requireLogin();

$db = getDB();
$user = currentUser();
$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!password_verify($current, $user['password'])) {
        $error = 'Your current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        $stmt = $db->prepare("UPDATE users SET password = :password, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([
            ':password' => password_hash($new, PASSWORD_DEFAULT),
            ':id'       => $user['id']
        ]);
        setFlash('Password changed successfully!');
        header('Location: ../index.php');
        exit();
    }
}

$pageTitle = 'Change Password';
include '../includes/header.php';
?>

<main>
    <div class="card" style="max-width: 520px;">
        <div class="card-header">
            <h2><?php echo icon('lock', 24); ?> Change Password</h2>
        </div>
        <div style="padding: 24px;">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <p style="margin-bottom: 24px; color: var(--ink-muted);">
                You are signed in as <strong><?php echo htmlspecialchars($user['username']); ?></strong>.
                Choose a new password to replace your current one.
            </p>

            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                    <small>At least 8 characters.</small>
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                </div>

                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-success">Update Password</button>
                    <a href="../index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
