<?php
// K
// POST /api/profile/add_education.php
// Body (JSON):
//   - institution    (string, required)
//   - degree         (string, required: high_school|associate|bachelors|masters|doctorate|certification|other)
//   - field_of_study (string, optional)
//   - start_date     (string, required, Y-m-d)
//   - end_date       (string, optional, Y-m-d)
//   - is_current     (bool, optional)
//   - grade          (string, optional)
//   - description    (string, optional)

require_once __DIR__ . '/../auth/session.php';

requireMethod('POST');
$user = requireAuth();
$data = getJsonBody();

// Validate
$errors = validateRequired($data, ['institution', 'degree', 'start_date']);
if (!empty($errors)) {
    jsonError('Validation failed.', 422, $errors);
}

$validDegrees = ['high_school', 'associate', 'bachelors', 'masters', 'doctorate', 'certification', 'other'];
if (!validateEnum($data['degree'], $validDegrees)) {
    jsonError('Invalid degree. Allowed: ' . implode(', ', $validDegrees), 422);
}

if (!validateDate($data['start_date'])) {
    jsonError('Invalid start_date format. Use Y-m-d.', 422);
}

$isCurrent = !empty($data['is_current']);
$endDate   = null;

if (!$isCurrent && isset($data['end_date'])) {
    if (!validateDate($data['end_date'])) {
        jsonError('Invalid end_date format. Use Y-m-d.', 422);
    }
    $endDate = $data['end_date'];
}

$db = getDB();

$stmt = $db->prepare('
    INSERT INTO education (user_id, institution, degree, field_of_study, start_date, end_date, is_current, grade, description)
    VALUES (:user_id, :institution, :degree, :field_of_study, :start_date, :end_date, :is_current, :grade, :description)
');
$stmt->execute([
    ':user_id'        => $user['id'],
    ':institution'    => sanitizeString($data['institution']),
    ':degree'         => $data['degree'],
    ':field_of_study' => isset($data['field_of_study']) ? sanitizeString($data['field_of_study']) : null,
    ':start_date'     => $data['start_date'],
    ':end_date'       => $endDate,
    ':is_current'     => $isCurrent ? 1 : 0,
    ':grade'          => isset($data['grade']) ? sanitizeString($data['grade']) : null,
    ':description'    => isset($data['description']) ? sanitizeString($data['description']) : null,
]);

$educationId = (int) $db->lastInsertId();

jsonSuccess(['education_id' => $educationId], 'Education added successfully.', 201);
