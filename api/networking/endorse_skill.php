<?php
// K
// POST /api/networking/endorse_skill.php
// Body (JSON):
//   - user_id  (int, required — the user to endorse)
//   - skill_id (int, required — the skill to endorse)
// Requirements:
//   - Must be connected (accepted) with the user
//   - Cannot endorse yourself
//   - Cannot endorse the same skill twice

require_once __DIR__ . '/../auth/session.php';

requireMethod('POST');
$user = requireAuth();
$data = getJsonBody();

$errors = validateRequired($data, ['user_id', 'skill_id']);
if (!empty($errors)) {
    jsonError('Validation failed.', 422, $errors);
}

$endorsedUserId = (int) $data['user_id'];
$skillId        = (int) $data['skill_id'];

if ($endorsedUserId === $user['id']) {
    jsonError('You cannot endorse yourself.', 400);
}

$db = getDB();

// Verify connection
$stmt = $db->prepare('
    SELECT id FROM connections
    WHERE status = "accepted"
    AND (
        (requester_id = :uid1 AND receiver_id = :uid2)
        OR (requester_id = :uid2b AND receiver_id = :uid1b)
    )
');
$stmt->execute([
    ':uid1'  => $user['id'],
    ':uid2'  => $endorsedUserId,
    ':uid2b' => $endorsedUserId,
    ':uid1b' => $user['id'],
]);

if (!$stmt->fetch()) {
    jsonError('You must be connected to endorse this user.', 403);
}

// Verify the user has this skill
$stmt = $db->prepare('
    SELECT id FROM user_skills WHERE user_id = :user_id AND skill_id = :skill_id
');
$stmt->execute([':user_id' => $endorsedUserId, ':skill_id' => $skillId]);

if (!$stmt->fetch()) {
    jsonError('This user does not have this skill in their profile.', 404);
}

// Check for duplicate endorsement
$stmt = $db->prepare('
    SELECT id FROM endorsements
    WHERE endorser_id = :endorser_id AND endorsed_user_id = :endorsed_user_id AND skill_id = :skill_id
');
$stmt->execute([
    ':endorser_id'      => $user['id'],
    ':endorsed_user_id' => $endorsedUserId,
    ':skill_id'         => $skillId,
]);

if ($stmt->fetch()) {
    jsonError('You have already endorsed this skill.', 409);
}

try {
    $db->beginTransaction();

    // Insert endorsement
    $stmt = $db->prepare('
        INSERT INTO endorsements (endorser_id, endorsed_user_id, skill_id)
        VALUES (:endorser_id, :endorsed_user_id, :skill_id)
    ');
    $stmt->execute([
        ':endorser_id'      => $user['id'],
        ':endorsed_user_id' => $endorsedUserId,
        ':skill_id'         => $skillId,
    ]);

    // Update endorsement count on user_skills
    $stmt = $db->prepare('
        UPDATE user_skills
        SET endorsement_count = endorsement_count + 1
        WHERE user_id = :user_id AND skill_id = :skill_id
    ');
    $stmt->execute([':user_id' => $endorsedUserId, ':skill_id' => $skillId]);

    $db->commit();

    jsonSuccess(null, 'Skill endorsed successfully.', 201);

} catch (PDOException $e) {
    $db->rollBack();
    jsonError('Failed to endorse skill: ' . $e->getMessage(), 500);
}
