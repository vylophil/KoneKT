<?php
// K
// PUT /api/profile/update_experience.php
// Body (JSON):
//   - experience_id (int, required)
//   - company_name  (string, optional)
//   - job_title     (string, optional)
//   - location      (string, optional)
//   - start_date    (string, optional, Y-m-d)
//   - end_date      (string, optional, Y-m-d)
//   - is_current    (bool, optional)
//   - description   (string, optional)

require_once __DIR__ . '/../auth/session.php';

requireMethod('PUT');
$user = requireAuth();
$data = getJsonBody();

if (!isset($data['experience_id']) || !validatePositiveInt($data['experience_id'])) {
    jsonError('Valid experience_id is required.', 422);
}

$expId = (int) $data['experience_id'];
$db = getDB();

// Verify ownership
$stmt = $db->prepare('SELECT id FROM experience WHERE id = :id AND user_id = :user_id');
$stmt->execute([':id' => $expId, ':user_id' => $user['id']]);

if (!$stmt->fetch()) {
    jsonError('Experience entry not found or access denied.', 404);
}

// Build dynamic update
$allowedFields = ['company_name', 'job_title', 'location', 'start_date', 'end_date', 'is_current', 'description'];
$setClauses = [];
$params     = [':id' => $expId];

foreach ($allowedFields as $field) {
    if (isset($data[$field])) {
        if (in_array($field, ['start_date', 'end_date']) && !validateDate($data[$field])) {
            jsonError("Invalid {$field} format. Use Y-m-d.", 422);
        }
        $setClauses[]        = "{$field} = :{$field}";
        $params[":{$field}"] = ($field === 'is_current')
            ? (int) $data[$field]
            : sanitizeString((string) $data[$field]);
    }
}

if (empty($setClauses)) {
    jsonError('No fields to update.', 400);
}

$sql = 'UPDATE experience SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
$stmt = $db->prepare($sql);
$stmt->execute($params);

jsonSuccess(null, 'Experience updated successfully.');
