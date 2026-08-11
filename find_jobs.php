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
           c.name AS company_name, c.industry AS company_industry
    FROM job_postings jp
    JOIN companies c ON c.id = jp.company_id
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Find Jobs · KoneKT</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

  <!-- Bootstrap 5 & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>

  <!-- Shared Navbar -->
  <?php if (file_exists('includes/navbar.php')) include 'includes/navbar.php'; ?>

  <main class="container py-5">

    <!-- Search Banner -->
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

    <!-- Results -->
    <div class="mb-3">
      <p class="text-secondary small"><?= $total ?> job<?= $total !== 1 ? 's' : '' ?> found<?= $keyword ? " for \"{$keyword}\"" : '' ?></p>
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
          <?php if (!$isLoggedIn): ?>
            <a href="login.php" class="btn btn-konekt-primary btn-sm px-3">Sign In to Apply</a>
          <?php elseif (isset($appliedJobs[$job['id']])): ?>
            <span class="status-badge <?= $appliedJobs[$job['id']] ?>"><i class="bi bi-check-circle"></i> <?= ucfirst($appliedJobs[$job['id']]) ?></span>
          <?php elseif (($_SESSION['role'] ?? '') === 'job_seeker'): ?>
            <button class="btn btn-konekt-primary btn-sm px-3 apply-btn" data-job-id="<?= $job['id'] ?>">
              <i class="bi bi-send me-1"></i> Apply
            </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <!-- Shared Footer -->
  <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
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
  </script>
</body>
</html>