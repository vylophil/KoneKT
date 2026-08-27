<?php
// K
// POST /api/profile/view_profile.php
// Body (JSON):
//   - viewed_user_id (int, required)

require_once __DIR__ . '/../auth/session.php';

requireMethod('POST');
$user = requireAuth();
$data = getJsonBody();

if (!isset($data['viewed_user_id']) || !validatePositiveInt($data['viewed_user_id'])) {
    jsonError('Valid viewed_user_id is required.', 422);
}

$viewedId = (int) $data['viewed_user_id'];

// Don't log self-views
if ($viewedId === $user['id']) {
    jsonSuccess(null, 'Self-view not logged.');
    return;
}

$db = getDB();

// Verify viewed user exists
$stmt = $db->prepare('SELECT id FROM users WHERE id = :id AND is_active = 1');
$stmt->execute([':id' => $viewedId]);

if (!$stmt->fetch()) {
    jsonError('User not found.', 404);
}

// Throttle: don't log more than one view per viewer per day
$stmt = $db->prepare('
    SELECT id FROM profile_views
    WHERE viewer_id = :viewer_id AND viewed_id = :viewed_id
    AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
');
$stmt->execute([':viewer_id' => $user['id'], ':viewed_id' => $viewedId]);

if (!$stmt->fetch()) {
    $stmt = $db->prepare('
        INSERT INTO profile_views (viewer_id, viewed_id)
        VALUES (:viewer_id, :viewed_id)
    ');
    $stmt->execute([':viewer_id' => $user['id'], ':viewed_id' => $viewedId]);
}

jsonSuccess(null, 'Profile view logged.');
