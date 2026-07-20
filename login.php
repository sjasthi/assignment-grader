<?php
declare(strict_types=1);

require_once 'db.php';
require_once 'auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $message = 'Please enter your email and password.';
    } else {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, full_name, email, password_hash, role
             FROM users
             WHERE email = ?
             LIMIT 1'
        );

        if (!$stmt) {
            $message = 'Unable to prepare the login request.';
        } else {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (
                $user
                && password_verify($password, (string) $user['password_hash'])
            ) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_name'] = (string) $user['full_name'];
                $_SESSION['user_email'] = (string) $user['email'];
                $_SESSION['user_role'] = (string) $user['role'];

                $redirect = (string) ($_SESSION['login_redirect'] ?? 'index.php');
                unset($_SESSION['login_redirect']);

                if (
                    $redirect === ''
                    || str_contains($redirect, '://')
                    || str_starts_with($redirect, '//')
                ) {
                    $redirect = 'index.php';
                }

                header('Location: ' . $redirect);
                exit;
            }

            $message = 'Incorrect email or password.';
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
    <title>Login | Assignment Grader</title>
    <link rel="stylesheet" href="style.css?v=999">
</head>
<body>
<main>
    <section class="page-section">
        <div class="form-box">
            <h1>Login</h1>

            <?php if ($message !== ''): ?>
                <div class="message error" role="alert">
                    <?php echo escape($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <div class="form-actions">
                    <button type="submit">Login</button>
                    <a class="cancel-button" href="index.php">Back Home</a>
                </div>
            </form>
        </div>
    </section>
</main>
</body>
</html>
