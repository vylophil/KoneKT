<?php
session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employer') {
  header('Location: login.php');
  exit;
}

require_once __DIR__ . '/api/config/database.php';

$active_page = 'employer_applicants';
$unread_messages = 0;
$db = getDB();

// Get company
$company = null;
try {
  $stmt = $db->prepare('SELECT * FROM companies WHERE user_id = :uid LIMIT 1');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $company = $stmt->fetch();
} catch (Throwable $e) {}

if (!$company) {
  header('Location: employer_company.php');
  exit;
}

$companyId = $company['id'];

// Get all job postings for this company (for the dropdown)
$allJobs = [];
try {
  $stmt = $db->prepare('
    SELECT jp.id, jp.title, jp.is_active,
           (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) AS app_count
    FROM job_postings jp
    WHERE jp.company_id = :cid
    ORDER BY jp.created_at DESC
  ');
  $stmt->execute([':cid' => $companyId]);
  $allJobs = $stmt->fetchAll();
} catch (Throwable $e) {}

// Filters
$selectedJobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
$searchQuery   = trim($_GET['search'] ?? '');
$statusFilter  = trim($_GET['status'] ?? '');
$viewMode      = ($_GET['view'] ?? 'applications') === 'matches' ? 'matches' : 'applications';

// Build applicant query
$applicants = [];
$selectedJobTitle = '';

if ($selectedJobId || $searchQuery) {
  try {
    $where  = ['jp.company_id = :cid'];
    $params = [':cid' => $companyId];

    if ($selectedJobId) {
      $where[] = 'ja.job_id = :jid';
      $params[':jid'] = $selectedJobId;

      // Get job title
      foreach ($allJobs as $j) {
        if ($j['id'] == $selectedJobId) {
          $selectedJobTitle = $j['title'];
          break;
        }
      }

      // A match is useful before a candidate applies, so keep this separate from applications.
      if ($viewMode === 'matches' && $selectedJobId) {
        try {
          $stmt = $db->prepare('SELECT jp.title FROM job_postings jp WHERE jp.id = :jid AND jp.company_id = :cid');
          $stmt->execute([':jid' => $selectedJobId, ':cid' => $companyId]);
          $selectedJobTitle = (string) $stmt->fetchColumn();
          if ($selectedJobTitle === '') {
            $selectedJobId = 0;
          } else {
            $stmt = $db->prepare('
              SELECT NULL AS app_id, ja.status, ja.cover_letter, ja.resume_url, ja.applied_at,
                     jm.job_id, jp.title AS job_title, jm.match_score,
                     u.id AS user_id, u.first_name, u.last_name, u.email,
                     p.headline, p.location AS applicant_location, p.years_of_experience
              FROM job_matches jm
              JOIN job_postings jp ON jp.id = jm.job_id AND jp.company_id = :cid
              JOIN users u ON u.id = jm.user_id AND u.is_active = 1
              LEFT JOIN profiles p ON p.user_id = u.id
              LEFT JOIN job_applications ja ON ja.job_id = jm.job_id AND ja.user_id = jm.user_id
              WHERE jm.job_id = :jid
              ORDER BY jm.match_score DESC
              LIMIT 50
            ');
            $stmt->execute([':cid' => $companyId, ':jid' => $selectedJobId]);
            $applicants = $stmt->fetchAll();
          }
        } catch (Throwable $e) {}
      }
    }

    if ($searchQuery !== '') {
      $where[] = '(jp.title LIKE :sq1 OR u.first_name LIKE :sq2 OR u.last_name LIKE :sq3 OR u.email LIKE :sq4)';
      $params[':sq1'] = "%{$searchQuery}%";
      $params[':sq2'] = "%{$searchQuery}%";
      $params[':sq3'] = "%{$searchQuery}%";
      $params[':sq4'] = "%{$searchQuery}%";
    }

    if ($statusFilter !== '') {
      $where[] = 'ja.status = :sf';
      $params[':sf'] = $statusFilter;
    }

    $whereClause = implode(' AND ', $where);

    $sql = "
      SELECT ja.id AS app_id, ja.status, ja.cover_letter, ja.resume_url, ja.applied_at,
             ja.job_id, jp.title AS job_title,
             u.id AS user_id, u.first_name, u.last_name, u.email,
             p.headline, p.location AS applicant_location, p.years_of_experience,
             (SELECT match_score FROM job_matches WHERE user_id = u.id AND job_id = ja.job_id LIMIT 1) AS match_score
      FROM job_applications ja
      JOIN job_postings jp ON jp.id = ja.job_id
      JOIN users u ON u.id = ja.user_id
      LEFT JOIN profiles p ON p.user_id = u.id
      WHERE {$whereClause}
      ORDER BY ja.applied_at DESC
      LIMIT 50
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $applicants = $stmt->fetchAll();
  } catch (Throwable $e) {}
} elseif (empty($selectedJobId) && empty($searchQuery)) {
  // Show all applicants across all jobs
  try {
    $stmt = $db->prepare("
      SELECT ja.id AS app_id, ja.status, ja.cover_letter, ja.resume_url, ja.applied_at,
             ja.job_id, jp.title AS job_title,
             u.id AS user_id, u.first_name, u.last_name, u.email,
             p.headline, p.location AS applicant_location, p.years_of_experience,
             (SELECT match_score FROM job_matches WHERE user_id = u.id AND job_id = ja.job_id LIMIT 1) AS match_score
      FROM job_applications ja
      JOIN job_postings jp ON jp.id = ja.job_id
      JOIN users u ON u.id = ja.user_id
      LEFT JOIN profiles p ON p.user_id = u.id
      WHERE jp.company_id = :cid
      " . ($statusFilter ? "AND ja.status = :sf" : "") . "
      ORDER BY ja.applied_at DESC
      LIMIT 50
    ");
    $params = [':cid' => $companyId];
    if ($statusFilter) $params[':sf'] = $statusFilter;
    $stmt->execute($params);
    $applicants = $stmt->fetchAll();
  } catch (Throwable $e) {}
}

function getInitials($first, $last) {
  return strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Applicants · KoneKT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>
  <?php include 'includes/navbar.php'; ?>

  <main class="container py-5">

    <div class="mb-4">
      <h1 class="h3 mb-1">Applicant Management</h1>
      <p class="text-secondary mb-0">Review applications and discover strong matches for <?= htmlspecialchars($company['name']) ?>.</p>
    </div>

    <?php if ($selectedJobId): ?>
      <div class="d-flex gap-2 mb-4">
        <a href="employer_applicants.php?job_id=<?= $selectedJobId ?>" class="btn <?= $viewMode === 'applications' ? 'btn-konekt-primary' : 'btn-outline-primary' ?>">
          <i class="bi bi-inbox me-1"></i> Applications
        </a>
        <a href="employer_applicants.php?job_id=<?= $selectedJobId ?>&view=matches" class="btn <?= $viewMode === 'matches' ? 'btn-konekt-primary' : 'btn-outline-primary' ?>">
          <i class="bi bi-stars me-1"></i> Matching Candidates
        </a>
        <?php if ($viewMode === 'matches'): ?>
          <button type="button" class="btn btn-outline-secondary" id="refreshMatches" data-job-id="<?= $selectedJobId ?>">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh Scores
          </button>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php // Search & Filter Bar ?>
    <div class="konekt-card p-4 mb-4">
      <form method="GET" class="row g-3 align-items-end">
        <?php // Role search ?>
        <div class="col-md-4">
          <label class="form-label fw-semibold small">Search by role or applicant</label>
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search text-secondary"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Type a role name, e.g. IT Support..." value="<?= htmlspecialchars($searchQuery) ?>" id="roleSearch">
          </div>
        </div>

        <?php // Job dropdown ?>
        <div class="col-md-3">
          <label class="form-label fw-semibold small">Filter by Job Posting</label>
          <select name="job_id" class="form-select" id="jobFilter">
            <option value="0">All postings</option>
            <?php foreach ($allJobs as $j): ?>
              <option value="<?= $j['id'] ?>" <?= $selectedJobId == $j['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($j['title']) ?> (<?= $j['app_count'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php // Status filter ?>
        <div class="col-md-3">
          <label class="form-label fw-semibold small">Status</label>
          <select name="status" class="form-select">
            <option value="">All statuses</option>
            <?php foreach (['pending', 'reviewing', 'shortlisted', 'interview', 'offered', 'accepted', 'rejected', 'withdrawn'] as $s): ?>
              <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2">
          <button type="submit" class="btn btn-konekt-primary w-100">
            <i class="bi bi-funnel me-1"></i> Filter
          </button>
        </div>
      </form>
    </div>

    <?php // Results Header ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <p class="text-secondary small mb-0">
        <strong><?= count($applicants) ?></strong> applicant<?= count($applicants) !== 1 ? 's' : '' ?>
        <?= $selectedJobTitle ? ' for "' . htmlspecialchars($selectedJobTitle) . '"' : '' ?>
        <?= $viewMode === 'matches' ? ' ranked by match score' : '' ?>
        <?= $searchQuery ? ' matching "' . htmlspecialchars($searchQuery) . '"' : '' ?>
      </p>
    </div>

    <?php // Applicants List ?>
    <?php if (empty($applicants)): ?>
      <div class="konekt-card p-5">
        <div class="empty-state">
          <i class="bi bi-person-lines-fill"></i>
          <h3 class="h5 mb-2">No applicants found</h3>
          <p class="text-secondary mb-0">
            <?php if ($searchQuery || $selectedJobId): ?>
              Try adjusting your search or filters.
            <?php else: ?>
              Applicants will appear here when people apply to your job postings.
            <?php endif; ?>
          </p>
        </div>
      </div>
    <?php else: ?>
      <div class="konekt-card overflow-hidden">
        <?php foreach ($applicants as $app): ?>
        <div class="applicant-row d-flex align-items-start gap-3" id="app-<?= $app['app_id'] ?>">
          <?php // Avatar ?>
          <div class="applicant-avatar">
            <?= getInitials($app['first_name'], $app['last_name']) ?>
          </div>

          <?php // Info ?>
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-start mb-1">
              <div>
                <h3 class="h6 mb-0"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></h3>
                <p class="text-secondary small mb-0"><?= htmlspecialchars($app['headline'] ?? $app['email']) ?></p>
              </div>
              <div class="d-flex align-items-center gap-2">
                <?php if ($app['match_score']): ?>
                  <span class="match-chip"><i class="bi bi-stars"></i> <?= round($app['match_score']) ?>%</span>
                <?php endif; ?>
                <?php if ($app['status']): ?>
                  <span class="status-badge <?= $app['status'] ?>" id="status-<?= $app['app_id'] ?>"><?= ucfirst($app['status']) ?></span>
                <?php else: ?>
                  <span class="status-badge reviewing">Matched</span>
                <?php endif; ?>
              </div>
            </div>

            <p class="small text-secondary mb-2">
              Applied for <strong><?= htmlspecialchars($app['job_title']) ?></strong>
              · <?= htmlspecialchars($app['applicant_location'] ?? 'N/A') ?>
              <?php if ($app['years_of_experience']): ?>
                · <?= $app['years_of_experience'] ?> yr<?= $app['years_of_experience'] != 1 ? 's' : '' ?> exp
              <?php endif; ?>
              <?php if ($app['applied_at']): ?> · <?= date('M j, Y', strtotime($app['applied_at'])) ?><?php else: ?> · Has not applied<?php endif; ?>
            </p>

            <?php // Resume link ?>
            <?php if ($app['resume_url']): ?>
              <a href="<?= htmlspecialchars($app['resume_url']) ?>" target="_blank" class="small me-3">
                <i class="bi bi-file-earmark-pdf me-1"></i> View Resume
              </a>
            <?php endif; ?>

            <?php // Action Buttons ?>
            <div class="action-btn-group mt-2" id="actions-<?= $app['app_id'] ?>">
              <?php if (!$app['status']): ?>
                <a href="network.php?user_id=<?= $app['user_id'] ?>" class="btn btn-outline-primary"><i class="bi bi-person me-1"></i> Contact Candidate</a>
              <?php elseif (in_array($app['status'], ['pending', 'reviewing'])): ?>
                <button class="btn btn-outline-primary status-btn" data-id="<?= $app['app_id'] ?>" data-status="shortlisted">
                  <i class="bi bi-bookmark-check"></i> Shortlist
                </button>
                <button class="btn btn-outline-info status-btn" data-id="<?= $app['app_id'] ?>" data-status="interview">
                  <i class="bi bi-camera-video"></i> Interview
                </button>
                <button class="btn btn-konekt-danger status-btn" data-id="<?= $app['app_id'] ?>" data-status="rejected">
                  <i class="bi bi-x-circle"></i> Reject
                </button>
              <?php elseif ($app['status'] === 'shortlisted'): ?>
                <button class="btn btn-outline-info status-btn" data-id="<?= $app['app_id'] ?>" data-status="interview">
                  <i class="bi bi-camera-video"></i> Interview
                </button>
                <button class="btn btn-konekt-success status-btn" data-id="<?= $app['app_id'] ?>" data-status="offered">
                  <i class="bi bi-trophy"></i> Offer
                </button>
                <button class="btn btn-konekt-danger status-btn" data-id="<?= $app['app_id'] ?>" data-status="rejected">
                  <i class="bi bi-x-circle"></i> Reject
                </button>
              <?php elseif ($app['status'] === 'interview'): ?>
                <button class="btn btn-konekt-success status-btn" data-id="<?= $app['app_id'] ?>" data-status="offered">
                  <i class="bi bi-trophy"></i> Offer
                </button>
                <button class="btn btn-konekt-danger status-btn" data-id="<?= $app['app_id'] ?>" data-status="rejected">
                  <i class="bi bi-x-circle"></i> Reject
                </button>
              <?php elseif ($app['status'] === 'offered'): ?>
                <span class="small text-secondary"><i class="bi bi-hourglass-split"></i> Waiting for applicant response</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <?php include 'includes/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const refreshMatches = document.getElementById('refreshMatches');
    if (refreshMatches) {
      refreshMatches.addEventListener('click', async () => {
        refreshMatches.disabled = true;
        refreshMatches.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Refreshing';
        try {
          const response = await fetch('api/matchmaking/compute_matches.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ job_id: Number(refreshMatches.dataset.jobId) })
          });
          const result = await response.json();
          if (!result.success) throw new Error(result.message || 'Unable to refresh scores.');
          window.location.reload();
        } catch (error) {
          alert(error.message);
          refreshMatches.disabled = false;
          refreshMatches.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Refresh Scores';
        }
      });
    }

    // Status update via AJAX
    function handleStatusClick(btn) {
      btn.addEventListener('click', async () => {
        const appId = btn.dataset.id;
        const newStatus = btn.dataset.status;
        const label = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);

        if (!confirm(`Set this applicant to "${label}"?`)) return;

        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
          const res = await fetch('api/jobs/update_application.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ application_id: parseInt(appId), status: newStatus })
          });
          const data = await res.json();

          if (data.success) {
            // Update status badge
            const badge = document.getElementById('status-' + appId);
            badge.className = 'status-badge ' + newStatus;
            badge.textContent = label;

            // Update action buttons
            const actionsDiv = document.getElementById('actions-' + appId);
            if (newStatus === 'rejected') {
              actionsDiv.innerHTML = '<span class="small text-danger"><i class="bi bi-x-circle me-1"></i> Rejected</span>';
            } else if (newStatus === 'offered') {
              actionsDiv.innerHTML = '<span class="small text-secondary"><i class="bi bi-hourglass-split me-1"></i> Waiting for applicant response</span>';
            } else if (newStatus === 'shortlisted') {
              actionsDiv.innerHTML = `
                <button class="btn btn-outline-info status-btn" data-id="${appId}" data-status="interview"><i class="bi bi-camera-video"></i> Interview</button>
                <button class="btn btn-konekt-success status-btn" data-id="${appId}" data-status="offered"><i class="bi bi-trophy"></i> Offer</button>
                <button class="btn btn-konekt-danger status-btn" data-id="${appId}" data-status="rejected"><i class="bi bi-x-circle"></i> Reject</button>
              `;
            } else if (newStatus === 'interview') {
              actionsDiv.innerHTML = `
                <button class="btn btn-konekt-success status-btn" data-id="${appId}" data-status="offered"><i class="bi bi-trophy"></i> Offer</button>
                <button class="btn btn-konekt-danger status-btn" data-id="${appId}" data-status="rejected"><i class="bi bi-x-circle"></i> Reject</button>
              `;
            }
            // Re-bind new buttons
            actionsDiv.querySelectorAll('.status-btn').forEach(newBtn => {
              handleStatusClick(newBtn);
            });
          } else {
            alert(data.message || 'Action failed.');
            btn.disabled = false;
            btn.innerHTML = originalText;
          }
        } catch (err) {
          alert('Network error. Please try again.');
          btn.disabled = false;
          btn.innerHTML = originalText;
        }
      });
    }

    document.querySelectorAll('.status-btn').forEach(btn => {
      handleStatusClick(btn);
    });
  </script>
</body>
</html>
