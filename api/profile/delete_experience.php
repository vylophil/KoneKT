<?php
// ============================================================
// KONEKT — Delete Experience
// ============================================================
// DELETE /api/profile/delete_experience.php?experience_id=<id>
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('DELETE');
$user = requireAuth();

$expId = isset($_GET['experience_id']) ? (int) $_GET['experience_id'] : 0;

if (!$expId) {
    jsonError('experience_id is required.', 400);
}

$db = getDB();

// --- Verify ownership and delete ---
$stmt = $db->prepare('DELETE FROM experience WHERE id = :id AND user_id = :user_id');
$stmt->execute([':id' => $expId, ':user_id' => $user['id']]);

if ($stmt->rowCount() === 0) {
    jsonError('Experience entry not found or access denied.', 404);
}

jsonSuccess(null, 'Experience deleted successfully.');
