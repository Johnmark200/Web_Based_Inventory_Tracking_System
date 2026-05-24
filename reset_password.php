<?php
include 'db.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$errors = [];
$success = false;

if ($token === '') {
    redirect('forgot_password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $stmt = $conn->prepare(
            'SELECT id
             FROM users
             WHERE reset_token = ?
               AND reset_expires >= UTC_TIMESTAMP()
             LIMIT 1'
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare(
                'UPDATE users
                 SET password = ?, reset_token = NULL, reset_expires = NULL, two_factor_code = NULL, two_factor_expires = NULL
                 WHERE id = ?'
            );
            $update->bind_param('si', $hashedPassword, $user['id']);
            $update->execute();
            $update->close();
            $success = true;
        } else {
            $errors[] = 'Invalid or expired reset token.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <div class="page-shell auth-shell">
        <section class="form-panel auth-card">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Recovery</p>
                    <h1>Reset Password</h1>
                    <p>Create a new password for your account.</p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p>Password updated. You can now log in.</p>
                </div>
                <div class="auth-links">
                    <a href="login.php">Back to Login</a>
                </div>
            <?php else: ?>
                <?php if ($errors): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo h($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="product-form">
                    <input type="hidden" name="token" value="<?php echo h($token); ?>">
                    <label>
                        New Password
                        <input type="password" name="password" required>
                    </label>
                    <label>
                        Confirm Password
                        <input type="password" name="confirm_password" required>
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
