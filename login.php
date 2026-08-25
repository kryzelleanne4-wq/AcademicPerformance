<?php
/**
 * Login Page
 * Users sign in with their auto-generated ID (student/instructor) or username.
 */

require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter your ID and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username COLLATE NOCASE AND is_active = 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            startSession();
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['role'] = $user['role'];

            $update = $db->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id");
            $update->execute([':id' => $user['id']]);

            header('Location: index.php');
            exit();
        } else {
            $error = 'Invalid ID or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Academic Excellence System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
    <div class="login-card">
        <div class="login-brand">
            <h1><span class="red">ACADEMIC</span><br><span class="white">EXCELLENCE</span></h1>
            <p class="login-subtitle">Sign in to continue</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="username">ID / Username</label>
                <input type="text" id="username" name="username" class="form-control"
                       placeholder="e.g. STU-2026-0001" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Your password" required>
            </div>

            <button type="submit" class="btn btn-primary login-btn">Sign In</button>
        </form>

        <p class="login-hint">New accounts are created by the administrator.<br>Default password is <code>password123</code>.</p>
    </div>
</body>
</html>
