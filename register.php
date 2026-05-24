<?php
include 'db.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$signupEnabled = false;
$errors = [];
$success = false;
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$signupEnabled) {
        $errors[] = 'Sign up is currently disabled.';
    } else {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->bind_param('s', $email);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            $errors[] = 'Email is already registered.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $name, $email, $hashedPassword);
            $stmt->execute();
            $stmt->close();
            $success = true;
            $name = '';
            $email = '';
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <div class="page-shell auth-shell">
        <section class="form-panel auth-card">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Create Account</p>
                    <h1>Sign Up</h1>
                    <p>Register a new inventory user account.</p>
                </div>
            </div>

            <?php if (!$signupEnabled): ?>
                <div class="alert alert-error">
                    <p>Sign up is currently disabled.</p>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p>Account created. You can now log in.</p>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="product-form">
                <label>
                    Name
                    <input type="text" name="name" value="<?php echo h($name); ?>" required>
                </label>
                <label>
                    Email
                    <input type="email" name="email" value="<?php echo h($email); ?>" required>
                </label>
                <label>
                    Password
                    <input type="password" name="password" required>
                </label>
                <label>
                    Confirm Password
                    <input type="password" name="confirm_password" required>
                </label>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" <?php echo !$signupEnabled ? 'disabled aria-disabled="true"' : ''; ?>>Sign Up</button>
                </div>
            </form>

            <div class="auth-links">
                <a href="login.php">Back to Login</a>
            </div>
        </section>
    </div>
</body>
</html>
