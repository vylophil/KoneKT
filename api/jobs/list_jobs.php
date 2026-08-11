<?php
// ============================================================
// KONEKT — List / Search Job Postings
// ============================================================
// GET /api/jobs/list_jobs.php
//
// Query params:
//   - search            (string, optional — search title/description)
//   - job_type          (string, optional)
//   - work_arrangement  (string, optional)
//   - experience_level  (string, optional)
//   - location          (string, optional)
//   - skill_ids         (comma-separated int, optional)
//   - company_id        (int, optional)
//   - page              (int, optional, default 1)
//   - limit             (int, optional, default 20, max 100)
//   - sort              (string, optional: newest|salary_high|salary_low)
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('GET');
requireAuth();

$db = getDB();

// --- Parse params ---
$search           = isset($_GET['search']) ? trim($_GET['search']) : '';
$jobType          = $_GET['job_type'] ?? '';
$workArrangement  = $_GET['work_arrangement'] ?? '';
$experienceLevel  = $_GET['experience_level'] ?? '';
$location         = isset($_GET['location']) ? trim($_GET['location']) : '';
$skillIds         = isset($_GET['skill_ids']) ? $_GET['skill_ids'] : '';
$companyId        = isset($_GET['company_id']) ? (int) $_GET['company_id'] : 0;
$page             = max(1, (int) ($_GET['page'] ?? 1));
$limit            = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$sort             = $_GET['sort'] ?? 'newest';
$offset           = ($page - 1) * $limit;

// --- Build query ---
$where  = ['jp.is_active = 1'];
$params = [];
$joins  = '';

if ($search !== '') {
    $where[]            = '(jp.title LIKE :search OR jp.description LIKE :search2)';
    $params[':search']  = "%{$search}%";
    $params[':search2'] = "%{$search}%";
}

if ($jobType !== '') {
    $where[]              = 'jp.job_type = :job_type';
    $params[':job_type']  = $jobType;
}

if ($workArrangement !== '') {
    $where[]                      = 'jp.work_arrangement = :work_arrangement';
    $params[':work_arrangement']  = $workArrangement;
}

if ($experienceLevel !== '') {
    $where[]                     = 'jp.experience_level = :experience_level';
    $params[':experience_level'] = $experienceLevel;
}

if ($location !== '') {
    $where[]               = 'jp.location LIKE :location';
    $params[':location']   = "%{$location}%";
}

if ($companyId > 0) {
    $where[]                 = 'jp.company_id = :company_id';
    $params[':company_id']   = $companyId;
}

// --- Filter by skills ---
if ($skillIds !== '') {
    $skillIdArray = array_filter(array_map('intval', explode(',', $skillIds)));
    if (!empty($skillIdArray)) {
        $placeholders = [];
        foreach ($skillIdArray as $i => $sid) {
            $key = ":skill_{$i}";
            $placeholders[] = $key;
            $params[$key] = $sid;
        }
        $joins .= ' INNER JOIN job_skills js_filter ON js_filter.job_id = jp.id ';
        $where[] = 'js_filter.skill_id IN (' . implode(',', $placeholders) . ')';
    }
}

// --- Sort ---
$orderBy = 'jp.created_at DESC'; // default: newest
if ($sort === 'salary_high') {
    $orderBy = 'jp.salary_max DESC';
} elseif ($sort === 'salary_low') {
    $orderBy = 'jp.salary_min ASC';
}

$whereClause = implode(' AND ', $where);

// --- Count total ---
$countSql = "SELECT COUNT(DISTINCT jp.id) as total FROM job_postings jp {$joins} WHERE {$whereClause}";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

// --- Fetch jobs ---
$sql = "
    SELECT DISTINCT
        jp.id, jp.title, jp.description, jp.location, jp.job_type,
        jp.work_arrangement, jp.salary_min, jp.salary_max, jp.salary_currency,
        jp.experience_level, jp.min_experience_years, jp.education_requirement,
        jp.deadline, jp.created_at,
        c.id AS company_id, c.name AS company_name, c.logo_url AS company_logo,
        c.industry AS company_industry
    FROM job_postings jp
    JOIN companies c ON c.id = jp.company_id
    {$joins}
    WHERE {$whereClause}
    ORDER BY {$orderBy}
    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$jobs = $stmt->fetchAll();

// --- Attach skills to each job ---
if (!empty($jobs)) {
    $jobIds = array_column($jobs, 'id');
    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));

    $skillSql = "
        SELECT js.job_id, s.id AS skill_id, s.name AS skill_name, s.category, js.importance
        FROM job_skills js
        JOIN skills s ON s.id = js.skill_id
        WHERE js.job_id IN ({$placeholders})
    ";
    $stmt = $db->prepare($skillSql);
    $stmt->execute($jobIds);
    $allSkills = $stmt->fetchAll();

    $skillsByJob = [];
    foreach ($allSkills as $sk) {
        $skillsByJob[$sk['job_id']][] = $sk;
    }

    foreach ($jobs as &$job) {
        $job['skills'] = $skillsByJob[$job['id']] ?? [];
    }
}

jsonSuccess([
    'jobs'       => $jobs,
    'pagination' => [
        'page'       => $page,
        'limit'      => $limit,
        'total'      => $total,
        'total_pages' => ceil($total / $limit),
    ],
], 'Jobs retrieved successfully.');
