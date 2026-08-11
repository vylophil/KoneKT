<?php
// ============================================================
// KONEKT — Apply for a Job
// ============================================================
// POST /api/jobs/apply_job.php
//
// Body (JSON):
//   - job_id       (int, required)
//   - cover_letter (string, optional)
//
// Automatically attaches the user's resume from their profile.
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('POST');
$user = requireRole('job_seeker');
$data = getJsonBody();

if (!isset($data['job_id']) || !validatePositiveInt($data['job_id'])) {
    jsonError('Valid job_id is required.', 422);
}

$jobId = (int) $data['job_id'];
$db = getDB();

// --- Verify job exists and is active ---
$stmt = $db->prepare('SELECT id, deadline FROM job_postings WHERE id = :id AND is_active = 1');
$stmt->execute([':id' => $jobId]);
$job = $stmt->fetch();

if (!$job) {
    jsonError('Job posting not found or is no longer active.', 404);
}

// --- Check deadline ---
if ($job['deadline'] && strtotime($job['deadline']) < time()) {
    jsonError('The application deadline has passed.', 400);
}

// --- Check if already applied ---
$stmt = $db->prepare('SELECT id FROM job_applications WHERE job_id = :job_id AND user_id = :user_id');
$stmt->execute([':job_id' => $jobId, ':user_id' => $user['id']]);

if ($stmt->fetch()) {
    jsonError('You have already applied for this job.', 409);
}

// --- Get user's resume ---
$stmt = $db->prepare('SELECT resume_url FROM profiles WHERE user_id = :user_id');
$stmt->execute([':user_id' => $user['id']]);
$profile = $stmt->fetch();
$resumeUrl = $profile['resume_url'] ?? null;

// --- Insert application ---
$stmt = $db->prepare('
    INSERT INTO job_applications (job_id, user_id, cover_letter, resume_url)
    VALUES (:job_id, :user_id, :cover_letter, :resume_url)
');
$stmt->execute([
    ':job_id'       => $jobId,
    ':user_id'      => $user['id'],
    ':cover_letter' => isset($data['cover_letter']) ? sanitizeString($data['cover_letter']) : null,
    ':resume_url'   => $resumeUrl,
]);

$applicationId = (int) $db->lastInsertId();

jsonSuccess(['application_id' => $applicationId], 'Application submitted successfully.', 201);
