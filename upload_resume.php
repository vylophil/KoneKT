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
require_once __DIR__ . '/api/helpers/validation.php';

$active_page = 'upload';
$unread_messages = 0;
$uploadMsg = '';
$uploadOk  = false;

// Get current resume info
$currentResume = null;
try {
  $stmt = getDB()->prepare('SELECT resume_url FROM profiles WHERE user_id = :uid');
  $stmt->execute([':uid' => $_SESSION['user_id']]);
  $profile = $stmt->fetch();
  $currentResume = $profile['resume_url'] ?? null;
} catch (Throwable $e) {}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resume'])) {
  $file = $_FILES['resume'];

  $errors = validateFileUpload($file, ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], 5);
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['pdf', 'docx'])) {
    $errors[] = 'Only PDF and DOCX files are accepted.';
  }

  if (!empty($errors)) {
    $uploadMsg = implode(' ', $errors);
  } else {
    $filename  = 'resume_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
    $uploadDir = __DIR__ . '/uploads/resumes/';
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }
    $destination = $uploadDir . $filename;

    // Delete old resume
    if ($currentResume) {
      $oldFile = __DIR__ . '/' . $currentResume;
      if (file_exists($oldFile)) {
        @unlink($oldFile);
      }
    }

    if (move_uploaded_file($file['tmp_name'], $destination)) {
      $resumeUrl = 'uploads/resumes/' . $filename;
      try {
        $stmt = getDB()->prepare('UPDATE profiles SET resume_url = :url WHERE user_id = :uid');
        $stmt->execute([':url' => $resumeUrl, ':uid' => $_SESSION['user_id']]);
        $currentResume = $resumeUrl;
        $uploadOk  = true;
        $uploadMsg = 'Resume uploaded successfully! Proceed to set your job preferences.';
      } catch (Throwable $e) {
        $uploadMsg = 'File saved but database update failed.';
      }
    } else {
      $uploadMsg = 'Failed to save the uploaded file.';
    }
  }
}
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

        <?php if (!empty($uploadMsg)): ?>
          <div class="alert <?= $uploadOk ? 'alert-success' : 'alert-danger' ?> small">
            <?= htmlspecialchars($uploadMsg, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <!-- Current resume status -->
        <?php if ($currentResume): ?>
        <div class="konekt-card p-3 mb-4 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-pdf fs-4 text-danger"></i>
            <div>
              <p class="fw-semibold mb-0 small">Current Resume</p>
              <p class="text-secondary mb-0" style="font-size: 0.78rem;"><?= htmlspecialchars(basename($currentResume), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </div>
          <span class="status-badge accepted"><i class="bi bi-check-circle-fill"></i> Uploaded</span>
        </div>
        <?php endif; ?>

        <div class="konekt-card p-4 p-md-5 mb-4">
          <form action="upload_resume.php" method="POST" enctype="multipart/form-data">

            <label for="resume_file" class="upload-zone d-block mb-4 <?= $currentResume ? 'uploaded' : '' ?>" id="dropZone">
              <i class="bi bi-cloud-arrow-up display-4 text-primary mb-3 d-block"></i>
              <p class="fw-semibold mb-1">Drag and drop your resume here, or <span class="text-primary text-decoration-underline">browse</span></p>
              <p class="text-secondary small mb-0">Supports PDF, DOCX (Max 5MB)</p>
              <p class="fw-semibold text-primary small mt-2 mb-0" id="fileName"></p>
              <input type="file" name="resume" id="resume_file" class="d-none" accept=".pdf,.docx">
            </label>

            <div class="d-flex justify-content-between align-items-center">
              <a href="dashboard.php" class="btn btn-konekt-outline px-4">Cancel</a>
              <button type="submit" class="btn btn-konekt-primary px-4">
                <i class="bi bi-cloud-arrow-up me-1"></i>
                <?= $currentResume ? 'Re-upload Resume' : 'Upload Resume' ?>
              </button>
            </div>

          </form>
        </div>

        <?php if ($currentResume || $uploadOk): ?>
        <div class="text-center">
          <a href="job_preferences.php" class="btn btn-konekt-gold px-4 py-2">
            Continue to Preferences <i class="bi bi-arrow-right ms-1"></i>
          </a>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </main>

  <!-- Shared Footer -->
  <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const fileInput = document.getElementById('resume_file');
    const dropZone = document.getElementById('dropZone');
    const fileName = document.getElementById('fileName');

    fileInput.addEventListener('change', () => {
      if (fileInput.files.length > 0) {
        fileName.textContent = fileInput.files[0].name;
        dropZone.classList.add('uploaded');
      }
    });

    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('dragover'); });
    dropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropZone.classList.remove('dragover');
      if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        fileName.textContent = e.dataTransfer.files[0].name;
        dropZone.classList.add('uploaded');
      }
    });
  </script>
</body>
</html>