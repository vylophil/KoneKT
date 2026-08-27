<?php
// K
// PUT /api/jobs/update_application.php
// For Employers:
//   Body: { application_id, status }
//   Allowed statuses: reviewing, shortlisted, interview, offered, rejected
// For Job Seekers:
//   Body: { application_id, status }
//   Allowed statuses: withdrawn, accepted

require_once __DIR__ . '/../auth/session.php';

requireMethod('PUT');
$user = requireAuth();
$data = getJsonBody();

$errors = validateRequired($data, ['application_id', 'status']);
if (!empty($errors)) {
    jsonError('Validation failed.', 422, $errors);
}

$appId     = (int) $data['application_id'];
$newStatus = $data['status'];
$db        = getDB();

if ($user['role'] === 'employer') {
    // Employer updates application status
    $allowedStatuses = ['reviewing', 'shortlisted', 'interview', 'offered', 'rejected'];

    if (!validateEnum($newStatus, $allowedStatuses)) {
        jsonError('Invalid status. Allowed: ' . implode(', ', $allowedStatuses), 422);
    }

    // Verify the application belongs to one of the employer's jobs
    $stmt = $db->prepare('
        SELECT ja.id FROM job_applications ja
        JOIN job_postings jp ON jp.id = ja.job_id
        WHERE ja.id = :app_id AND jp.employer_id = :employer_id
    ');
    $stmt->execute([':app_id' => $appId, ':employer_id' => $user['id']]);

    if (!$stmt->fetch()) {
        jsonError('Application not found or access denied.', 404);
    }

} else {
    // Job seeker can withdraw or accept
    $allowedStatuses = ['withdrawn', 'accepted'];

    if (!validateEnum($newStatus, $allowedStatuses)) {
        jsonError('Invalid status. Allowed: ' . implode(', ', $allowedStatuses), 422);
    }

    $stmt = $db->prepare('SELECT id FROM job_applications WHERE id = :app_id AND user_id = :user_id');
    $stmt->execute([':app_id' => $appId, ':user_id' => $user['id']]);

    if (!$stmt->fetch()) {
        jsonError('Application not found or access denied.', 404);
    }
}

// Update status
$stmt = $db->prepare('UPDATE job_applications SET status = :status WHERE id = :id');
$stmt->execute([':status' => $newStatus, ':id' => $appId]);

jsonSuccess(null, "Application status updated to '{$newStatus}'.");
