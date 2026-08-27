<?php
session_start();

require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/helpers/validation.php';

$active_page = 'home';
$unread_messages = 0;
$authError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $firstName   = trim($_POST['first_name'] ?? '');
  $lastName    = trim($_POST['last_name'] ?? '');
  $email       = strtolower(trim($_POST['email'] ?? ''));
  $password    = $_POST['password'] ?? '';
  $role        = ($_POST['role'] ?? 'job_seeker') === 'employer' ? 'employer' : 'job_seeker';
  $companyName = trim($_POST['company_name'] ?? '');

  if (!validateEmail($email)) {
    $authError = 'Please enter a valid email address.';
  } elseif (!validateLength($firstName, 1, 100) || !validateLength($lastName, 1, 100)) {
    $authError = 'Please enter both your first and last name.';
  } elseif (mb_strlen($password) < 8) {
    $authError = 'Password must be at least 8 characters long.';
  } elseif ($role === 'employer' && mb_strlen($companyName) < 2) {
    $authError = 'Please enter your company name.';
  } else {
    try {
      $db = getDB();
      $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
      $stmt->execute([':email' => $email]);

      if ($stmt->fetch()) {
        $authError = 'An account with that email already exists.';
      } else {
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->beginTransaction();

        $stmt = $db->prepare('INSERT INTO users (email, password_hash, role, first_name, last_name) VALUES (:email, :password_hash, :role, :first_name, :last_name)');
        $stmt->execute([
          ':email'         => $email,
          ':password_hash' => $passwordHash,
          ':role'          => $role,
          ':first_name'    => $firstName,
          ':last_name'     => $lastName,
        ]);

        $userId = (int) $db->lastInsertId();

        // Create profile for all users
        $stmt = $db->prepare('INSERT INTO profiles (user_id) VALUES (:user_id)');
        $stmt->execute([':user_id' => $userId]);

        // If employer, create a company record
        if ($role === 'employer') {
          $stmt = $db->prepare('INSERT INTO companies (user_id, name) VALUES (:user_id, :name)');
          $stmt->execute([':user_id' => $userId, ':name' => $companyName]);
        }

        $db->commit();

        $_SESSION['user_id']    = $userId;
        $_SESSION['email']      = $email;
        $_SESSION['role']       = $role;
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name']  = $lastName;

        header('Location: ' . ($role === 'employer' ? 'employer_dashboard.php' : 'dashboard.php'));
        exit;
      }
    } catch (Throwable $e) {
      if (!empty($db) && is_object($db) && method_exists($db, 'inTransaction')) {
        try {
          if ($db->inTransaction()) {
            $db->rollBack();
          }
        } catch (Throwable $rollbackError) {
          // ignore rollback errors
        }
      }
      $authError = 'Registration failed. Please try again.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account · KoneKT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>
  <?php if (file_exists('includes/navbar.php')) include 'includes/navbar.php'; ?>

  <main class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="konekt-card p-4 p-md-5">
          <h1 class="h3 mb-2">Create your account</h1>
          <p class="text-secondary mb-4">Join KoneKT to start building your cross-field career profile.</p>

          <?php if (!empty($authError)): ?>
            <div class="alert alert-danger small"><?= htmlspecialchars($authError, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>

          <form method="post" id="registerForm">

            <?php // Role Toggle ?>
            <div class="mb-4">
              <label class="form-label fw-semibold d-block">I am a</label>
              <div class="role-toggle d-flex rounded-3 overflow-hidden border" style="max-width: 340px;">
                <label class="role-toggle-option flex-fill text-center py-2 mb-0" id="labelSeeker">
                  <input type="radio" name="role" value="job_seeker" class="d-none" checked>
                  <i class="bi bi-person-badge me-1"></i> Job Seeker
                </label>
                <label class="role-toggle-option flex-fill text-center py-2 mb-0" id="labelEmployer">
                  <input type="radio" name="role" value="employer" class="d-none">
                  <i class="bi bi-building me-1"></i> Employer
                </label>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">First name</label>
                <input type="text" name="first_name" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Last name</label>
                <input type="text" name="last_name" class="form-control" required>
              </div>
            </div>

            <?php // Company name (shown only for employers) ?>
            <div class="mb-3" id="companyField" style="display: none;">
              <label class="form-label fw-semibold">Company name</label>
              <input type="text" name="company_name" class="form-control" placeholder="e.g. Accenture Philippines">
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Email address</label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Password</label>
              <input type="password" name="password" class="form-control" required minlength="8">
              <div class="form-text">At least 8 characters.</div>
            </div>
            <button type="submit" class="btn btn-konekt-primary w-100">Create Account</button>
          </form>

          <div class="mt-3 small text-secondary">
            Already have an account? <a href="login.php">Sign in</a>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Role toggle UI logic
    const radios = document.querySelectorAll('input[name="role"]');
    const companyField = document.getElementById('companyField');
    const labels = document.querySelectorAll('.role-toggle-option');

    function updateToggle() {
      radios.forEach((radio, i) => {
        if (radio.checked) {
          labels[i].classList.add('active');
        } else {
          labels[i].classList.remove('active');
        }
      });
      const isEmployer = document.querySelector('input[name="role"]:checked').value === 'employer';
      companyField.style.display = isEmployer ? 'block' : 'none';
      if (isEmployer) {
        companyField.querySelector('input').setAttribute('required', '');
      } else {
        companyField.querySelector('input').removeAttribute('required');
      }
    }

    radios.forEach(r => r.addEventListener('change', updateToggle));
    labels.forEach(label => {
      label.addEventListener('click', () => {
        const radio = label.querySelector('input[type="radio"]');
        if (radio) { radio.checked = true; updateToggle(); }
      });
    });
    updateToggle();
  </script>
</body>
</html>
