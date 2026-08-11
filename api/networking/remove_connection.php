<?php
// ============================================================
// KONEKT — Remove Connection
// ============================================================
// DELETE /api/networking/remove_connection.php?connection_id=<id>
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('DELETE');
$user = requireAuth();

$connectionId = isset($_GET['connection_id']) ? (int) $_GET['connection_id'] : 0;

if (!$connectionId) {
    jsonError('connection_id is required.', 400);
}

$db = getDB();

// --- Verify user is part of this connection ---
$stmt = $db->prepare('
    DELETE FROM connections
    WHERE id = :id AND (requester_id = :user_id1 OR receiver_id = :user_id2)
');
$stmt->execute([
    ':id'        => $connectionId,
    ':user_id1'  => $user['id'],
    ':user_id2'  => $user['id'],
]);

if ($stmt->rowCount() === 0) {
    jsonError('Connection not found or access denied.', 404);
}

jsonSuccess(null, 'Connection removed.');
