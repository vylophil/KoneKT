<?php
// ============================================================
// KONEKT — List Applications
// ============================================================
// GET /api/jobs/list_applications.php
//
// For Job Seekers: lists their own applications
//   Query: ?page=1&limit=20&status=pending
//
// For Employers: lists applications for a specific job
//   Query: ?job_id=<id>&page=1&limit=20&status=pending
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('GET');
$user = requireAuth();

$db     = getDB();
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$status = $_GET['status'] ?? '';

if ($user['role'] === 'job_seeker') {
    // --- Job seeker: list own applications ---
    $where  = ['ja.user_id = :user_id'];
    $params = [':user_id' => $user['id']];

    if ($status !== '') {
        $where[]            = 'ja.status = :status';
        $params[':status']  = $status;
    }

    $whereClause = implode(' AND ', $where);

    // Count
    $stmt = $db->prepare("SELECT COUNT(*) FROM job_applications ja WHERE {$whereClause}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // Fetch
    $sql = "
        SELECT
            ja.id, ja.job_id, ja.status, ja.cover_letter, ja.resume_url,
            ja.applied_at, ja.updated_at,
            jp.title AS job_title, jp.location AS job_location, jp.job_type,
            c.name AS company_name, c.logo_url AS company_logo
        FROM job_applications ja
        JOIN job_postings jp ON jp.id = ja.job_id
        JOIN companies c ON c.id = jp.company_id
        WHERE {$whereClause}
        ORDER BY ja.applied_at DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $applications = $stmt->fetchAll();

} else {
    // --- Employer: list applications for a job ---
    $jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;

    if (!$jobId) {
        jsonError('job_id is required for employers.', 400);
    }

    // Verify ownership
    $stmt = $db->prepare('SELECT id FROM job_postings WHERE id = :id AND employer_id = :employer_id');
    $stmt->execute([':id' => $jobId, ':employer_id' => $user['id']]);
    if (!$stmt->fetch()) {
        jsonError('Job posting not found or access denied.', 404);
    }

    $where  = ['ja.job_id = :job_id'];
    $params = [':job_id' => $jobId];

    if ($status !== '') {
        $where[]            = 'ja.status = :status';
        $params[':status']  = $status;
    }

    $whereClause = implode(' AND ', $where);

    // Count
    $stmt = $db->prepare("SELECT COUNT(*) FROM job_applications ja WHERE {$whereClause}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // Fetch
    $sql = "
        SELECT
            ja.id, ja.user_id, ja.status, ja.cover_letter, ja.resume_url,
            ja.applied_at, ja.updated_at,
            u.first_name, u.last_name, u.email,
            p.headline, p.location AS applicant_location, p.avatar_url
        FROM job_applications ja
        JOIN users u ON u.id = ja.user_id
        LEFT JOIN profiles p ON p.user_id = u.id
        WHERE {$whereClause}
        ORDER BY ja.applied_at DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $applications = $stmt->fetchAll();
}

jsonSuccess([
    'applications' => $applications,
    'pagination'   => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => ceil($total / $limit),
    ],
], 'Applications retrieved successfully.');
