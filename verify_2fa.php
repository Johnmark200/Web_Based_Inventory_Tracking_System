<?php
include 'db.php';

if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
}

$errors = [];
$messages = [];
$pendingUserId = (int) ($_SESSION['pending_2fa_user_id'] ?? 0);
$code = '';

if (!$pendingUserId) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'verify';
    $code = preg_replace('/\D+/', '', trim($_POST['code'] ?? ''));

    if ($action === 'resend') {
        $stmt = $conn->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $pendingUserId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && sendTwoFactorCode($conn, $pendingUserId, $user['email'])) {
            $messages[] = 'A new verification code has been sent to your email.';
        } else {
            $errors[] = 'Unable to resend the code right now. Please try again.';
        }
    } else {
        $stmt = $conn->prepare(
            'SELECT id
             FROM users
             WHERE id = ?
               AND two_factor_code = ?
               AND two_factor_expires >= UTC_TIMESTAMP()
             LIMIT 1'
        );
        $stmt->bind_param('is', $pendingUserId, $code);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $clear = $conn->prepare(
                'UPDATE users
                 SET two_factor_code = NULL, two_factor_expires = NULL
                 WHERE id = ?'
            );
            $clear->bind_param('i', $pendingUserId);
            $clear->execute();
            $clear->close();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $pendingUserId;
            unset($_SESSION['pending_2fa_user_id']);
            redirect('index.php');
        }

        $errors[] = 'Invalid or expired code.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify 2FA</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <div class="page-shell auth-shell">
        <section class="form-panel auth-card">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Two-Factor</p>
                    <h1>Verify Code</h1>
                    <p>Enter the 6-digit code sent to your email.</p>
                </div>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($messages): ?>
                <div class="alert alert-success">
                    <?php foreach ($messages as $message): ?>
                        <p><?php echo h($message); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="product-form">
                <label>
                    2FA Code
                    <input type="text" name="code" value="<?php echo h($code); ?>" inputmode="numeric" maxlength="6" required>
                </label>
                <div class="form-actions">
                    <button type="submit" name="action" value="verify" class="btn btn-primary">Verify</button>
                    <button type="submit" name="action" value="resend" class="btn btn-secondary" formnovalidate>Resend OTP</button>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
