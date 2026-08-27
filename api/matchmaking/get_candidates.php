<?php
// K
// GET /api/matchmaking/get_candidates.php?job_id=<id>
// Returns top matching candidates for a specific job posting.
// Employer only.
// Query params:
//   - job_id     (int, required)
//   - min_score  (number, optional, default 0)
//   - page       (int, optional, default 1)
//   - limit      (int, optional, default 20)

require_once __DIR__ . '/../auth/session.php';

requireMethod('GET');
$user = requireRole('employer');

$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;

if (!$jobId) {
    jsonError('job_id is required.', 400);
}

$db = getDB();

// Verify ownership
$stmt = $db->prepare('SELECT id FROM job_postings WHERE id = :id AND employer_id = :employer_id');
$stmt->execute([':id' => $jobId, ':employer_id' => $user['id']]);

if (!$stmt->fetch()) {
    jsonError('Job posting not found or access denied.', 404);
}

$minScore = isset($_GET['min_score']) ? (float) $_GET['min_score'] : 0;
$page     = max(1, (int) ($_GET['page'] ?? 1));
$limit    = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$offset   = ($page - 1) * $limit;

// Count total candidates
$stmt = $db->prepare('
    SELECT COUNT(*) FROM job_matches jm
    JOIN users u ON u.id = jm.user_id AND u.is_active = 1
    WHERE jm.job_id = :job_id AND jm.match_score >= :min_score
');
$stmt->execute([':job_id' => $jobId, ':min_score' => $minScore]);
$total = (int) $stmt->fetchColumn();

// Fetch candidates
$stmt = $db->prepare('
    SELECT
        jm.match_score, jm.skill_score, jm.experience_score, jm.education_score,
        u.id AS user_id, u.first_name, u.last_name, u.email,
        p.headline, p.location, p.avatar_url, p.resume_url,
        p.industry, p.years_of_experience
    FROM job_matches jm
    JOIN users u ON u.id = jm.user_id AND u.is_active = 1
    LEFT JOIN profiles p ON p.user_id = u.id
    WHERE jm.job_id = :job_id AND jm.match_score >= :min_score
    ORDER BY jm.match_score DESC
    LIMIT :limit OFFSET :offset
');
$stmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
$stmt->bindValue(':min_score', $minScore);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$candidates = $stmt->fetchAll();

// Attach skills to each candidate
foreach ($candidates as &$candidate) {
    $stmt = $db->prepare('
        SELECT s.name, s.category, us.proficiency_level, us.endorsement_count
        FROM user_skills us
        JOIN skills s ON s.id = us.skill_id
        WHERE us.user_id = :user_id
        ORDER BY us.endorsement_count DESC
    ');
    $stmt->execute([':user_id' => $candidate['user_id']]);
    $candidate['skills'] = $stmt->fetchAll();

    // Check if they applied
    $stmt = $db->prepare('
        SELECT id, status FROM job_applications
        WHERE job_id = :job_id AND user_id = :user_id
    ');
    $stmt->execute([':job_id' => $jobId, ':user_id' => $candidate['user_id']]);
    $candidate['application'] = $stmt->fetch() ?: null;
}

jsonSuccess([
    'candidates' => $candidates,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => ceil($total / $limit),
    ],
], 'Candidates retrieved successfully.');
