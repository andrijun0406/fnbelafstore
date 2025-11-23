<?php
// Mulai sesi hanya jika belum aktif
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Redirect ke halaman login (path absolut dari root)
 */
function redirectToLogin(): void {
    header("Location: /login.php");
    exit();
}

/**
 * Wajib login
 */
function requireLogin(): void {
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        redirectToLogin();
    }
}

/**
 * Wajib role tertentu (mis. 'admin' atau 'supplier')
 */
function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        redirectToLogin();
    }
}

/**
 * Wajib salah satu dari beberapa role
 */
function requireAnyRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles, true)) {
        redirectToLogin();
    }
}

/**
 * Helpers (opsional)
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

function currentUserId(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function currentUserRole(): ?string {
    return isset($_SESSION['role']) ? (string)$_SESSION['role'] : null;
}