<?php
include 'db.php';

if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare('SELECT id, email, password FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        if (sendTwoFactorCode($conn, (int) $user['id'], $user['email'])) {
            session_regenerate_id(true);
            $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
            redirect('verify_2fa.php');
        }

        $errors[] = 'Unable to send the 2FA code. Check SMTP settings.';
    } else {
        $errors[] = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <div class="page-shell auth-shell">
        <section class="form-panel auth-card">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Welcome Back</p>
                    <h1>Login</h1>
                    <p>Sign in and receive your 2FA code by email.</p>
                </div>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="product-form">
                <label>
                    Email
                    <input type="email" name="email" value="<?php echo h($email); ?>" required>
                </label>
                <label>
                    Password
                    <input type="password" name="password" required>
                </label>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Login</button>
                </div>
            </form>

            <div class="auth-links">
                <a href="register.php">Create account</a>
                <a href="forgot_password.php">Forgot password?</a>
            </div>
        </section>
    </div>
</body>
</html>
