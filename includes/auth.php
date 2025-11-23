<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function redirectToLogin(): void {
    header("Location: /login.php"); // pastikan login.php ada di root
    exit();
}

function requireLogin(): void {
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        redirectToLogin();
    }
}

function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        redirectToLogin();
    }
}

function requireAnyRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles, true)) {
        redirectToLogin();
    }
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

function currentUserId(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function currentUserRole(): ?string {
    return isset($_SESSION['role']) ? (string)$_SESSION['role'] : null;
}

/* Kompatibilitas mundur untuk file lama */
if (!function_exists('checkRole')) {
    function checkRole($role) {
        requireRole((string)$role);
    }
}