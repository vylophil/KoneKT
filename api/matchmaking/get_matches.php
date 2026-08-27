<?php
// K
// GET /api/matchmaking/get_matches.php
// Returns top matching jobs for the authenticated user.
// Query params:
//   - min_score  (number, optional, default 0)
//   - page       (int, optional, default 1)
//   - limit      (int, optional, default 20)

require_once __DIR__ . '/../auth/session.php';

requireMethod('GET');
$user = requireRole('job_seeker');

$db       = getDB();
$minScore = isset($_GET['min_score']) ? (float) $_GET['min_score'] : 0;
$page     = max(1, (int) ($_GET['page'] ?? 1));
$limit    = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$offset   = ($page - 1) * $limit;

// Count total matches
$stmt = $db->prepare('
    SELECT COUNT(*) FROM job_matches jm
    JOIN job_postings jp ON jp.id = jm.job_id AND jp.is_active = 1
    WHERE jm.user_id = :user_id AND jm.match_score >= :min_score
');
$stmt->execute([':user_id' => $user['id'], ':min_score' => $minScore]);
$total = (int) $stmt->fetchColumn();

// Fetch matches with job details
$stmt = $db->prepare('
    SELECT
        jm.match_score, jm.skill_score, jm.experience_score, jm.education_score,
        jm.computed_at,
        jp.id AS job_id, jp.title, jp.description, jp.location, jp.job_type,
        jp.work_arrangement, jp.salary_min, jp.salary_max, jp.salary_currency,
        jp.experience_level, jp.deadline, jp.created_at AS job_posted_at,
        c.name AS company_name, c.logo_url AS company_logo, c.industry AS company_industry
    FROM job_matches jm
    JOIN job_postings jp ON jp.id = jm.job_id AND jp.is_active = 1
    JOIN companies c ON c.id = jp.company_id
    WHERE jm.user_id = :user_id AND jm.match_score >= :min_score
    ORDER BY jm.match_score DESC
    LIMIT :limit OFFSET :offset
');
$stmt->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
$stmt->bindValue(':min_score', $minScore);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$matches = $stmt->fetchAll();

// Attach skills to each matched job
if (!empty($matches)) {
    $jobIds = array_column($matches, 'job_id');
    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));

    $skillStmt = $db->prepare("
        SELECT js.job_id, s.name AS skill_name, js.importance
        FROM job_skills js
        JOIN skills s ON s.id = js.skill_id
        WHERE js.job_id IN ({$placeholders})
    ");
    $skillStmt->execute($jobIds);
    $allSkills = $skillStmt->fetchAll();

    $skillsByJob = [];
    foreach ($allSkills as $sk) {
        $skillsByJob[$sk['job_id']][] = $sk;
    }

    foreach ($matches as &$match) {
        $match['job_skills'] = $skillsByJob[$match['job_id']] ?? [];
    }
}

jsonSuccess([
    'matches'    => $matches,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => ceil($total / $limit),
    ],
], 'Job matches retrieved successfully.');
