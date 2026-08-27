<footer class="konekt-footer">
  <div class="container">
    <div class="row g-4">
      <?php // Brand & Tagline ?>
      <div class="col-6 col-md-3">
        <a href="index.php" class="navbar-brand d-inline-flex align-items-center mb-2" style="font-family: var(--font-display); font-weight: 700; font-size: 1.2rem; color: var(--ink-navy);">
          <span class="match-pulse me-1"></span>
          <span style="color: var(--ember-gold);">Kone</span>KT
        </a>
        <p class="mt-2 mb-0 text-secondary" style="font-size: 0.88rem;">Matching your resume to the right opportunity across fields.</p>
      </div>

      <?php // Quick Links: Job Seekers ?>
      <div class="col-6 col-md-3">
        <h6>Job Seekers</h6>
        <ul class="list-unstyled mb-0">
          <li class="mb-2"><a href="upload_resume.php">Upload Resume</a></li>
          <li class="mb-2"><a href="job_matches.php">Browse Matches</a></li>
          <li class="mb-2"><a href="job_preferences.php">Set Preferences</a></li>
          <li class="mb-2"><a href="my_applications.php">My Applications</a></li>
        </ul>
      </div>

      <?php // Quick Links: Employers ?>
      <div class="col-6 col-md-3">
        <h6>Employers</h6>
        <ul class="list-unstyled mb-0">
          <li class="mb-2"><a href="employer_dashboard.php">Employer Dashboard</a></li>
          <li class="mb-2"><a href="employer_jobs.php">Post Jobs</a></li>
          <li class="mb-2"><a href="employer_applicants.php">View Applicants</a></li>
          <li class="mb-2"><a href="employer_company.php">Company Profile</a></li>
        </ul>
      </div>

      <?php // Quick Links: Network & Explore ?>
      <div class="col-6 col-md-3">
        <h6>Network & Explore</h6>
        <ul class="list-unstyled mb-0">
          <li class="mb-2"><a href="network.php">Messages</a></li>
          <li class="mb-2"><a href="find_jobs.php">Find Jobs</a></li>
          <li class="mb-2"><a href="index.php">About KoneKT</a></li>
        </ul>
      </div>
    </div>

    <?php // Bottom Copyright Bar ?>
    <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
      <span>&copy; <?= date('Y') ?> KoneKT. All rights reserved.</span>
      <span>Built for job seekers who match on merit.</span>
    </div>
  </div>
</footer>

<script src="assets/js/app.js"></script>