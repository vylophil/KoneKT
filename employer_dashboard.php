<?php
session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employer') {
  header('Location: login.php');
  exit;
}

require_once __DIR__ . '/api/config/database.php';

$active_page = 'employer_dashboard';
$unread_messages = 0;

$db = getDB();

try {
  $stmt = $db->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = 0');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $unread_messages = (int) $stmt->fetchColumn();
} catch (Throwable $e) {}

// Get company
$company = null;
try {
  $stmt = $db->prepare('SELECT * FROM companies WHERE user_id = :uid LIMIT 1');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $company = $stmt->fetch();
} catch (Throwable $e) {}

$companyId = $company['id'] ?? 0;

// Stats
$totalJobs = 0;
$totalApps = 0;
$pendingReview = 0;
$recentJobs = [];

if ($companyId) {
  try {
    $stmt = $db->prepare('SELECT COUNT(*) FROM job_postings WHERE company_id = :cid AND is_active = 1');
    $stmt->execute([':cid' => $companyId]);
    $totalJobs = (int) $stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM job_applications ja JOIN job_postings jp ON jp.id = ja.job_id WHERE jp.company_id = :cid');
    $stmt->execute([':cid' => $companyId]);
    $totalApps = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM job_applications ja JOIN job_postings jp ON jp.id = ja.job_id WHERE jp.company_id = :cid AND ja.status = 'pending'");
    $stmt->execute([':cid' => $companyId]);
    $pendingReview = (int) $stmt->fetchColumn();

    // Recent jobs with app counts
    $stmt = $db->prepare('
      SELECT jp.id, jp.title, jp.location, jp.job_type, jp.created_at, jp.is_active,
             (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) AS app_count
      FROM job_postings jp
      WHERE jp.company_id = :cid
      ORDER BY jp.created_at DESC LIMIT 5
    ');
    $stmt->execute([':cid' => $companyId]);
    $recentJobs = $stmt->fetchAll();
  } catch (Throwable $e) {}
}

$displayName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employer Dashboard · KoneKT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>
  <?php include 'includes/navbar.php'; ?>

  <main class="container py-5">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
      <div>
        <h1 class="h3 mb-1">Welcome, <?= htmlspecialchars($displayName) ?></h1>
        <p class="text-secondary mb-0"><?= $company ? htmlspecialchars($company['name']) : 'Set up your company profile' ?></p>
      </div>
      <div class="d-flex gap-2">
        <?php if ($company): ?>
          <a href="employer_jobs.php" class="btn btn-konekt-primary px-4"><i class="bi bi-plus-lg me-1"></i> Post New Job</a>
        <?php else: ?>
          <a href="employer_company.php" class="btn btn-konekt-gold px-4"><i class="bi bi-building me-1"></i> Set Up Company</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$company): ?>
      <div class="konekt-card p-5">
        <div class="empty-state">
          <i class="bi bi-building"></i>
          <h3 class="h5 mb-2">Set up your company first</h3>
          <p class="text-secondary mb-4">You need a company profile before you can post jobs and manage applicants.</p>
          <a href="employer_company.php" class="btn btn-konekt-primary px-4">Create Company Profile</a>
        </div>
      </div>
    <?php else: ?>

    <!-- Stats Row -->
    <div class="row g-4 mb-4">
      <div class="col-md-4">
        <div class="konekt-card stat-card">
          <div class="stat-icon text-primary"><i class="bi bi-briefcase"></i></div>
          <div class="stat-number"><?= $totalJobs ?></div>
          <div class="stat-label">Active Jobs</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="konekt-card stat-card">
          <div class="stat-icon" style="color: var(--ember-gold);"><i class="bi bi-people"></i></div>
          <div class="stat-number"><?= $totalApps ?></div>
          <div class="stat-label">Total Applications</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="konekt-card stat-card">
          <div class="stat-icon text-danger"><i class="bi bi-clock-history"></i></div>
          <div class="stat-number"><?= $pendingReview ?></div>
          <div class="stat-label">Pending Review</div>
        </div>
      </div>
    </div>

    <!-- Recent Jobs -->
    <div class="konekt-card p-4 mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Your Job Postings</h2>
        <a href="employer_jobs.php" class="small">Manage all &rarr;</a>
      </div>
      <?php if (empty($recentJobs)): ?>
        <p class="text-secondary small">No jobs posted yet. <a href="employer_jobs.php">Post your first job</a>.</p>
      <?php else: ?>
        <?php foreach ($recentJobs as $job): ?>
        <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
          <div>
            <p class="fw-semibold mb-1"><?= htmlspecialchars($job['title']) ?></p>
            <p class="text-secondary small mb-0"><?= htmlspecialchars($job['location'] ?? 'Remote') ?> · <?= ucfirst(str_replace('_', ' ', $job['job_type'])) ?></p>
          </div>
          <div class="text-end">
            <span class="match-chip"><?= $job['app_count'] ?> applicant<?= $job['app_count'] != 1 ? 's' : '' ?></span>
            <a href="employer_applicants.php?job_id=<?= $job['id'] ?>" class="d-block small mt-1">View →</a>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="row g-4">
      <div class="col-md-4">
        <a href="employer_applicants.php" class="konekt-card p-4 text-decoration-none text-center d-block h-100">
          <i class="bi bi-person-lines-fill fs-2 text-primary mb-2 d-block"></i>
          <h3 class="h6">View Applicants</h3>
          <p class="text-secondary small mb-0">Search and manage applicants by role</p>
        </a>
      </div>
      <div class="col-md-4">
        <a href="employer_company.php" class="konekt-card p-4 text-decoration-none text-center d-block h-100">
          <i class="bi bi-building fs-2 mb-2 d-block" style="color: var(--ember-gold);"></i>
          <h3 class="h6">Company Profile</h3>
          <p class="text-secondary small mb-0">Edit your company information</p>
        </a>
      </div>
      <div class="col-md-4">
        <a href="network.php" class="konekt-card p-4 text-decoration-none text-center d-block h-100">
          <i class="bi bi-chat-dots fs-2 text-success mb-2 d-block"></i>
          <h3 class="h6">Messages</h3>
          <p class="text-secondary small mb-0"><?= $unread_messages ?> unread message<?= $unread_messages !== 1 ? 's' : '' ?></p>
        </a>
      </div>
    </div>
    <?php endif; ?>
  </main>

  <?php include 'includes/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
