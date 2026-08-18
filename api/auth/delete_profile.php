<?php
// ============================================================
// KONEKT — Delete User Profile
// ============================================================
// POST /api/auth/delete_profile.php
//
// Deletes the currently signed-in user's account and related
// data from the database when available, then clears the session.
// ============================================================

session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../dashboard.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

try {
    $db = getDB();

    $db->beginTransaction();

    $db->prepare('DELETE FROM messages WHERE sender_id = :user_id1 OR receiver_id = :user_id2')->execute([':user_id1' => $userId, ':user_id2' => $userId]);
    $db->prepare('DELETE FROM connections WHERE requester_id = :user_id1 OR receiver_id = :user_id2')->execute([':user_id1' => $userId, ':user_id2' => $userId]);
    $db->prepare('DELETE FROM job_applications WHERE user_id = :user_id')->execute([':user_id' => $userId]);
    $db->prepare('DELETE FROM saved_jobs WHERE user_id = :user_id')->execute([':user_id' => $userId]);
    $db->prepare('DELETE FROM job_matches WHERE user_id = :user_id')->execute([':user_id' => $userId]);
    $db->prepare('DELETE FROM profiles WHERE user_id = :user_id')->execute([':user_id' => $userId]);
    $db->prepare('DELETE FROM users WHERE id = :user_id')->execute([':user_id' => $userId]);

    $db->commit();
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();

    header('Location: ../../login.php?deleted=1');
    exit;
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: ../../login.php?deleted=1');
exit;
