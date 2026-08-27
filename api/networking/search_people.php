<?php
// ============================================================
// KONEKT — Search People / Discover Profiles
// ============================================================
// GET /api/networking/search_people.php
//
// Query params:
//   - search    (string, optional — search by name, headline)
//   - skill_ids (comma-separated int, optional)
//   - industry  (string, optional)
//   - location  (string, optional)
//   - role      (string, optional: job_seeker|employer)
//   - page      (int, optional, default 1)
//   - limit     (int, optional, default 20)
// ============================================================

require_once __DIR__ . '/../auth/session.php';

requireMethod('GET');
$user = requireAuth();

$db       = getDB();
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';
$skillIds = isset($_GET['skill_ids']) ? $_GET['skill_ids'] : '';
$industry = isset($_GET['industry']) ? trim($_GET['industry']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$role     = $_GET['role'] ?? '';
$page     = max(1, (int) ($_GET['page'] ?? 1));
$limit    = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$offset   = ($page - 1) * $limit;

$where  = ['u.is_active = 1', 'u.id != :current_user_id'];
$params = [':current_user_id' => $user['id']];
$joins  = 'LEFT JOIN profiles p ON p.user_id = u.id';

if ($search !== '') {
    $where[]            = '(CONCAT(u.first_name, " ", u.last_name) LIKE :search OR p.headline LIKE :search2)';
    $params[':search']  = "%{$search}%";
    $params[':search2'] = "%{$search}%";
}

if ($role !== '') {
    $where[]          = 'u.role = :role';
    $params[':role']  = $role;
}

if ($industry !== '') {
    $where[]               = 'p.industry LIKE :industry';
    $params[':industry']   = "%{$industry}%";
}

if ($location !== '') {
    $where[]               = 'p.location LIKE :location';
    $params[':location']   = "%{$location}%";
}

if ($skillIds !== '') {
    $skillIdArray = array_filter(array_map('intval', explode(',', $skillIds)));
    if (!empty($skillIdArray)) {
        $placeholders = [];
        foreach ($skillIdArray as $i => $sid) {
            $key = ":sk_{$i}";
            $placeholders[] = $key;
            $params[$key] = $sid;
        }
        $joins .= ' INNER JOIN user_skills us_filter ON us_filter.user_id = u.id';
        $where[] = 'us_filter.skill_id IN (' . implode(',', $placeholders) . ')';
    }
}

$whereClause = implode(' AND ', $where);

// --- Count ---
$stmt = $db->prepare("SELECT COUNT(DISTINCT u.id) FROM users u {$joins} WHERE {$whereClause}");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

// --- Fetch ---
$sql = "
    SELECT DISTINCT
        u.id, u.first_name, u.last_name, u.role, u.created_at,
        p.headline, p.location, p.avatar_url, p.industry,
        p.years_of_experience
    FROM users u
    {$joins}
    WHERE {$whereClause}
    ORDER BY u.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$people = $stmt->fetchAll();

// --- Batch-fetch connection status and top skills for all results ---
$personIds = array_column($people, 'id');

// Connection statuses (single query)
$connStatusMap = [];
if (!empty($personIds)) {
    $placeholders = [];
    $connParams = [];
    foreach ($personIds as $i => $pid) {
        $placeholders[] = ":pid_{$i}";
        $connParams[":pid_{$i}"] = $pid;
    }
    $inClause = implode(',', $placeholders);
    $stmt = $db->prepare("
        SELECT
            IF(requester_id = :me1, receiver_id, requester_id) AS other_id,
            status
        FROM connections
        WHERE (requester_id = :me2 AND receiver_id IN ({$inClause}))
           OR (receiver_id = :me3 AND requester_id IN ({$inClause}))
    ");
    $connParams[':me1'] = $user['id'];
    $connParams[':me2'] = $user['id'];
    $connParams[':me3'] = $user['id'];
    $stmt->execute($connParams);
    foreach ($stmt->fetchAll() as $row) {
        $connStatusMap[(int) $row['other_id']] = $row['status'];
    }
}

// Top skills (single query, grouped by user)
$skillsMap = [];
if (!empty($personIds)) {
    $placeholders = [];
    $skillParams = [];
    foreach ($personIds as $i => $pid) {
        $placeholders[] = ":spid_{$i}";
        $skillParams[":spid_{$i}"] = $pid;
    }
    $inClause = implode(',', $placeholders);
    $stmt = $db->prepare("
        SELECT us.user_id, s.name, us.proficiency_level
        FROM user_skills us
        JOIN skills s ON s.id = us.skill_id
        WHERE us.user_id IN ({$inClause})
        ORDER BY us.endorsement_count DESC
    ");
    $stmt->execute($skillParams);
    foreach ($stmt->fetchAll() as $row) {
        $uid = (int) $row['user_id'];
        if (!isset($skillsMap[$uid])) $skillsMap[$uid] = [];
        if (count($skillsMap[$uid]) < 5) {
            $skillsMap[$uid][] = ['name' => $row['name'], 'proficiency_level' => $row['proficiency_level']];
        }
    }
}

// Mutual connections count (single query)
$mutualMap = [];
if (!empty($personIds)) {
    // Get all accepted connections for the current user
    $myConnStmt = $db->prepare('
        SELECT IF(requester_id = :me1, receiver_id, requester_id) AS friend_id
        FROM connections
        WHERE (requester_id = :me2 OR receiver_id = :me3)
        AND status = :status
    ');
    $myConnStmt->execute([':me1' => $user['id'], ':me2' => $user['id'], ':me3' => $user['id'], ':status' => 'accepted']);
    $myFriends = array_column($myConnStmt->fetchAll(), 'friend_id');

    if (!empty($myFriends)) {
        $placeholders = [];
        $mutualParams = [];
        foreach ($personIds as $i => $pid) {
            $placeholders[] = ":mpid_{$i}";
            $mutualParams[":mpid_{$i}"] = $pid;
        }
        $friendPlaceholders = [];
        foreach ($myFriends as $j => $fid) {
            $friendPlaceholders[] = ":fid_{$j}";
            $mutualParams[":fid_{$j}"] = $fid;
        }
        $inPeople = implode(',', $placeholders);
        $inFriends = implode(',', $friendPlaceholders);
        $stmt = $db->prepare("
            SELECT
                IF(requester_id IN ({$inPeople}), requester_id, receiver_id) AS person_id,
                COUNT(*) AS mutual_count
            FROM connections
            WHERE status = 'accepted'
            AND (
                (requester_id IN ({$inPeople}) AND receiver_id IN ({$inFriends}))
                OR (receiver_id IN ({$inPeople}) AND requester_id IN ({$inFriends}))
            )
            GROUP BY person_id
        ");
        $stmt->execute($mutualParams);
        foreach ($stmt->fetchAll() as $row) {
            $mutualMap[(int) $row['person_id']] = (int) $row['mutual_count'];
        }
    }
}

// Assign to each person
foreach ($people as &$person) {
    $pid = (int) $person['id'];
    $person['connection_status'] = $connStatusMap[$pid] ?? 'none';
    $person['mutual_connections'] = $mutualMap[$pid] ?? 0;
    $person['top_skills'] = $skillsMap[$pid] ?? [];
}

jsonSuccess([
    'people'     => $people,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => ceil($total / $limit),
    ],
], 'People search results retrieved.');
