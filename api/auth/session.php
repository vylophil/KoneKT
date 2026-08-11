<?php
// ============================================================
// KONEKT — Session Guard
// ============================================================
// Session management and authentication guard.
// Include this file at the top of any protected endpoint.
// ============================================================

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validation.php';

/**
 * Require an authenticated session.
 * Sends a 401 response if the user is not logged in.
 *
 * @return array The authenticated user data from the session
 */
function requireAuth(): array
{
    if (!isset($_SESSION['user_id'])) {
        jsonError('Authentication required. Please log in.', 401);
    }

    return [
        'id'         => $_SESSION['user_id'],
        'email'      => $_SESSION['email'],
        'role'       => $_SESSION['role'],
        'first_name' => $_SESSION['first_name'],
        'last_name'  => $_SESSION['last_name'],
    ];
}

/**
 * Require a specific user role.
 * Sends a 403 response if the user's role doesn't match.
 *
 * @param string $role Required role ('job_seeker' or 'employer')
 * @return array The authenticated user data
 */
function requireRole(string $role): array
{
    $user = requireAuth();

    if ($user['role'] !== $role) {
        jsonError("Access denied. This action requires the '{$role}' role.", 403);
    }

    return $user;
}

/**
 * Get the current user's ID if logged in, or null.
 *
 * @return int|null
 */
function getCurrentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Check if the current session user is logged in.
 *
 * @return bool
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}
