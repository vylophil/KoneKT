<?php
// ============================================================
// KONEKT — User Logout
// ============================================================
// POST /api/auth/logout.php
//
// Destroys the current session and logs the user out.
// ============================================================

session_start();

require_once __DIR__ . '/../helpers/response.php';

requireMethod('POST');

// --- Destroy session ---
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
    jsonSuccess(null, 'Logged out successfully.');
}

header('Location: ../../login.php?logged_out=1');
exit;
