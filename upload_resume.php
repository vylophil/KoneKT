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
  <title>Upload Resume · KoneKT</title>

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
        
        <div class="text-center mb-4">
          <h1 class="h3 mb-2">Upload Your Resume</h1>
          <p class="text-secondary">Upload your PDF or DOCX file to match your degree and skills with open roles.</p>
        </div>

        <div class="konekt-card p-4 p-md-5 mb-4">
          <form action="job_preferences.php" method="POST" enctype="multipart/form-data">
            
            <div class="border border-2 border-dashed rounded-3 p-5 text-center bg-light mb-4">
              <i class="bi bi-cloud-arrow-up display-4 text-primary mb-3 d-block"></i>
              <p class="fw-semibold mb-1">Drag and drop your resume here, or <label for="resume_file" class="text-primary text-decoration-underline cursor-pointer">browse</label></p>
              <p class="text-secondary small mb-0">Supports PDF, DOCX (Max 5MB)</p>
              <input type="file" name="resume" id="resume_file" class="d-none" accept=".pdf,.docx">
            </div>

            <div class="d-flex justify-content-between align-items-center">
              <a href="dashboard.php" class="btn btn-konekt-outline px-4">Cancel</a>
              <button type="submit" class="btn btn-konekt-primary px-4">Continue to Preferences &rarr;</button>
            </div>

          </form>
        </div>

      </div>
    </div>
  </main>

  <!-- Shared Footer -->
  <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>