<?php
include 'db.php';

$message = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $message = 'If the email exists, a reset link has been sent.';

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $token = tokenValue(16);
            $update = $conn->prepare(
                'UPDATE users
                 SET reset_token = ?,
                     reset_expires = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)
                 WHERE id = ?'
            );
            $update->bind_param('si', $token, $user['id']);
            $update->execute();
            $update->close();

            $link = sprintf(
                '%s://%s%s/reset_password.php?token=%s',
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
                $_SERVER['HTTP_HOST'] ?? 'localhost',
                rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\'),
                $token
            );

            sendSmtpMail($email, 'Password Reset', "Reset your password using this link:\n{$link}\nThis link expires in 30 minutes.");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <div class="page-shell auth-shell">
        <section class="form-panel auth-card">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Recovery</p>
                    <h1>Forgot Password</h1>
                    <p>We’ll email a reset link if the account exists.</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <p><?php echo h($message); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" class="product-form">
                <label>
                    Email
                    <input type="email" name="email" value="<?php echo h($email); ?>" required>
                </label>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Send Reset Link</button>
                </div>
            </form>

            <div class="auth-links">
                <a href="login.php">Back to Login</a>
            </div>
        </section>
    </div>
</body>
</html>
