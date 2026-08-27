<?php
session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'employer') {
  header('Location: login.php');
  exit;
}

require_once __DIR__ . '/api/config/database.php';

$active_page = 'employer_dashboard';
$unread_messages = 0;
$saveMsg = '';
$saveOk = false;

$db = getDB();

// Load company data
$company = null;
try {
  $stmt = $db->prepare('SELECT * FROM companies WHERE user_id = :uid LIMIT 1');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $company = $stmt->fetch();
} catch (Throwable $e) {}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name         = trim($_POST['name'] ?? '');
  $description  = trim($_POST['description'] ?? '');
  $industry     = trim($_POST['industry'] ?? '');
  $website      = trim($_POST['website'] ?? '');
  $location     = trim($_POST['location'] ?? '');
  $companySize  = trim($_POST['company_size'] ?? '');
  $foundedYear  = !empty($_POST['founded_year']) ? (int) $_POST['founded_year'] : null;

  if (mb_strlen($name) < 2) {
    $saveMsg = 'Company name is required (at least 2 characters).';
  } else {
    try {
      if ($company) {
        $stmt = $db->prepare('UPDATE companies SET name = :name, description = :desc, industry = :ind, website = :web, location = :loc, company_size = :sz, founded_year = :yr WHERE id = :id');
        $stmt->execute([':name' => $name, ':desc' => $description, ':ind' => $industry, ':web' => $website, ':loc' => $location, ':sz' => $companySize, ':yr' => $foundedYear, ':id' => $company['id']]);
      } else {
        $stmt = $db->prepare('INSERT INTO companies (user_id, name, description, industry, website, location, company_size, founded_year) VALUES (:uid, :name, :desc, :ind, :web, :loc, :sz, :yr)');
        $stmt->execute([':uid' => $_SESSION['user_id'], ':name' => $name, ':desc' => $description, ':ind' => $industry, ':web' => $website, ':loc' => $location, ':sz' => $companySize, ':yr' => $foundedYear]);
      }
      $saveOk = true;
      $saveMsg = 'Company profile saved successfully!';

      // Reload
      $stmt = $db->prepare('SELECT * FROM companies WHERE user_id = :uid LIMIT 1');
      $stmt->execute([':uid' => $_SESSION['user_id']]);
      $company = $stmt->fetch();
    } catch (Throwable $e) {
      $saveMsg = 'Failed to save: ' . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Company Profile · KoneKT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>
  <?php include 'includes/navbar.php'; ?>

  <main class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-8">

        <h1 class="h3 mb-2">Company Profile</h1>
        <p class="text-secondary mb-4">Set up your company information. This will be shown to job seekers on your postings.</p>

        <?php if (!empty($saveMsg)): ?>
          <div class="alert <?= $saveOk ? 'alert-success' : 'alert-danger' ?> small"><?= htmlspecialchars($saveMsg) ?></div>
        <?php endif; ?>

        <div class="konekt-card p-4 p-md-5">
          <form method="post">
            <div class="mb-3">
              <label class="form-label fw-semibold">Company Name *</label>
              <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($company['name'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Description</label>
              <textarea name="description" class="form-control" rows="4" placeholder="Tell applicants about your company..."><?= htmlspecialchars($company['description'] ?? '') ?></textarea>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Industry</label>
                <input type="text" name="industry" class="form-control" placeholder="e.g. Healthcare, IT, Finance" value="<?= htmlspecialchars($company['industry'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Website</label>
                <input type="url" name="website" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($company['website'] ?? '') ?>">
              </div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold">Location</label>
                <input type="text" name="location" class="form-control" placeholder="City, Province" value="<?= htmlspecialchars($company['location'] ?? '') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Company Size</label>
                <select name="company_size" class="form-select">
                  <option value="">Select...</option>
                  <?php foreach (['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+'] as $sz): ?>
                    <option <?= ($company['company_size'] ?? '') === $sz ? 'selected' : '' ?>><?= $sz ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold">Founded Year</label>
                <input type="number" name="founded_year" class="form-control" min="1900" max="<?= date('Y') ?>" value="<?= htmlspecialchars($company['founded_year'] ?? '') ?>">
              </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-4">
              <a href="employer_dashboard.php" class="btn btn-konekt-outline px-4">&larr; Back to Dashboard</a>
              <button type="submit" class="btn btn-konekt-primary px-4">Save Company Profile</button>
            </div>
          </form>
        </div>

        <div class="konekt-card danger-zone p-4 mt-4">
          <div class="d-flex gap-3 align-items-start">
            <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
            <div class="flex-grow-1">
              <h2 class="h6 mb-1">Delete employer account</h2>
              <p class="small text-secondary mb-3">This permanently removes your company, job postings, applications, and profile data.</p>
              <form method="post" action="api/auth/delete_profile.php" onsubmit="return confirm('Delete your employer account permanently? This cannot be undone.');">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                  <i class="bi bi-trash3 me-1"></i> Delete account
                </button>
              </form>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>

  <?php include 'includes/footer.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
