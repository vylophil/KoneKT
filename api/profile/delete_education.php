<?php
// K
// DELETE /api/profile/delete_education.php?education_id=<id>

require_once __DIR__ . '/../auth/session.php';

requireMethod('DELETE');
$user = requireAuth();

$eduId = isset($_GET['education_id']) ? (int) $_GET['education_id'] : 0;

if (!$eduId) {
    jsonError('education_id is required.', 400);
}

$db = getDB();

$stmt = $db->prepare('DELETE FROM education WHERE id = :id AND user_id = :user_id');
$stmt->execute([':id' => $eduId, ':user_id' => $user['id']]);

if ($stmt->rowCount() === 0) {
    jsonError('Education entry not found or access denied.', 404);
}

jsonSuccess(null, 'Education deleted successfully.');
