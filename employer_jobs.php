<?php
session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employer') {
  header('Location: login.php');
  exit;
}

require_once __DIR__ . '/api/config/database.php';

$active_page = 'employer_jobs';
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

$saveMsg = '';
$saveOk = false;

// Handle POST — create new job
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? 'create';

  if ($action === 'create') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $jobType     = $_POST['job_type'] ?? 'full_time';
    $workArr     = $_POST['work_arrangement'] ?? 'on_site';
    $salaryMin   = !empty($_POST['salary_min']) ? (float)$_POST['salary_min'] : null;
    $salaryMax   = !empty($_POST['salary_max']) ? (float)$_POST['salary_max'] : null;
    $expLevel    = $_POST['experience_level'] ?? 'entry';
    $minExpYrs   = (int)($_POST['min_experience_years'] ?? 0);
    $eduReq      = $_POST['education_requirement'] ?? 'none';
    $deadline    = !empty($_POST['deadline']) ? $_POST['deadline'] : null;

    if (mb_strlen($title) < 3) {
      $saveMsg = 'Job title is required (at least 3 characters).';
    } elseif (mb_strlen($description) < 10) {
      $saveMsg = 'Job description is required (at least 10 characters).';
    } else {
      try {
        $stmt = $db->prepare('
          INSERT INTO job_postings (company_id, employer_id, title, description, requirements, location, job_type, work_arrangement, salary_min, salary_max, experience_level, min_experience_years, education_requirement, deadline)
          VALUES (:cid, :eid, :title, :desc, :req, :loc, :jt, :wa, :smin, :smax, :el, :mey, :er, :dl)
        ');
        $stmt->execute([
          ':cid' => $company['id'], ':eid' => $_SESSION['user_id'],
          ':title' => $title, ':desc' => $description, ':req' => $requirements,
          ':loc' => $location, ':jt' => $jobType, ':wa' => $workArr,
          ':smin' => $salaryMin, ':smax' => $salaryMax,
          ':el' => $expLevel, ':mey' => $minExpYrs, ':er' => $eduReq, ':dl' => $deadline,
        ]);
        $saveOk = true;
        $saveMsg = "Job \"" . htmlspecialchars($title) . "\" posted successfully!";
      } catch (Throwable $e) {
        $saveMsg = 'Failed to post job: ' . $e->getMessage();
      }
    }
  } elseif ($action === 'update') {
    $jobId       = (int)($_POST['job_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $jobType     = $_POST['job_type'] ?? 'full_time';
    $workArr     = $_POST['work_arrangement'] ?? 'on_site';

    if ($jobId < 1 || mb_strlen($title) < 3 || mb_strlen($description) < 10) {
      $saveMsg = 'Title must be at least 3 characters and description at least 10 characters.';
    } else {
      try {
        $stmt = $db->prepare('UPDATE job_postings SET title = :title, description = :description, requirements = :requirements, location = :location, job_type = :job_type, work_arrangement = :work_arrangement WHERE id = :id AND employer_id = :eid');
        $stmt->execute([
          ':title' => $title, ':description' => $description, ':requirements' => $requirements,
          ':location' => $location, ':job_type' => $jobType, ':work_arrangement' => $workArr,
          ':id' => $jobId, ':eid' => $_SESSION['user_id'],
        ]);
        $saveOk = true;
        $saveMsg = 'Job posting updated successfully.';
      } catch (Throwable $e) {
        $saveMsg = 'Failed to update job posting.';
      }
    }
  } elseif ($action === 'toggle') {
    $jobId = (int)($_POST['job_id'] ?? 0);
    $newActive = (int)($_POST['new_active'] ?? 0);
    try {
      $stmt = $db->prepare('UPDATE job_postings SET is_active = :a WHERE id = :id AND employer_id = :eid');
      $stmt->execute([':a' => $newActive, ':id' => $jobId, ':eid' => $_SESSION['user_id']]);
      $saveOk = true;
      $saveMsg = $newActive ? 'Job reactivated.' : 'Job deactivated.';
    } catch (Throwable $e) {
      $saveMsg = 'Failed to update job status.';
    }
  }
}

// Load all jobs
$jobs = [];
try {
  $stmt = $db->prepare('
    SELECT jp.*, (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) AS app_count
    FROM job_postings jp
    WHERE jp.company_id = :cid
    ORDER BY jp.created_at DESC
  ');
  $stmt->execute([':cid' => $company['id']]);
  $jobs = $stmt->fetchAll();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Jobs · KoneKT</title>
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
        <h1 class="h3 mb-1">My Job Postings</h1>
        <p class="text-secondary mb-0"><?= count($jobs) ?> total job<?= count($jobs) !== 1 ? 's' : '' ?> posted for <?= htmlspecialchars($company['name']) ?></p>
      </div>
      <button class="btn btn-konekt-primary px-4" data-bs-toggle="collapse" data-bs-target="#newJobForm">
        <i class="bi bi-plus-lg me-1"></i> Post New Job
      </button>
    </div>

    <?php if (!empty($saveMsg)): ?>
      <div class="alert <?= $saveOk ? 'alert-success' : 'alert-danger' ?> small"><?= $saveMsg ?></div>
    <?php endif; ?>

    <!-- New Job Form (collapsible) -->
    <div class="collapse mb-4" id="newJobForm">
      <div class="konekt-card p-4 p-md-5">
        <h2 class="h5 mb-4">Create New Job Posting</h2>
        <form method="post">
          <input type="hidden" name="action" value="create">
          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">Job Title *</label>
              <input type="text" name="title" class="form-control" required placeholder="e.g. IT Support Specialist">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Job Type *</label>
              <select name="job_type" class="form-select">
                <option value="full_time">Full Time</option>
                <option value="part_time">Part Time</option>
                <option value="contract">Contract</option>
                <option value="internship">Internship</option>
                <option value="freelance">Freelance</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Description *</label>
            <textarea name="description" class="form-control" rows="4" required placeholder="Describe the role, responsibilities, and what makes it a great opportunity..."></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Requirements</label>
            <textarea name="requirements" class="form-control" rows="3" placeholder="List key requirements, qualifications, or skills needed..."></textarea>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">Location</label>
              <input type="text" name="location" class="form-control" placeholder="City, Province">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Work Arrangement</label>
              <select name="work_arrangement" class="form-select">
                <option value="on_site">On-site</option>
                <option value="remote">Remote</option>
                <option value="hybrid">Hybrid</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Experience Level</label>
              <select name="experience_level" class="form-select">
                <option value="entry">Entry Level</option>
                <option value="mid">Mid Level</option>
                <option value="senior">Senior</option>
                <option value="executive">Executive</option>
              </select>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <label class="form-label fw-semibold">Salary Min (₱)</label>
              <input type="number" name="salary_min" class="form-control" min="0">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Salary Max (₱)</label>
              <input type="number" name="salary_max" class="form-control" min="0">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Min Experience (yrs)</label>
              <input type="number" name="min_experience_years" class="form-control" min="0" value="0">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Application Deadline</label>
              <input type="date" name="deadline" class="form-control">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Education Requirement</label>
            <select name="education_requirement" class="form-select" style="max-width: 300px;">
              <option value="none">None</option>
              <option value="high_school">High School</option>
              <option value="associate">Associate</option>
              <option value="bachelors" selected>Bachelor's</option>
              <option value="masters">Master's</option>
              <option value="doctorate">Doctorate</option>
            </select>
          </div>
          <button type="submit" class="btn btn-konekt-primary px-4">
            <i class="bi bi-plus-lg me-1"></i> Post Job
          </button>
        </form>
      </div>
    </div>

    <!-- Existing Jobs List -->
    <?php if (empty($jobs)): ?>
      <div class="konekt-card p-5">
        <div class="empty-state">
          <i class="bi bi-briefcase"></i>
          <h3 class="h5 mb-2">No jobs posted yet</h3>
          <p class="text-secondary">Click "Post New Job" above to create your first listing.</p>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($jobs as $job): ?>
      <div class="konekt-card p-4 mb-3">
        <div class="d-flex justify-content-between align-items-start">
          <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 mb-1">
              <h2 class="h5 mb-0"><?= htmlspecialchars($job['title']) ?></h2>
              <?php if (!$job['is_active']): ?>
                <span class="status-badge withdrawn">Inactive</span>
              <?php endif; ?>
            </div>
            <p class="text-secondary small mb-2">
              <?= htmlspecialchars($job['location'] ?? 'Remote') ?> · <?= ucfirst(str_replace('_', ' ', $job['job_type'])) ?> · <?= ucfirst(str_replace('_', ' ', $job['work_arrangement'])) ?>
              <?php if ($job['salary_min'] || $job['salary_max']): ?>
                · ₱<?= number_format($job['salary_min'] ?? 0) ?> – ₱<?= number_format($job['salary_max'] ?? 0) ?>
              <?php endif; ?>
            </p>
            <p class="text-secondary small mb-0">Posted <?= date('M j, Y', strtotime($job['created_at'])) ?></p>
          </div>
          <div class="text-end">
            <a href="employer_applicants.php?job_id=<?= $job['id'] ?>" class="match-chip text-decoration-none">
              <i class="bi bi-people"></i> <?= $job['app_count'] ?> applicant<?= $job['app_count'] != 1 ? 's' : '' ?>
            </a>
            <a href="employer_applicants.php?job_id=<?= $job['id'] ?>&view=matches" class="d-block small mt-2">
              <i class="bi bi-stars me-1"></i> See matching candidates
            </a>
            <a href="#editJob<?= $job['id'] ?>" data-bs-toggle="collapse" class="btn btn-sm btn-outline-primary mt-2">
              <i class="bi bi-pencil"></i> Edit
            </a>
            <form method="post" class="mt-2 d-inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
              <input type="hidden" name="new_active" value="<?= $job['is_active'] ? 0 : 1 ?>">
              <button type="submit" class="btn btn-sm <?= $job['is_active'] ? 'btn-outline-secondary' : 'btn-konekt-success' ?> px-3">
                <?= $job['is_active'] ? 'Deactivate' : 'Reactivate' ?>
              </button>
            </form>
          </div>
        </div>
        <div class="collapse mt-4" id="editJob<?= $job['id'] ?>">
          <hr>
          <h3 class="h6 mb-3">Edit job posting</h3>
          <form method="post" class="row g-3">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
            <div class="col-md-8">
              <label class="form-label small fw-semibold">Job Title</label>
              <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($job['title']) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Job Type</label>
              <select name="job_type" class="form-select">
                <?php foreach (['full_time' => 'Full Time', 'part_time' => 'Part Time', 'contract' => 'Contract', 'internship' => 'Internship', 'freelance' => 'Freelance'] as $value => $label): ?>
                  <option value="<?= $value ?>" <?= $job['job_type'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Description</label>
              <textarea name="description" class="form-control" rows="3" required><?= htmlspecialchars($job['description']) ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Requirements</label>
              <textarea name="requirements" class="form-control" rows="2"><?= htmlspecialchars($job['requirements'] ?? '') ?></textarea>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Location</label>
              <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($job['location'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Work Arrangement</label>
              <select name="work_arrangement" class="form-select">
                <?php foreach (['on_site' => 'On-site', 'remote' => 'Remote', 'hybrid' => 'Hybrid'] as $value => $label): ?>
                  <option value="<?= $value ?>" <?= $job['work_arrangement'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-konekt-primary"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
            </div>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <?php include 'includes/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
