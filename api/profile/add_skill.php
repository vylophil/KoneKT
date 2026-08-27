<?php
// K
// POST /api/profile/add_skill.php
// Body (JSON):
//   - skill_id          (int, optional — use if skill exists in catalog)
//   - skill_name        (string, optional — creates new skill if not in catalog)
//   - proficiency_level (string, optional: beginner|intermediate|advanced|expert)
// Must provide either skill_id or skill_name.

require_once __DIR__ . '/../auth/session.php';

requireMethod('POST');
$user = requireAuth();
$data = getJsonBody();

$db = getDB();

$skillId          = isset($data['skill_id']) ? (int) $data['skill_id'] : null;
$skillName        = isset($data['skill_name']) ? trim($data['skill_name']) : null;
$proficiencyLevel = isset($data['proficiency_level']) ? $data['proficiency_level'] : 'beginner';

// Validate proficiency level
$validLevels = ['beginner', 'intermediate', 'advanced', 'expert'];
if (!validateEnum($proficiencyLevel, $validLevels)) {
    jsonError('Invalid proficiency_level. Allowed: ' . implode(', ', $validLevels), 422);
}

// Resolve skill ID
if (!$skillId && !$skillName) {
    jsonError('Provide either skill_id or skill_name.', 422);
}

if (!$skillId && $skillName) {
    // Check if skill already exists by name
    $stmt = $db->prepare('SELECT id FROM skills WHERE LOWER(name) = LOWER(:name)');
    $stmt->execute([':name' => $skillName]);
    $existing = $stmt->fetch();

    if ($existing) {
        $skillId = (int) $existing['id'];
    } else {
        // Create new skill
        $stmt = $db->prepare('INSERT INTO skills (name, category) VALUES (:name, :category)');
        $stmt->execute([
            ':name'     => sanitizeString($skillName),
            ':category' => isset($data['category']) ? sanitizeString($data['category']) : 'Other',
        ]);
        $skillId = (int) $db->lastInsertId();
    }
}

// Check if user already has this skill
$stmt = $db->prepare('SELECT id FROM user_skills WHERE user_id = :user_id AND skill_id = :skill_id');
$stmt->execute([':user_id' => $user['id'], ':skill_id' => $skillId]);

if ($stmt->fetch()) {
    jsonError('You already have this skill in your profile.', 409);
}

// Add skill to user profile
$stmt = $db->prepare('
    INSERT INTO user_skills (user_id, skill_id, proficiency_level)
    VALUES (:user_id, :skill_id, :proficiency_level)
');
$stmt->execute([
    ':user_id'           => $user['id'],
    ':skill_id'          => $skillId,
    ':proficiency_level' => $proficiencyLevel,
]);

jsonSuccess(['skill_id' => $skillId], 'Skill added to profile.', 201);
