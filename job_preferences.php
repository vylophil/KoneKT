<?php
session_start();
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

$active_page = 'upload';
$unread_messages = 3;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Preferences · KoneKT</title>

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
    <div class="row justify-content-center">
      <div class="col-lg-8">
        
        <!-- Header Section -->
        <div class="text-center mb-4">
          <span class="match-chip mb-2">
            <i class="bi bi-sliders"></i> Step 2 of 2
          </span>
          <h1 class="h3 mb-2">Cross-Field Job Preferences</h1>
          <p class="text-secondary">Tell KoneKT where you'd like to apply your degree beyond traditional career paths.</p>
        </div>

        <form action="job_matches.php" method="POST">
          
          <!-- Card 1: Academic & Professional Profile -->
          <div class="konekt-card p-4 mb-4">
            <h2 class="h6 mb-3 text-uppercase tracking-wider text-secondary">1. Academic Background</h2>
            
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Degree / Field of Study</label>
                <select class="form-select" name="degree">
                  <option selected>BS Information Technology (BSIT)</option>
                  <option>BS Computer Science (BSCS)</option>
                  <option>BS Business Administration (BSBA)</option>
                  <option>BS Nursing / Allied Health</option>
                  <option>BS Civil Engineering</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Primary Skill Category</label>
                <select class="form-select" name="primary_skill">
                  <option selected>Database Admin & SQL</option>
                  <option>Network & Systems Admin</option>
                  <option>Software Development</option>
                  <option>Data Analysis & Excel</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Card 2: Target Industries Selection -->
          <div class="konekt-card p-4 mb-4">
            <h2 class="h6 mb-3 text-uppercase tracking-wider text-secondary">2. Target Industries</h2>
            <p class="text-secondary small mb-3">Select industries where you want our matchmaking engine to find cross-field opportunities:</p>
            
            <div class="d-flex flex-wrap gap-2 mb-3">
              <input type="checkbox" class="btn-check" id="field-med" name="fields[]" value="Healthcare" checked>
              <label class="btn btn-konekt-outline btn-sm rounded-pill px-3 py-2" for="field-med">
                <i class="bi bi-hospital me-1"></i> Healthcare / Med-Tech
              </label>

              <input type="checkbox" class="btn-check" id="field-acct" name="fields[]" value="Accounting" checked>
              <label class="btn btn-konekt-outline btn-sm rounded-pill px-3 py-2" for="field-acct">
                <i class="bi bi-calculator me-1"></i> Accounting / Fintech
              </label>

              <input type="checkbox" class="btn-check" id="field-sys" name="fields[]" value="Systems" checked>
              <label class="btn btn-konekt-outline btn-sm rounded-pill px-3 py-2" for="field-sys">
                <i class="bi bi-cpu me-1"></i> Systems Administration
              </label>

              <input type="checkbox" class="btn-check" id="field-edu" name="fields[]" value="Education">
              <label class="btn btn-konekt-outline btn-sm rounded-pill px-3 py-2" for="field-edu">
                <i class="bi bi-journal-bookmark me-1"></i> Education & EdTech
              </label>

              <input type="checkbox" class="btn-check" id="field-logistics" name="fields[]" value="Logistics">
              <label class="btn btn-konekt-outline btn-sm rounded-pill px-3 py-2" for="field-logistics">
                <i class="bi bi-truck me-1"></i> Logistics & Supply Chain
              </label>
            </div>
          </div>

          <!-- Card 3: Preferred Location & Work Style -->
          <div class="konekt-card p-4 mb-4">
            <h2 class="h6 mb-3 text-uppercase tracking-wider text-secondary">3. Location & Availability</h2>
            
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Target Location</label>
                <input type="text" class="form-control" name="location" value="Clark, Pampanga" placeholder="e.g. Clark, Manila, Remote">
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Work Setup Preference</label>
                <select class="form-select" name="work_setup">
                  <option>Hybrid</option>
                  <option>On-site</option>
                  <option>Remote</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="d-flex justify-content-between align-items-center">
            <a href="upload_resume.php" class="btn btn-konekt-outline px-4">&larr; Back to Resume Upload</a>
            <button type="submit" class="btn btn-konekt-primary px-4 py-2">
              <i class="bi bi-stars me-1"></i> Generate Job Matches
            </button>
          </div>

        </form>

      </div>
    </div>
  </main>

  <!-- Shared Footer -->
  <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>