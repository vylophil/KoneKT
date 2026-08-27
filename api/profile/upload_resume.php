<?php
// Upload Resume (PDF)

require_once __DIR__ . '/../auth/session.php';

requireMethod('POST');
$user = requireRole('job_seeker');

// Check file is present
if (!isset($_FILES['resume'])) {
    jsonError('No resume file uploaded.', 400);
}

$file = $_FILES['resume'];

// Validate file
$errors = validateFileUpload($file, ['application/pdf'], 10);
if (!empty($errors)) {
    jsonError('Resume upload validation failed.', 422, $errors);
}

// Verify file extension (double check)
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    jsonError('Only PDF files are accepted.', 422);
}

// Generate unique filename
$filename = 'resume_' . $user['id'] . '_' . time() . '.pdf';
$uploadDir = __DIR__ . '/../../uploads/resumes/';

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destination = $uploadDir . $filename;

// Delete old resume if exists
$db = getDB();
$stmt = $db->prepare('SELECT resume_url FROM profiles WHERE user_id = :user_id');
$stmt->execute([':user_id' => $user['id']]);
$profile = $stmt->fetch();

if ($profile && $profile['resume_url']) {
    $oldFile = __DIR__ . '/../../' . $profile['resume_url'];
    if (file_exists($oldFile)) {
        unlink($oldFile);
    }
}

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    jsonError('Failed to save the resume file.', 500);
}

// Update profile with resume URL
$resumeUrl = 'uploads/resumes/' . $filename;
$stmt = $db->prepare('UPDATE profiles SET resume_url = :resume_url WHERE user_id = :user_id');
$stmt->execute([
    ':resume_url' => $resumeUrl,
    ':user_id'    => $user['id'],
]);

jsonSuccess([
    'resume_url' => $resumeUrl,
    'filename'   => $filename,
], 'Resume uploaded successfully.');
