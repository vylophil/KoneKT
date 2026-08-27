<?php
session_start();
$active_page = 'find_jobs';
$unread_messages = 0;
$isLoggedIn = !empty($_SESSION['user_id']);

require_once __DIR__ . '/api/config/database.php';

$db = getDB();

// Get search params
$keyword  = trim($_GET['keyword'] ?? '');
$location = trim($_GET['location'] ?? '');

// Fetch jobs from DB
$jobs = [];
$total = 0;
try {
  $where = ['jp.is_active = 1'];
  $params = [];

  if ($keyword !== '') {
    $where[] = '(jp.title LIKE :kw1 OR jp.description LIKE :kw2)';
    $params[':kw1'] = "%{$keyword}%";
    $params[':kw2'] = "%{$keyword}%";
  }
  if ($location !== '') {
    $where[] = 'jp.location LIKE :loc';
    $params[':loc'] = "%{$location}%";
  }

  $whereClause = implode(' AND ', $where);

  $stmt = $db->prepare("SELECT COUNT(*) FROM job_postings jp WHERE {$whereClause}");
  $stmt->execute($params);
  $total = (int) $stmt->fetchColumn();

  $sql = "
    SELECT jp.id, jp.title, jp.description, jp.location, jp.job_type,
           jp.work_arrangement, jp.salary_min, jp.salary_max, jp.salary_currency,
           jp.experience_level, jp.created_at,
           jp.employer_id,
           c.name AS company_name, c.industry AS company_industry,
           u.first_name AS employer_first, u.last_name AS employer_last
    FROM job_postings jp
    JOIN companies c ON c.id = jp.company_id
    JOIN users u ON u.id = jp.employer_id
    WHERE {$whereClause}
    ORDER BY jp.created_at DESC
    LIMIT 20
  ";
  $stmt = $db->prepare($sql);
  $stmt->execute($params);
  $jobs = $stmt->fetchAll();

  // Attach skills
  if (!empty($jobs)) {
    $jobIds = array_column($jobs, 'id');
    $ph = implode(',', array_fill(0, count($jobIds), '?'));
    $sk = $db->prepare("SELECT js.job_id, s.name FROM job_skills js JOIN skills s ON s.id = js.skill_id WHERE js.job_id IN ({$ph})");
    $sk->execute($jobIds);
    $skillsByJob = [];
    foreach ($sk->fetchAll() as $s) { $skillsByJob[$s['job_id']][] = $s['name']; }
    foreach ($jobs as &$j) { $j['skills'] = $skillsByJob[$j['id']] ?? []; }
  }
} catch (Throwable $e) {
  // DB unavailable
}

// Check applied jobs for logged-in user
$appliedJobs = [];
if ($isLoggedIn) {
  try {
    $stmt = $db->prepare('SELECT job_id, status FROM job_applications WHERE user_id = :uid');
    $stmt->execute([':uid' => $_SESSION['user_id']]);
    foreach ($stmt->fetchAll() as $app) { $appliedJobs[$app['job_id']] = $app['status']; }
  } catch (Throwable $e) {}

  try {
    $stmt = $db->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = 0');
    $stmt->execute([':uid' => $_SESSION['user_id']]);
    $unread_messages = (int) $stmt->fetchColumn();
  } catch (Throwable $e) {}
}

// Get match scores if user logged in
$matchScores = [];
if ($isLoggedIn && !empty($jobs)) {
  try {
    $jobIds = array_column($jobs, 'id');
    $ph = implode(',', array_fill(0, count($jobIds), '?'));
    $stmt = $db->prepare("SELECT job_id, match_score FROM job_matches WHERE user_id = ? AND job_id IN ({$ph})");
    $stmt->execute(array_merge([$_SESSION['user_id']], $jobIds));
    foreach ($stmt->fetchAll() as $m) { $matchScores[$m['job_id']] = $m['match_score']; }
  } catch (Throwable $e) {}
}

