<?php
// ============================================================
// KONEKT — Add Experience
// ============================================================
// POST /api/profile/add_experience.php
//
// Body (JSON):
//   - company_name  (string, required)
//   - job_title     (string, required)
//   - location      (string, optional)
//   - start_date    (string, required, Y-m-d)
//   - end_date      (string, optional, Y-m-d)
//   - is_current    (bool, optional, default false)
//   - description   (string, optional)
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('POST');
$user = requireAuth();
$data = getJsonBody();

// --- Validate ---
$errors = validateRequired($data, ['company_name', 'job_title', 'start_date']);
if (!empty($errors)) {
    jsonError('Validation failed.', 422, $errors);
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
    INSERT INTO experience (user_id, company_name, job_title, location, start_date, end_date, is_current, description)
    VALUES (:user_id, :company_name, :job_title, :location, :start_date, :end_date, :is_current, :description)
');
$stmt->execute([
    ':user_id'      => $user['id'],
    ':company_name' => sanitizeString($data['company_name']),
    ':job_title'    => sanitizeString($data['job_title']),
    ':location'     => isset($data['location']) ? sanitizeString($data['location']) : null,
    ':start_date'   => $data['start_date'],
    ':end_date'     => $endDate,
    ':is_current'   => $isCurrent ? 1 : 0,
    ':description'  => isset($data['description']) ? sanitizeString($data['description']) : null,
]);

$experienceId = (int) $db->lastInsertId();

jsonSuccess(['experience_id' => $experienceId], 'Experience added successfully.', 201);
