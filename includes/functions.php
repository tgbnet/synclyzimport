<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function config(string $section, ?string $key = null)
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }

    if (!array_key_exists($section, $config)) {
        return null;
    }

    if ($key === null) {
        return $config[$section];
    }

    return $config[$section][$key] ?? null;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);

    return $value;
}

function currentUser(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, username, email, bio, avatar_path, is_admin, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['user_id']]);

    $user = $stmt->fetch();
    return $user ?: null;
}

function requireAuth(): array
{
    $user = currentUser();
    if (!$user) {
        flash('error', 'Please log in first.');
        redirect('login.php');
    }

    return $user;
}

function requireAdmin(): array
{
    $user = requireAuth();

    if ((int) $user['is_admin'] !== 1) {
        flash('error', 'Admin access required.');
        redirect('/index.php');
    }

    return $user;
}

function isPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function baseUrl(string $path = ''): string
{
    $base = rtrim((string) config('app', 'base_url'), '/');
    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}

function videoCategories(): array
{
    return ['Education', 'Entertainment', 'Gaming', 'Music', 'Technology', 'Travel', 'Other'];
}

function userCount(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}
