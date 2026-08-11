<?php
// ============================================================
// KONEKT — Remove Skill from Profile
// ============================================================
// DELETE /api/profile/remove_skill.php?skill_id=<id>
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('DELETE');
$user = requireAuth();

$skillId = isset($_GET['skill_id']) ? (int) $_GET['skill_id'] : 0;

if (!$skillId) {
    jsonError('skill_id is required.', 400);
}

$db = getDB();

// --- Delete the user_skill entry ---
$stmt = $db->prepare('DELETE FROM user_skills WHERE user_id = :user_id AND skill_id = :skill_id');
$stmt->execute([':user_id' => $user['id'], ':skill_id' => $skillId]);

if ($stmt->rowCount() === 0) {
    jsonError('Skill not found in your profile.', 404);
}

// --- Also remove associated endorsements ---
$stmt = $db->prepare('DELETE FROM endorsements WHERE endorsed_user_id = :user_id AND skill_id = :skill_id');
$stmt->execute([':user_id' => $user['id'], ':skill_id' => $skillId]);

jsonSuccess(null, 'Skill removed from profile.');
