<?php
declare(strict_types=1);

require_once 'db.php';
require_once 'auth.php';

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($fullName === '' || $email === '' || $password === '') {
        $message = 'Please complete every field.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'The password must be at least 8 characters.';
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'The passwords do not match.';
        $messageType = 'error';
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO users (full_name, email, password_hash, role)
             VALUES (?, ?, ?, "instructor")'
        );

        if (!$stmt) {
            $message = 'Unable to prepare account creation.';
            $messageType = 'error';
        } else {
            mysqli_stmt_bind_param(
                $stmt,
                'sss',
                $fullName,
                $email,
                $passwordHash
            );

            if (mysqli_stmt_execute($stmt)) {
                $message = 'Instructor account created. You may now log in.';
                $messageType = 'success';
            } elseif (mysqli_errno($conn) === 1062) {
                $message = 'An account with that email already exists.';
                $messageType = 'error';
            } else {
                $message = 'Unable to create the account.';
                $messageType = 'error';
            }

            mysqli_stmt_close($stmt);
        }
    }
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Instructor Account</title>
    <link rel="stylesheet" href="style.css?v=999">
</head>
<body>
<main>
    <section class="page-section">
        <div class="form-box">
            <h1>Create Instructor Account</h1>

            <?php if ($message !== ''): ?>
                <div class="message <?php echo escape($messageType); ?>">
                    <?php echo escape($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="full-name">Full Name</label>
                    <input id="full-name" name="full_name" type="text" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" minlength="8" required>
                </div>

                <div class="form-group">
                    <label for="confirm-password">Confirm Password</label>
                    <input id="confirm-password" name="confirm_password" type="password" minlength="8" required>
                </div>

                <button type="submit">Create Instructor Account</button>
                <a class="cancel-button" href="login.php">Go to Login</a>
            </form>
        </div>
    </section>
</main>
</body>
</html>
