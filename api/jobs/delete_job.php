<?php
// ============================================================
// KONEKT — Delete Job Posting
// ============================================================
// DELETE /api/jobs/delete_job.php?job_id=<id>
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('DELETE');
$user = requireRole('employer');

$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;

if (!$jobId) {
    jsonError('job_id is required.', 400);
}

$db = getDB();

$stmt = $db->prepare('DELETE FROM job_postings WHERE id = :id AND employer_id = :employer_id');
$stmt->execute([':id' => $jobId, ':employer_id' => $user['id']]);

if ($stmt->rowCount() === 0) {
    jsonError('Job posting not found or access denied.', 404);
}

jsonSuccess(null, 'Job posting deleted successfully.');
