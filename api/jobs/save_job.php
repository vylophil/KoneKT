<?php
// K
// POST /api/jobs/save_job.php
// Body (JSON):
//   - job_id (int, required)
// Toggles the saved state: saves if not saved, removes if already saved.

require_once __DIR__ . '/../auth/session.php';

requireMethod('POST');
$user = requireRole('job_seeker');
$data = getJsonBody();

if (!isset($data['job_id']) || !validatePositiveInt($data['job_id'])) {
    jsonError('Valid job_id is required.', 422);
}

$jobId = (int) $data['job_id'];
$db = getDB();

// Check job exists
$stmt = $db->prepare('SELECT id FROM job_postings WHERE id = :id');
$stmt->execute([':id' => $jobId]);

if (!$stmt->fetch()) {
    jsonError('Job posting not found.', 404);
}

// Toggle save
$stmt = $db->prepare('SELECT id FROM saved_jobs WHERE user_id = :user_id AND job_id = :job_id');
$stmt->execute([':user_id' => $user['id'], ':job_id' => $jobId]);

if ($stmt->fetch()) {
    // Already saved — remove
    $db->prepare('DELETE FROM saved_jobs WHERE user_id = :user_id AND job_id = :job_id')
       ->execute([':user_id' => $user['id'], ':job_id' => $jobId]);

    jsonSuccess(['saved' => false], 'Job removed from saved list.');
} else {
    // Not saved — add
    $db->prepare('INSERT INTO saved_jobs (user_id, job_id) VALUES (:user_id, :job_id)')
       ->execute([':user_id' => $user['id'], ':job_id' => $jobId]);

    jsonSuccess(['saved' => true], 'Job saved successfully.');
}
