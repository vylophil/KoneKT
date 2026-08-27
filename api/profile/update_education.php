<?php
// K
// PUT /api/profile/update_education.php
// Body (JSON):
//   - education_id   (int, required)
//   - institution    (string, optional)
//   - degree         (string, optional)
//   - field_of_study (string, optional)
//   - start_date     (string, optional, Y-m-d)
//   - end_date       (string, optional, Y-m-d)
//   - is_current     (bool, optional)
//   - grade          (string, optional)
//   - description    (string, optional)

require_once __DIR__ . '/../auth/session.php';

requireMethod('PUT');
$user = requireAuth();
$data = getJsonBody();

if (!isset($data['education_id']) || !validatePositiveInt($data['education_id'])) {
    jsonError('Valid education_id is required.', 422);
}

$eduId = (int) $data['education_id'];
$db = getDB();

// Verify ownership
$stmt = $db->prepare('SELECT id FROM education WHERE id = :id AND user_id = :user_id');
$stmt->execute([':id' => $eduId, ':user_id' => $user['id']]);

if (!$stmt->fetch()) {
    jsonError('Education entry not found or access denied.', 404);
}

// Validate degree if provided
if (isset($data['degree'])) {
    $validDegrees = ['high_school', 'associate', 'bachelors', 'masters', 'doctorate', 'certification', 'other'];
    if (!validateEnum($data['degree'], $validDegrees)) {
        jsonError('Invalid degree. Allowed: ' . implode(', ', $validDegrees), 422);
    }
}

// Build dynamic update
$allowedFields = ['institution', 'degree', 'field_of_study', 'start_date', 'end_date', 'is_current', 'grade', 'description'];
$setClauses = [];
$params     = [':id' => $eduId];

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

$sql = 'UPDATE education SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
$stmt = $db->prepare($sql);
$stmt->execute($params);

jsonSuccess(null, 'Education updated successfully.');
