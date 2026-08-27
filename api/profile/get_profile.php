<?php
// K
// GET /api/profile/get_profile.php?user_id=<id>
// Returns the full profile for a given user, including:
//   - Basic info, headline, bio, location
//   - Skills with proficiency & endorsement counts
//   - Experience history
//   - Education history
//   - Company info (if employer)

require_once __DIR__ . '/../auth/session.php';

requireMethod('GET');
requireAuth();

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : getCurrentUserId();

if (!$userId) {
    jsonError('User ID is required.', 400);
}

$db = getDB();

// Fetch user + profile
$stmt = $db->prepare('
    SELECT
        u.id, u.email, u.role, u.first_name, u.last_name, u.created_at,
        p.headline, p.bio, p.location, p.phone, p.website,
        p.avatar_url, p.resume_url, p.linkedin_url, p.github_url,
        p.industry, p.years_of_experience
    FROM users u
    LEFT JOIN profiles p ON p.user_id = u.id
    WHERE u.id = :user_id AND u.is_active = 1
');
$stmt->execute([':user_id' => $userId]);
$profile = $stmt->fetch();

if (!$profile) {
    jsonError('User not found.', 404);
}

// Fetch skills
$stmt = $db->prepare('
    SELECT s.id, s.name, s.category, us.proficiency_level, us.endorsement_count
    FROM user_skills us
    JOIN skills s ON s.id = us.skill_id
    WHERE us.user_id = :user_id
    ORDER BY us.endorsement_count DESC, s.name ASC
');
$stmt->execute([':user_id' => $userId]);
$profile['skills'] = $stmt->fetchAll();

// Fetch experience
$stmt = $db->prepare('
    SELECT id, company_name, job_title, location, start_date, end_date, is_current, description
    FROM experience
    WHERE user_id = :user_id
    ORDER BY is_current DESC, start_date DESC
');
$stmt->execute([':user_id' => $userId]);
$profile['experience'] = $stmt->fetchAll();

// Fetch education
$stmt = $db->prepare('
    SELECT id, institution, degree, field_of_study, start_date, end_date, is_current, grade, description
    FROM education
    WHERE user_id = :user_id
    ORDER BY is_current DESC, start_date DESC
');
$stmt->execute([':user_id' => $userId]);
$profile['education'] = $stmt->fetchAll();

// Fetch company info (if employer)
if ($profile['role'] === 'employer') {
    $stmt = $db->prepare('
        SELECT id, name, description, industry, website, logo_url, location, company_size, founded_year
        FROM companies
        WHERE user_id = :user_id
    ');
    $stmt->execute([':user_id' => $userId]);
    $profile['company'] = $stmt->fetch() ?: null;
}

// Connection status (if viewing someone else's profile)
$currentUserId = getCurrentUserId();
if ($currentUserId && $currentUserId !== $userId) {
    $stmt = $db->prepare('
        SELECT status FROM connections
        WHERE (requester_id = :uid1 AND receiver_id = :uid2)
           OR (requester_id = :uid2b AND receiver_id = :uid1b)
    ');
    $stmt->execute([
        ':uid1'  => $currentUserId,
        ':uid2'  => $userId,
        ':uid2b' => $userId,
        ':uid1b' => $currentUserId,
    ]);
    $conn = $stmt->fetch();
    $profile['connection_status'] = $conn ? $conn['status'] : 'none';
}

jsonSuccess($profile, 'Profile retrieved successfully.');