// Get connection status with each employer (for message button)
$employerConnections = [];
if ($isLoggedIn && !empty($jobs) && ($_SESSION['role'] ?? '') === 'job_seeker') {
  try {
    $employerIds = array_unique(array_column($jobs, 'employer_id'));
    foreach ($employerIds as $eid) {
      $stmt = $db->prepare('
        SELECT status FROM connections
        WHERE (requester_id = :me AND receiver_id = :them)
           OR (requester_id = :them2 AND receiver_id = :me2)
      ');
      $stmt->execute([':me' => $_SESSION['user_id'], ':them' => $eid, ':them2' => $eid, ':me2' => $_SESSION['user_id']]);
      $conn = $stmt->fetch();
      $employerConnections[$eid] = $conn ? $conn['status'] : 'none';
    }
  } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Find Jobs · KoneKT</title>

  <?php // Google Fonts ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

  <?php // Bootstrap 5 & Icons ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

  <?php // Shared Navbar ?>
  <?php if (file_exists('includes/navbar.php')) include 'includes/navbar.php'; ?>

  <main class="container py-5">

    <?php // Search Banner ?>
    <div class="konekt-card p-4 p-md-5 mb-4 text-white text-center rounded-4" style="background-color: var(--ink-navy);">
      <h1 class="h3 mb-2 text-white">Find Cross-Field Opportunities</h1>
      <p class="text-white-50 mb-4 mx-auto" style="max-width: 550px;">Search roles across industries tailored to your skills and qualifications.</p>

      <form action="find_jobs.php" method="GET" class="row g-2 justify-content-center mx-auto" style="max-width: 700px;">
        <div class="col-md-5">
          <div class="input-group">
            <span class="input-group-text bg-white border-0"><i class="bi bi-search text-secondary"></i></span>
            <input type="text" class="form-control border-0" name="keyword" placeholder="Job title, skill, or degree..." value="<?= htmlspecialchars($keyword) ?>">
          </div>
        </div>
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-text bg-white border-0"><i class="bi bi-geo-alt text-secondary"></i></span>
            <input type="text" class="form-control border-0" name="location" placeholder="Location..." value="<?= htmlspecialchars($location) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-konekt-gold w-100 fw-semibold py-2">Search Roles</button>
        </div>
      </form>
    </div>

    <?php // Results ?>
    <div class="mb-3">
      <p class="text-secondary small"><?= $total ?> job<?= $total !== 1 ? 's' : '' ?> found<?= $keyword ? ' for "' . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . '"' : '' ?></p>
    </div>

    <?php if (empty($jobs)): ?>
      <div class="konekt-card p-5">
        <div class="empty-state">
          <i class="bi bi-briefcase"></i>
          <h3 class="h5 mb-2">No jobs found</h3>
          <p class="text-secondary">Try a different search term or clear your filters.</p>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($jobs as $job): ?>
      <div class="konekt-card p-4 mb-3">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <h2 class="h5 mb-1"><?= htmlspecialchars($job['title']) ?></h2>
            <p class="text-secondary small mb-0"><?= htmlspecialchars($job['company_name']) ?> &middot; <?= htmlspecialchars($job['location'] ?? 'Remote') ?></p>
          </div>
          <?php if (isset($matchScores[$job['id']])): ?>
            <span class="match-chip"><i class="bi bi-stars"></i> <?= round($matchScores[$job['id']]) ?>% match</span>
          <?php endif; ?>
        </div>
        <p class="small text-secondary mb-3">
          <?= htmlspecialchars(mb_strimwidth($job['description'], 0, 200, '...')) ?>
        </p>
        <div class="d-flex justify-content-between align-items-center">
          <div class="d-flex gap-2 flex-wrap">
            <?php foreach (array_slice($job['skills'], 0, 3) as $skill): ?>
              <span class="badge rounded-pill text-bg-light border"><?= htmlspecialchars($skill) ?></span>
            <?php endforeach; ?>
            <span class="badge rounded-pill text-bg-light border"><?= ucfirst(str_replace('_', ' ', $job['job_type'])) ?></span>
          </div>
          <div class="d-flex gap-2 align-items-center">
          <?php if (!$isLoggedIn): ?>
            <a href="login.php" class="btn btn-konekt-primary btn-sm px-3">Sign In to Apply</a>
          <?php elseif (($_SESSION['role'] ?? '') === 'job_seeker'): ?>
            <?php
              $connStatus = $employerConnections[$job['employer_id']] ?? 'none';
              $empName = htmlspecialchars($job['employer_first'] . ' ' . $job['employer_last']);
            ?>
            <?php if ($connStatus === 'accepted'): ?>
              <a href="network.php?user_id=<?= $job['employer_id'] ?>" class="btn btn-konekt-outline btn-sm px-3" title="Message <?= $empName ?>">
                <i class="bi bi-chat-dots me-1"></i> Message
              </a>
            <?php elseif ($connStatus === 'pending'): ?>
              <button class="btn btn-outline-secondary btn-sm px-3" disabled title="Connection request pending">
                <i class="bi bi-clock me-1"></i> Pending
              </button>
            <?php elseif ($connStatus === 'none'): ?>
              <button class="btn btn-konekt-outline btn-sm px-3 connect-employer-btn"
                      data-employer-id="<?= $job['employer_id'] ?>"
                      data-employer-name="<?= $empName ?>"
                      data-company-name="<?= htmlspecialchars($job['company_name']) ?>"
                      data-job-title="<?= htmlspecialchars($job['title']) ?>"
                      title="Connect with <?= $empName ?>">
                <i class="bi bi-person-plus me-1"></i> Connect
              </button>
            <?php endif; ?>
            <?php if (isset($appliedJobs[$job['id']])): ?>
              <span class="status-badge <?= $appliedJobs[$job['id']] ?>"><i class="bi bi-check-circle"></i> <?= ucfirst($appliedJobs[$job['id']]) ?></span>
            <?php else: ?>
              <button class="btn btn-konekt-primary btn-sm px-3 apply-btn" data-job-id="<?= $job['id'] ?>">
                <i class="bi bi-send me-1"></i> Apply
              </button>
            <?php endif; ?>
          <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <?php // Shared Footer ?>
  <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>

  <?php // Connect with Employer Modal ?>
  <div class="modal fade" id="connectEmployerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" style="font-family: var(--font-display);">
            <i class="bi bi-person-plus text-primary me-2"></i>Connect with <span id="connectModalName"></span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-secondary small mb-3">Send a connection request to start a conversation about <strong id="connectModalJob"></strong> at <strong id="connectModalCompany"></strong>.</p>
          <textarea id="connectMessage" class="form-control" rows="3" placeholder="Hi! I'm interested in the role you posted and would love to connect..."
                    style="border-radius: 10px; resize: none;"></textarea>
          <input type="hidden" id="connectEmployerId">
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-konekt-primary btn-sm px-4" id="sendConnectBtn">
            <i class="bi bi-send me-1"></i> Send Request
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Apply button handler
    document.querySelectorAll('.apply-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const jobId = btn.dataset.jobId;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Applying...';
        try {
          const res = await fetch('api/jobs/apply_job.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ job_id: parseInt(jobId) })
          });
          const data = await res.json();
          if (data.success) {
            btn.outerHTML = '<span class="status-badge pending"><i class="bi bi-check-circle"></i> Applied</span>';
          } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-1"></i> Apply';
            alert(data.message || 'Failed to apply.');
          }
        } catch (err) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-send me-1"></i> Apply';
          alert('Network error.');
        }
      });
    });

    // Connect with employer button handler
    document.querySelectorAll('.connect-employer-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('connectModalName').textContent = btn.dataset.employerName;
        document.getElementById('connectModalJob').textContent = btn.dataset.jobTitle;
        document.getElementById('connectModalCompany').textContent = btn.dataset.companyName;
        document.getElementById('connectEmployerId').value = btn.dataset.employerId;
        document.getElementById('connectMessage').value = `Hi ${btn.dataset.employerName}! I came across the ${btn.dataset.jobTitle} role at ${btn.dataset.companyName} and I'm very interested. I'd love to connect and learn more about the opportunity.`;
        const modal = new bootstrap.Modal(document.getElementById('connectEmployerModal'));
        modal.show();
        // Store reference to the button that triggered the modal
        document.getElementById('sendConnectBtn').dataset.triggerBtn = Array.from(document.querySelectorAll('.connect-employer-btn')).indexOf(btn);
      });
    });

    // Send connection request
    document.getElementById('sendConnectBtn')?.addEventListener('click', async function() {
      const sendBtn = this;
      const employerId = document.getElementById('connectEmployerId').value;
      const message = document.getElementById('connectMessage').value.trim();

      sendBtn.disabled = true;
      sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';

      try {
        const res = await fetch('api/networking/send_connection.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ receiver_id: parseInt(employerId), message: message })
        });
        const data = await res.json();
        if (data.success) {
          // Update the triggering button
          const btnIndex = parseInt(sendBtn.dataset.triggerBtn);
          const allBtns = document.querySelectorAll('.connect-employer-btn');
          if (allBtns[btnIndex]) {
            allBtns[btnIndex].outerHTML = '<button class="btn btn-outline-secondary btn-sm px-3" disabled><i class="bi bi-clock me-1"></i> Pending</button>';
          }
          bootstrap.Modal.getInstance(document.getElementById('connectEmployerModal')).hide();
        } else {
          alert(data.message || 'Failed to send request.');
        }
      } catch (err) {
        alert('Network error.');
      } finally {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="bi bi-send me-1"></i> Send Request';
      }
    });
  </script>
</body>
</html>