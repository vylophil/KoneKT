<?php
session_start();
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
if (($_SESSION['role'] ?? '') === 'employer') {
  header('Location: employer_dashboard.php');
  exit;
}

require_once __DIR__ . '/api/config/database.php';

$active_page = 'upload';
$unread_messages = 0;
$saved = isset($_GET['saved']);

$db = getDB();

// Fetch real matches from the job_matches table
$matches = [];
$totalJobs = 0;
try {
  $stmt = $db->prepare('
    SELECT COUNT(*) FROM job_matches jm
    JOIN job_postings jp ON jp.id = jm.job_id AND jp.is_active = 1
    WHERE jm.user_id = :uid AND jm.match_score > 0
  ');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $totalJobs = (int) $stmt->fetchColumn();

  $stmt = $db->prepare('
    SELECT
      jm.match_score, jm.skill_score, jm.experience_score, jm.education_score,
      jp.id AS job_id, jp.title, jp.description, jp.location, jp.job_type,
      jp.work_arrangement, jp.salary_min, jp.salary_max, jp.salary_currency,
      c.name AS company_name, c.industry AS company_industry
    FROM job_matches jm
    JOIN job_postings jp ON jp.id = jm.job_id AND jp.is_active = 1
    JOIN companies c ON c.id = jp.company_id
    WHERE jm.user_id = :uid AND jm.match_score > 0
    ORDER BY jm.match_score DESC
    LIMIT 20
  ');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $matches = $stmt->fetchAll();

  // Attach skills to each match
  if (!empty($matches)) {
    $jobIds = array_column($matches, 'job_id');
    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
    $skillStmt = $db->prepare("SELECT js.job_id, s.name FROM job_skills js JOIN skills s ON s.id = js.skill_id WHERE js.job_id IN ({$placeholders})");
    $skillStmt->execute($jobIds);
    $skillsByJob = [];
    foreach ($skillStmt->fetchAll() as $sk) { $skillsByJob[$sk['job_id']][] = $sk['name']; }
    foreach ($matches as &$m) { $m['skills'] = $skillsByJob[$m['job_id']] ?? []; }
  }
} catch (Throwable $e) {
  // DB unavailable — matches will be empty
}

// Check if user already applied to any of these jobs
$appliedJobs = [];
try {
  $stmt = $db->prepare('SELECT job_id, status FROM job_applications WHERE user_id = :uid');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  foreach ($stmt->fetchAll() as $app) { $appliedJobs[$app['job_id']] = $app['status']; }
} catch (Throwable $e) {}

// Get user's profile industries
$userIndustry = '';
try {
  $stmt = $db->prepare('SELECT industry FROM profiles WHERE user_id = :uid');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $userIndustry = $stmt->fetchColumn() ?: '';
} catch (Throwable $e) {}
$activeFilters = array_filter(array_map('trim', explode(',', $userIndustry)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Matches · KoneKT</title>

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

    <?php if ($saved): ?>
      <div class="alert alert-success small mb-4"><i class="bi bi-check-circle me-1"></i> Preferences saved and matches recomputed!</div>
    <?php endif; ?>

    <?php // Page Header ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
      <div>
        <h1 class="h3 mb-1">Your Cross-Field Job Matches</h1>
        <p class="text-secondary mb-0">Matched against <strong><?= $totalJobs ?> open roles</strong> based on your profile and preferences.</p>
      </div>
      <a href="job_preferences.php" class="btn btn-konekt-outline btn-sm">
        <i class="bi bi-sliders me-1"></i> Edit Preferences
      </a>
    </div>

    <?php // Active Field Filter Chips ?>
    <?php if (!empty($activeFilters)): ?>
    <div class="mb-4 d-flex align-items-center gap-2 flex-wrap">
      <span class="text-secondary small fw-semibold me-2">Active Filters:</span>
      <?php foreach ($activeFilters as $filter): ?>
        <span class="badge bg-white text-dark border px-3 py-2 rounded-pill"><i class="bi bi-check2 me-1 text-success"></i> <?= htmlspecialchars($filter) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($matches)): ?>
      <?php // Empty state ?>
      <div class="konekt-card p-5 text-center">
        <div class="empty-state">
          <i class="bi bi-stars"></i>
          <h3 class="h5 mb-2">No matches yet</h3>
          <p class="text-secondary mb-4">Upload your resume and set your preferences to start receiving cross-field job matches.</p>
          <a href="upload_resume.php" class="btn btn-konekt-primary px-4">Upload Resume</a>
        </div>
      </div>
    <?php else: ?>
    <div class="row g-4">

      <?php // Match List ?>
      <div class="col-lg-8">
        <?php foreach ($matches as $match): ?>
        <div class="konekt-card p-4 mb-3">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <h2 class="h5 mb-1"><?= htmlspecialchars($match['title']) ?></h2>
              <p class="text-secondary small mb-0"><?= htmlspecialchars($match['company_name']) ?> &middot; <?= htmlspecialchars($match['location'] ?? 'Remote') ?></p>
            </div>
            <span class="match-chip"><i class="bi bi-stars"></i> <?= round($match['match_score']) ?>% match</span>
          </div>
          <p class="small text-secondary mb-3">
            <?= htmlspecialchars(mb_strimwidth($match['description'], 0, 180, '...')) ?>
          </p>
          <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex gap-2 flex-wrap">
              <?php foreach (array_slice($match['skills'], 0, 3) as $skill): ?>
                <span class="badge rounded-pill text-bg-light border"><?= htmlspecialchars($skill) ?></span>
              <?php endforeach; ?>
              <?php if (!empty($match['company_industry'])): ?>
                <span class="badge rounded-pill text-bg-light border"><?= htmlspecialchars($match['company_industry']) ?></span>
              <?php endif; ?>
            </div>
            <?php if (isset($appliedJobs[$match['job_id']])): ?>
              <span class="status-badge <?= $appliedJobs[$match['job_id']] ?>"><i class="bi bi-check-circle"></i> <?= ucfirst($appliedJobs[$match['job_id']]) ?></span>
            <?php else: ?>
              <button class="btn btn-konekt-primary btn-sm px-3 apply-btn" data-job-id="<?= $match['job_id'] ?>">
                <i class="bi bi-send me-1"></i> Apply
              </button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php // Sidebar summary ?>
      <div class="col-lg-4">
        <div class="konekt-card p-4 mb-4">
          <h3 class="h6 mb-3"><i class="bi bi-lightbulb text-warning me-2"></i>Match Insights</h3>
          <p class="small text-secondary mb-2">
            <strong>Skill Score:</strong> How well your skills match job requirements
          </p>
          <p class="small text-secondary mb-2">
            <strong>Experience Score:</strong> Years of relevant work experience
          </p>
          <p class="small text-secondary mb-3">
            <strong>Education Score:</strong> Degree level vs. job requirements
          </p>
          <hr>
          <a href="upload_resume.php" class="btn btn-konekt-gold btn-sm w-100">Update Resume to Refresh</a>
        </div>

        <div class="konekt-card p-4">
          <h3 class="h6 mb-3">Quick Links</h3>
          <a href="my_applications.php" class="d-block small mb-2"><i class="bi bi-file-earmark-text me-1"></i> My Applications</a>
          <a href="find_jobs.php" class="d-block small mb-2"><i class="bi bi-search me-1"></i> Browse All Jobs</a>
          <a href="network.php" class="d-block small"><i class="bi bi-chat-dots me-1"></i> Network & Messages</a>
        </div>
      </div>

    </div>
    <?php endif; ?>
  </main>

  <?php // Shared Footer ?>
  <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>

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
          alert('Network error. Please try again.');
        }
      });
    });
  </script>
</body>
</html>