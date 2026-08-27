<?php
// K
// GET /api/jobs/get_job.php?job_id=<id>

require_once __DIR__ . '/../auth/session.php';

requireMethod('GET');
requireAuth();

$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;

if (!$jobId) {
    jsonError('job_id is required.', 400);
}

$db = getDB();

// Fetch job with company info
$stmt = $db->prepare('
    SELECT
        jp.*,
        c.name AS company_name, c.description AS company_description,
        c.logo_url AS company_logo, c.website AS company_website,
        c.industry AS company_industry, c.location AS company_location,
        c.company_size,
        u.first_name AS employer_first_name, u.last_name AS employer_last_name
    FROM job_postings jp
    JOIN companies c ON c.id = jp.company_id
    JOIN users u ON u.id = jp.employer_id
    WHERE jp.id = :job_id
');
$stmt->execute([':job_id' => $jobId]);
$job = $stmt->fetch();

if (!$job) {
    jsonError('Job posting not found.', 404);
}

// Fetch required skills
$stmt = $db->prepare('
    SELECT s.id, s.name, s.category, js.importance
    FROM job_skills js
    JOIN skills s ON s.id = js.skill_id
    WHERE js.job_id = :job_id
    ORDER BY js.importance ASC, s.name ASC
');
$stmt->execute([':job_id' => $jobId]);
$job['skills'] = $stmt->fetchAll();

// Check if current user has applied
$currentUserId = getCurrentUserId();
if ($currentUserId) {
    $stmt = $db->prepare('
        SELECT id, status, applied_at FROM job_applications
        WHERE job_id = :job_id AND user_id = :user_id
    ');
    $stmt->execute([':job_id' => $jobId, ':user_id' => $currentUserId]);
    $job['user_application'] = $stmt->fetch() ?: null;

    // Check if saved
    $stmt = $db->prepare('
        SELECT id FROM saved_jobs
        WHERE job_id = :job_id AND user_id = :user_id
    ');
    $stmt->execute([':job_id' => $jobId, ':user_id' => $currentUserId]);
    $job['is_saved'] = (bool) $stmt->fetch();
}

// Application count (for employer)
$stmt = $db->prepare('SELECT COUNT(*) FROM job_applications WHERE job_id = :job_id');
$stmt->execute([':job_id' => $jobId]);
$job['application_count'] = (int) $stmt->fetchColumn();

jsonSuccess($job, 'Job retrieved successfully.');
