<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user_name(): string
{
    return (string) ($_SESSION['user_name'] ?? '');
}

function current_user_role(): string
{
    return (string) ($_SESSION['user_role'] ?? '');
}

function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: login.php');
        exit;
    }
}

function require_role(string $role): void
{
    require_login();

    if (current_user_role() !== $role) {
        http_response_code(403);
        die('You do not have permission to view this page.');
    }
}
