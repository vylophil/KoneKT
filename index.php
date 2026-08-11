<?php
$active_page = 'home';
$unread_messages = 3; // Pull real count dynamically from DB if logged in
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KoneKT · Smart Resume & Cross-Field Job Matching</title>

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

  <!-- Hero Section -->
  <header class="py-5 bg-white border-bottom">
    <div class="container my-4 text-center">
      <span class="match-chip mb-3">
        <i class="bi bi-stars"></i> AI-Powered Career Matchmaking
      </span>
      <h1 class="display-4 fw-bold mb-3">Connect Your Degree to <br class="d-none d-md-inline"><span style="color: var(--signal-blue);">Any Industry</span> You Want</h1>
      <p class="lead text-secondary mx-auto mb-4" style="max-width: 650px;">
        Are you a BSIT graduate looking for opportunities in Healthcare, Accounting, or Logistics? Upload your resume and let <strong>KoneKT</strong> match your skills across fields.
      </p>
      
      <div class="d-flex justify-content-center gap-3">
        <a href="upload_resume.php" class="btn btn-konekt-primary btn-lg px-4 fs-6">
          <i class="bi bi-cloud-arrow-up me-2"></i>Upload Resume
        </a>
        <a href="dashboard.php" class="btn btn-konekt-outline btn-lg px-4 fs-6">
          Go to Dashboard
        </a>
      </div>
    </div>
  </header>

  <!-- Main Content Area -->
  <main class="container py-5">
    
    <!-- Feature Highlights -->
    <div class="row g-4 mb-5">
      <div class="col-md-4">
        <div class="konekt-card p-4 h-100">
          <div class="rounded-circle p-3 d-inline-flex mb-3" style="background-color: var(--ember-gold-soft); color: var(--ember-gold);">
            <i class="bi bi-file-earmark-person fs-3"></i>
          </div>
          <h2 class="h5 mb-2">1. Resume Parsing</h2>
          <p class="text-secondary small mb-0">Upload your PDF or DOCX file. Our engine extracts your core competencies and technical skills automatically.</p>
        </div>
      </div>

      <div class="col-md-4">
        <div class="konekt-card p-4 h-100">
          <div class="rounded-circle p-3 d-inline-flex mb-3" style="background-color: var(--ember-gold-soft); color: var(--ember-gold);">
            <i class="bi bi-funnel fs-3"></i>
          </div>
          <h2 class="h5 mb-2">2. Cross-Field Filtering</h2>
          <p class="text-secondary small mb-0">Pivot your qualifications! Specify interest in specialized fields like Healthcare, Finance, or Education.</p>
        </div>
      </div>

      <div class="col-md-4">
        <div class="konekt-card p-4 h-100">
          <div class="rounded-circle p-3 d-inline-flex mb-3" style="background-color: var(--ember-gold-soft); color: var(--ember-gold);">
            <i class="bi bi-chat-dots fs-3"></i>
          </div>
          <h2 class="h5 mb-2">3. Direct Networking</h2>
          <p class="text-secondary small mb-0">Chat directly with recruiters and industry peers through our built-in messaging platform.</p>
        </div>
      </div>
    </div>

    <!-- Quick Callout Section -->
    <div class="konekt-card p-4 p-md-5 text-white text-center rounded-4" style="background-color: var(--ink-navy);">
      <h2 class="h3 mb-2 text-white">Ready to find your match?</h2>
      <p class="mb-4 text-white-50">Set up your cross-field preferences in less than 2 minutes.</p>
      <a href="upload_resume.php" class="btn btn-konekt-gold px-4 py-2">Get Started Now</a>
    </div>

  </main>

  <!-- Shared Footer -->
  <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>