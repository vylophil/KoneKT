<?php
// ============================================================
// KONEKT — Demo Data Seeder
// ============================================================
// Run via browser: http://localhost/.../seed_demo.php
//
// Inserts realistic demo data: employers, companies, jobs,
// job seekers, profiles, skills, connections, messages, and
// job applications.
//
// All demo users use emails: demo_*@konekt.test
// All demo passwords:        Demo@12345
//
// Safe to run multiple times (idempotent — skips if exists).
// To remove all demo data, run: remove_demo.php
// ============================================================

require_once __DIR__ . '/api/config/database.php';

// ── Helpers ──────────────────────────────────────────────────
function out(string $msg, string $type = 'info'): void {
    $icons = ['ok' => '✅', 'skip' => '⏭️', 'info' => 'ℹ️', 'err' => '❌', 'section' => '📦'];
    $icon = $icons[$type] ?? '';
    echo "<div style='margin:2px 0;padding:4px 8px;font-family:\"Segoe UI\",sans-serif;font-size:14px;'>{$icon} {$msg}</div>\n";
}

function sectionHeader(string $title): void {
    echo "<div style='margin:16px 0 4px;padding:8px 12px;background:#1a1a2e;color:#e6b800;font-family:\"Segoe UI\",sans-serif;font-weight:600;border-radius:6px;'>{$title}</div>\n";
}

// ── Start ────────────────────────────────────────────────────
$startTime = microtime(true);

echo '<!DOCTYPE html><html><head><title>KoneKT · Seed Demo Data</title>';
echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<style>body{background:#0f0f23;color:#e0e0e0;padding:24px 32px;max-width:800px;margin:0 auto;}';
echo 'a{color:#e6b800;text-decoration:none;}a:hover{text-decoration:underline;}';
echo '.summary{background:#16213e;border:1px solid #e6b800;border-radius:10px;padding:20px;margin-top:20px;}';
echo '.summary h2{color:#e6b800;margin:0 0 12px;font-size:18px;}';
echo '.summary table{width:100%;border-collapse:collapse;font-size:13px;}';
echo '.summary th,.summary td{padding:6px 10px;border-bottom:1px solid #1a1a3e;text-align:left;}';
echo '.summary th{color:#a0a0c0;font-weight:600;}.summary td code{color:#e6b800;background:#1a1a2e;padding:2px 6px;border-radius:3px;}';
echo '</style></head><body>';
echo '<h1 style="color:#e6b800;font-family:\'Segoe UI\',sans-serif;">🎭 KoneKT Demo Seeder</h1>';

try {
    $db = getDB();
} catch (Throwable $e) {
    out('Cannot connect to database: ' . htmlspecialchars($e->getMessage()), 'err');
    echo '<p>Make sure Laragon is running and the <code>konekt_db</code> database exists.</p>';
    echo '<a href="create_db.php">→ Run create_db.php first</a>';
    echo '</body></html>';
    exit;
}

// ── Check if demo data already exists ────────────────────────
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email LIKE 'demo_%@konekt.test'");
$stmt->execute();
$existingCount = (int) $stmt->fetchColumn();

if ($existingCount > 0) {
    out("Demo data already exists ({$existingCount} demo users found). Remove it first if you want to re-seed.", 'skip');
    echo '<p style="margin-top:12px;"><a href="remove_demo.php">🗑️ Remove Demo Data</a> &nbsp;|&nbsp; <a href="index.php">🏠 Go to Homepage</a></p>';
    echo '</body></html>';
    exit;
}

// ── Constants ────────────────────────────────────────────────
$demoPassword = password_hash('Demo@12345', PASSWORD_BCRYPT, ['cost' => 12]);

// ── Helper: get skill ID by name ─────────────────────────────
function getSkillId(PDO $db, string $name): ?int {
    static $cache = [];
    if (isset($cache[$name])) return $cache[$name];
    $stmt = $db->prepare('SELECT id FROM skills WHERE name = :n LIMIT 1');
    $stmt->execute([':n' => $name]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        $cache[$name] = (int) $id;
        return (int) $id;
    }
    return null;
}

// ── Helper: insert user and return ID ────────────────────────
function insertUser(PDO $db, string $email, string $passwordHash, string $role, string $firstName, string $lastName): int {
    $stmt = $db->prepare('INSERT INTO users (email, password_hash, role, first_name, last_name) VALUES (:e, :p, :r, :fn, :ln)');
    $stmt->execute([':e' => $email, ':p' => $passwordHash, ':r' => $role, ':fn' => $firstName, ':ln' => $lastName]);
    return (int) $db->lastInsertId();
}

// ── Helper: insert profile ───────────────────────────────────
function insertProfile(PDO $db, int $userId, array $data): void {
    $stmt = $db->prepare('INSERT INTO profiles (user_id, headline, bio, location, phone, industry, years_of_experience) VALUES (:uid, :h, :b, :loc, :ph, :ind, :yoe)');
    $stmt->execute([
        ':uid' => $userId,
        ':h'   => $data['headline'] ?? null,
        ':b'   => $data['bio'] ?? null,
        ':loc' => $data['location'] ?? null,
        ':ph'  => $data['phone'] ?? null,
        ':ind' => $data['industry'] ?? null,
        ':yoe' => $data['years_of_experience'] ?? 0,
    ]);
}

// ── Helper: insert company and return ID ─────────────────────
function insertCompany(PDO $db, int $userId, array $data): int {
    $stmt = $db->prepare('INSERT INTO companies (user_id, name, description, industry, website, location, company_size, founded_year) VALUES (:uid, :n, :d, :ind, :w, :loc, :cs, :fy)');
    $stmt->execute([
        ':uid' => $userId,
        ':n'   => $data['name'],
        ':d'   => $data['description'],
        ':ind' => $data['industry'],
        ':w'   => $data['website'] ?? null,
        ':loc' => $data['location'],
        ':cs'  => $data['company_size'] ?? null,
        ':fy'  => $data['founded_year'] ?? null,
    ]);
    return (int) $db->lastInsertId();
}

// ── Helper: insert job posting and return ID ─────────────────
function insertJob(PDO $db, int $companyId, int $employerId, array $data): int {
    $stmt = $db->prepare('
        INSERT INTO job_postings (company_id, employer_id, title, description, requirements, responsibilities, location, job_type, work_arrangement, salary_min, salary_max, salary_currency, experience_level, min_experience_years, education_requirement, deadline)
        VALUES (:cid, :eid, :title, :desc, :req, :resp, :loc, :jt, :wa, :smin, :smax, :cur, :el, :mey, :er, :dl)
    ');
    $stmt->execute([
        ':cid'   => $companyId,
        ':eid'   => $employerId,
        ':title' => $data['title'],
        ':desc'  => $data['description'],
        ':req'   => $data['requirements'] ?? null,
        ':resp'  => $data['responsibilities'] ?? null,
        ':loc'   => $data['location'],
        ':jt'    => $data['job_type'],
        ':wa'    => $data['work_arrangement'] ?? 'on_site',
        ':smin'  => $data['salary_min'] ?? null,
        ':smax'  => $data['salary_max'] ?? null,
        ':cur'   => $data['salary_currency'] ?? 'PHP',
        ':el'    => $data['experience_level'] ?? 'entry',
        ':mey'   => $data['min_experience_years'] ?? 0,
        ':er'    => $data['education_requirement'] ?? 'none',
        ':dl'    => $data['deadline'] ?? null,
    ]);
    return (int) $db->lastInsertId();
}

// ══════════════════════════════════════════════════════════════
//  BEGIN TRANSACTION
// ══════════════════════════════════════════════════════════════
$db->beginTransaction();

try {

    // ══════════════════════════════════════════════════════════
    // 1. EMPLOYER ACCOUNTS & COMPANIES
    // ══════════════════════════════════════════════════════════
    sectionHeader('1 · Employer Accounts & Companies');

    // ── Employer 1: Maria Santos — TechVault Philippines ─────
    $mariaId = insertUser($db, 'demo_maria@konekt.test', $demoPassword, 'employer', 'Maria', 'Santos');
    insertProfile($db, $mariaId, [
        'headline'  => 'CEO & Founder at TechVault Philippines',
        'bio'       => 'Passionate about building the next generation of Filipino tech talent. Founded TechVault to bridge the gap between education and industry.',
        'location'  => 'Makati City, Metro Manila',
        'phone'     => '+63 917 123 4567',
        'industry'  => 'Technology',
        'years_of_experience' => 12,
    ]);
    $techVaultId = insertCompany($db, $mariaId, [
        'name'         => 'TechVault Philippines',
        'description'  => 'TechVault Philippines is a leading software development company specializing in enterprise solutions, cloud architecture, and digital transformation for businesses across Southeast Asia.',
        'industry'     => 'Technology',
        'website'      => 'https://techvault.ph',
        'location'     => 'Makati City, Metro Manila',
        'company_size' => '51-200',
        'founded_year' => 2018,
    ]);
    out("Maria Santos → TechVault Philippines (ID: {$techVaultId})", 'ok');

    // ── Employer 2: James Reyes — GreenField Agritech ────────
    $jamesId = insertUser($db, 'demo_james@konekt.test', $demoPassword, 'employer', 'James', 'Reyes');
    insertProfile($db, $jamesId, [
        'headline'  => 'Managing Director at GreenField Agritech',
        'bio'       => 'Driving agricultural innovation through technology. GreenField merges data science with sustainable farming to feed the future.',
        'location'  => 'Los Baños, Laguna',
        'phone'     => '+63 918 234 5678',
        'industry'  => 'Agriculture',
        'years_of_experience' => 8,
    ]);
    $greenFieldId = insertCompany($db, $jamesId, [
        'name'         => 'GreenField Agritech',
        'description'  => 'GreenField Agritech develops precision agriculture tools, drone-based crop monitoring systems, and data-driven solutions for Filipino farmers.',
        'industry'     => 'Agriculture',
        'website'      => 'https://greenfield-agri.ph',
        'location'     => 'Los Baños, Laguna',
        'company_size' => '11-50',
        'founded_year' => 2020,
    ]);
    out("James Reyes → GreenField Agritech (ID: {$greenFieldId})", 'ok');

    // ── Employer 3: Anna Cruz — MediCare Solutions ───────────
    $annaId = insertUser($db, 'demo_anna@konekt.test', $demoPassword, 'employer', 'Anna', 'Cruz');
    insertProfile($db, $annaId, [
        'headline'  => 'CTO at MediCare Solutions',
        'bio'       => 'Leading the digital health revolution in the Philippines. Our mission is to make quality healthcare accessible through technology.',
        'location'  => 'Cebu City, Cebu',
        'phone'     => '+63 919 345 6789',
        'industry'  => 'Healthcare',
        'years_of_experience' => 10,
    ]);
    $mediCareId = insertCompany($db, $annaId, [
        'name'         => 'MediCare Solutions',
        'description'  => 'MediCare Solutions builds electronic health record systems, telemedicine platforms, and health informatics tools for hospitals and clinics nationwide.',
        'industry'     => 'Healthcare',
        'website'      => 'https://medicare-solutions.ph',
        'location'     => 'Cebu City, Cebu',
        'company_size' => '11-50',
        'founded_year' => 2019,
    ]);
    out("Anna Cruz → MediCare Solutions (ID: {$mediCareId})", 'ok');

    // ══════════════════════════════════════════════════════════
    // 2. JOB POSTINGS
    // ══════════════════════════════════════════════════════════
    sectionHeader('2 · Job Postings');

    $deadline = date('Y-m-d', strtotime('+60 days'));

    // ── TechVault Jobs ───────────────────────────────────────
    $job1 = insertJob($db, $techVaultId, $mariaId, [
        'title'                => 'Full-Stack Web Developer',
        'description'          => 'We are looking for a talented Full-Stack Web Developer to join our growing engineering team. You will design, develop, and maintain web applications using modern frameworks. The ideal candidate has experience with both front-end and back-end technologies and thrives in a collaborative, agile environment.',
        'requirements'         => "• 2+ years of experience in full-stack web development\n• Proficiency in PHP (Laravel preferred) and JavaScript\n• Experience with React or Vue.js\n• Strong understanding of MySQL/PostgreSQL\n• Familiarity with REST API design\n• Git version control proficiency",
        'responsibilities'     => "• Build and maintain scalable web applications\n• Collaborate with UI/UX designers to implement responsive interfaces\n• Write clean, documented, and testable code\n• Participate in code reviews and agile ceremonies\n• Optimize application performance and security",
        'location'             => 'Makati City, Metro Manila',
        'job_type'             => 'full_time',
        'work_arrangement'     => 'hybrid',
        'salary_min'           => 35000,
        'salary_max'           => 55000,
        'experience_level'     => 'mid',
        'min_experience_years' => 2,
        'education_requirement'=> 'bachelors',
        'deadline'             => $deadline,
    ]);
    // Attach skills to job 1
    foreach (['PHP', 'JavaScript', 'React', 'Laravel', 'MySQL', 'REST APIs', 'Git'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job1, $sid, 'required']);
        }
    }
    foreach (['HTML/CSS', 'Docker'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job1, $sid, 'preferred']);
        }
    }
    out("Full-Stack Web Developer @ TechVault (ID: {$job1})", 'ok');

    $job2 = insertJob($db, $techVaultId, $mariaId, [
        'title'                => 'UI/UX Designer',
        'description'          => 'Join our creative team as a UI/UX Designer. You will craft intuitive, visually stunning interfaces for our enterprise products. We value user-centered design thinking and someone who can translate complex workflows into delightful experiences.',
        'requirements'         => "• Portfolio showcasing UI/UX projects\n• Proficiency in Figma and Adobe Creative Suite\n• Understanding of responsive design principles\n• Knowledge of design systems and component libraries\n• Basic HTML/CSS knowledge is a plus",
        'responsibilities'     => "• Conduct user research and usability testing\n• Create wireframes, prototypes, and high-fidelity mockups\n• Collaborate with developers to ensure design accuracy\n• Maintain and evolve the company design system\n• Present design solutions to stakeholders",
        'location'             => 'Makati City, Metro Manila',
        'job_type'             => 'full_time',
        'work_arrangement'     => 'hybrid',
        'salary_min'           => 30000,
        'salary_max'           => 50000,
        'experience_level'     => 'mid',
        'min_experience_years' => 1,
        'education_requirement'=> 'bachelors',
        'deadline'             => $deadline,
    ]);
    foreach (['UI/UX Design', 'Figma', 'Adobe Photoshop', 'Adobe Illustrator'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job2, $sid, 'required']);
        }
    }
    foreach (['HTML/CSS', 'Figma Prototyping'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job2, $sid, 'preferred']);
        }
    }
    out("UI/UX Designer @ TechVault (ID: {$job2})", 'ok');

    $job3 = insertJob($db, $techVaultId, $mariaId, [
        'title'                => 'Data Analyst',
        'description'          => 'TechVault is seeking a Data Analyst to transform raw data into actionable business insights. You will work closely with product and engineering teams to drive data-informed decisions and build dashboards that empower our clients.',
        'requirements'         => "• Strong SQL and Python skills\n• Experience with data visualization tools (Tableau, Power BI, or similar)\n• Understanding of statistical analysis\n• Familiarity with ETL processes\n• Excellent communication skills to present findings",
        'responsibilities'     => "• Analyze large datasets to identify trends and patterns\n• Build and maintain interactive dashboards and reports\n• Collaborate with stakeholders to define KPIs and metrics\n• Automate recurring data analysis workflows\n• Ensure data quality and integrity",
        'location'             => 'Makati City, Metro Manila',
        'job_type'             => 'full_time',
        'work_arrangement'     => 'remote',
        'salary_min'           => 32000,
        'salary_max'           => 48000,
        'experience_level'     => 'entry',
        'min_experience_years' => 1,
        'education_requirement'=> 'bachelors',
        'deadline'             => $deadline,
    ]);
    foreach (['Python', 'SQL', 'Data Analysis', 'Data Visualization'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job3, $sid, 'required']);
        }
    }
    foreach (['Statistics', 'Machine Learning'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job3, $sid, 'nice_to_have']);
        }
    }
    out("Data Analyst @ TechVault (ID: {$job3})", 'ok');

    // ── GreenField Jobs ──────────────────────────────────────
    $job4 = insertJob($db, $greenFieldId, $jamesId, [
        'title'                => 'Farm Systems Engineer',
        'description'          => 'GreenField Agritech is hiring a Farm Systems Engineer to design and deploy IoT-based precision agriculture systems. You will work with drones, sensors, and embedded devices to help Filipino farmers optimize crop yields.',
        'requirements'         => "• Background in agricultural engineering, IT, or related field\n• Experience with IoT devices and sensor networks\n• Programming skills in Python or C++\n• Knowledge of GIS mapping tools\n• Willingness to do field work in rural areas",
        'responsibilities'     => "• Design and deploy smart farming sensor networks\n• Develop data collection pipelines from field devices\n• Collaborate with data scientists to analyze crop data\n• Provide technical support to farming communities\n• Document system architectures and field procedures",
        'location'             => 'Los Baños, Laguna',
        'job_type'             => 'full_time',
        'work_arrangement'     => 'on_site',
        'salary_min'           => 28000,
        'salary_max'           => 42000,
        'experience_level'     => 'entry',
        'min_experience_years' => 0,
        'education_requirement'=> 'bachelors',
        'deadline'             => $deadline,
    ]);
    foreach (['IoT', 'Python', 'GIS Mapping', 'Agricultural Technology'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job4, $sid, 'required']);
        }
    }
    foreach (['Embedded Systems', 'Data Analysis'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job4, $sid, 'preferred']);
        }
    }
    out("Farm Systems Engineer @ GreenField (ID: {$job4})", 'ok');

    $job5 = insertJob($db, $greenFieldId, $jamesId, [
        'title'                => 'Agri-Data Scientist',
        'description'          => 'We need a data-savvy individual who is passionate about agriculture. As our Agri-Data Scientist, you will build predictive models for crop health, analyze satellite imagery, and develop algorithms that help farmers make smarter decisions.',
        'requirements'         => "• Degree in Data Science, Statistics, Computer Science, or Agriculture\n• Strong Python skills with pandas, scikit-learn, or TensorFlow\n• Experience with geospatial data and remote sensing\n• Understanding of machine learning fundamentals\n• Interest in sustainable agriculture",
        'responsibilities'     => "• Build predictive models for crop yield and disease detection\n• Process and analyze satellite and drone imagery\n• Create data pipelines for real-time field analytics\n• Publish findings and insights for internal and client use\n• Collaborate with farm engineers on sensor data integration",
        'location'             => 'Los Baños, Laguna',
        'job_type'             => 'full_time',
        'work_arrangement'     => 'hybrid',
        'salary_min'           => 35000,
        'salary_max'           => 55000,
        'experience_level'     => 'mid',
        'min_experience_years' => 2,
        'education_requirement'=> 'bachelors',
        'deadline'             => $deadline,
    ]);
    foreach (['Python', 'Machine Learning', 'Data Analysis', 'Statistics'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job5, $sid, 'required']);
        }
    }
    foreach (['TensorFlow', 'GIS Mapping', 'Agricultural Technology'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job5, $sid, 'preferred']);
        }
    }
    out("Agri-Data Scientist @ GreenField (ID: {$job5})", 'ok');

    // ── GreenField Job 3: Agricultural Intern ────────────────
    $job6 = insertJob($db, $greenFieldId, $jamesId, [
        'title'                => 'Agricultural Technology Intern',
        'description'          => 'GreenField Agritech is offering a hands-on internship for students or fresh graduates passionate about the intersection of technology and agriculture. You will assist in field data collection, drone operations, and basic data analysis.',
        'requirements'         => "• Currently enrolled or recently graduated in Agriculture, IT, or Environmental Science\n• Basic knowledge of data collection methods\n• Willingness to work outdoors\n• Basic computer literacy (Excel, Google Sheets)\n• Interest in precision agriculture",
        'responsibilities'     => "• Assist with drone-based crop monitoring operations\n• Collect and organize field sensor data\n• Support the data science team with data cleaning\n• Document field observations and experiment results\n• Participate in team meetings and learning sessions",
        'location'             => 'Los Baños, Laguna',
        'job_type'             => 'internship',
        'work_arrangement'     => 'on_site',
        'salary_min'           => 8000,
        'salary_max'           => 12000,
        'experience_level'     => 'entry',
        'min_experience_years' => 0,
        'education_requirement'=> 'none',
        'deadline'             => $deadline,
    ]);
    foreach (['Agricultural Technology', 'Crop Management'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job6, $sid, 'preferred']);
        }
    }
    out("Agricultural Technology Intern @ GreenField (ID: {$job6})", 'ok');

    // ── MediCare Jobs ────────────────────────────────────────
    $job7 = insertJob($db, $mediCareId, $annaId, [
        'title'                => 'Health Informatics Specialist',
        'description'          => 'MediCare Solutions is looking for a Health Informatics Specialist to help design and implement electronic health record (EHR) systems for hospitals across the Philippines. This role bridges healthcare domain knowledge with IT expertise.',
        'requirements'         => "• Background in Health Informatics, IT, or Nursing with tech skills\n• Familiarity with EHR/EMR systems\n• Understanding of medical terminology and clinical workflows\n• Database management skills (SQL)\n• Excellent interpersonal and training skills",
        'responsibilities'     => "• Configure and deploy EHR systems at client hospitals\n• Train medical staff on system usage and best practices\n• Gather requirements from clinicians and translate to technical specs\n• Ensure compliance with health data privacy regulations\n• Provide ongoing technical support and system optimization",
        'location'             => 'Cebu City, Cebu',
        'job_type'             => 'full_time',
        'work_arrangement'     => 'on_site',
        'salary_min'           => 30000,
        'salary_max'           => 45000,
        'experience_level'     => 'mid',
        'min_experience_years' => 1,
        'education_requirement'=> 'bachelors',
        'deadline'             => $deadline,
    ]);
    foreach (['Health Informatics', 'Electronic Health Records', 'SQL', 'Medical Terminology'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job7, $sid, 'required']);
        }
    }
    foreach (['Data Privacy', 'Patient Care'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job7, $sid, 'preferred']);
        }
    }
    out("Health Informatics Specialist @ MediCare (ID: {$job7})", 'ok');

    $job8 = insertJob($db, $mediCareId, $annaId, [
        'title'                => 'IT Support Lead',
        'description'          => 'MediCare Solutions needs a reliable IT Support Lead to oversee technical operations at our partner clinics. You will manage network infrastructure, troubleshoot systems, and ensure uptime for mission-critical healthcare applications.',
        'requirements'         => "• 2+ years of IT support or system administration experience\n• Strong knowledge of networking, Windows/Linux servers\n• Experience with cybersecurity best practices\n• Familiarity with ITIL or similar frameworks\n• Ability to work under pressure in healthcare environments",
        'responsibilities'     => "• Manage and maintain network infrastructure at clinic sites\n• Provide tier-2/3 technical support for EHR systems\n• Implement cybersecurity policies and conduct audits\n• Train junior IT staff and document procedures\n• Coordinate with vendors for hardware and software procurement",
        'location'             => 'Cebu City, Cebu',
        'job_type'             => 'full_time',
        'work_arrangement'     => 'on_site',
        'salary_min'           => 28000,
        'salary_max'           => 40000,
        'experience_level'     => 'mid',
        'min_experience_years' => 2,
        'education_requirement'=> 'bachelors',
        'deadline'             => $deadline,
    ]);
    foreach (['IT Support', 'Networking', 'Cybersecurity', 'Linux'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job8, $sid, 'required']);
        }
    }
    foreach (['Network Security', 'Leadership'] as $sk) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO job_skills (job_id, skill_id, importance) VALUES (?, ?, ?)')->execute([$job8, $sid, 'preferred']);
        }
    }
    out("IT Support Lead @ MediCare (ID: {$job8})", 'ok');


    // ══════════════════════════════════════════════════════════
    // 3. JOB SEEKER ACCOUNTS
    // ══════════════════════════════════════════════════════════
    sectionHeader('3 · Job Seeker Accounts');

    // ── Seeker 1: Carlo Mendoza ──────────────────────────────
    $carloId = insertUser($db, 'demo_carlo@konekt.test', $demoPassword, 'job_seeker', 'Carlo', 'Mendoza');
    insertProfile($db, $carloId, [
        'headline'  => 'Full-Stack Web Developer',
        'bio'       => 'Passionate developer with 3 years of experience building web applications with PHP, Laravel, and React. Currently looking for opportunities to grow in a collaborative team environment.',
        'location'  => 'Quezon City, Metro Manila',
        'phone'     => '+63 920 111 2233',
        'industry'  => 'Technology',
        'years_of_experience' => 3,
    ]);
    // Skills
    foreach (['PHP' => 'advanced', 'JavaScript' => 'advanced', 'React' => 'intermediate', 'Laravel' => 'advanced', 'MySQL' => 'intermediate', 'HTML/CSS' => 'advanced', 'Git' => 'intermediate', 'REST APIs' => 'intermediate', 'Node.js' => 'beginner'] as $sk => $level) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO user_skills (user_id, skill_id, proficiency_level) VALUES (?, ?, ?)')->execute([$carloId, $sid, $level]);
        }
    }
    // Education
    $db->prepare('INSERT INTO education (user_id, institution, degree, field_of_study, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        $carloId, 'University of the Philippines Diliman', 'bachelors', 'Computer Science', '2018-06-01', '2022-05-30'
    ]);
    // Experience
    $db->prepare('INSERT INTO experience (user_id, company_name, job_title, location, start_date, is_current, description) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
        $carloId, 'Freelance', 'Web Developer', 'Remote', '2022-07-01', 1, 'Building custom web applications for small businesses using Laravel and React.'
    ]);
    out("Carlo Mendoza — Full-Stack Web Developer (ID: {$carloId})", 'ok');

    // ── Seeker 2: Sofia Garcia ───────────────────────────────
    $sofiaId = insertUser($db, 'demo_sofia@konekt.test', $demoPassword, 'job_seeker', 'Sofia', 'Garcia');
    insertProfile($db, $sofiaId, [
        'headline'  => 'UX Designer & Researcher',
        'bio'       => 'Creative UX designer with a keen eye for detail. I believe great design starts with understanding people. Experienced in Figma, user research, and building design systems.',
        'location'  => 'Taguig City, Metro Manila',
        'phone'     => '+63 921 222 3344',
        'industry'  => 'Design',
        'years_of_experience' => 2,
    ]);
    foreach (['UI/UX Design' => 'advanced', 'Figma' => 'expert', 'HTML/CSS' => 'intermediate', 'Adobe Photoshop' => 'intermediate', 'Adobe Illustrator' => 'intermediate', 'Figma Prototyping' => 'advanced', 'Graphic Design' => 'intermediate'] as $sk => $level) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO user_skills (user_id, skill_id, proficiency_level) VALUES (?, ?, ?)')->execute([$sofiaId, $sid, $level]);
        }
    }
    $db->prepare('INSERT INTO education (user_id, institution, degree, field_of_study, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        $sofiaId, 'De La Salle University', 'bachelors', 'Multimedia Arts', '2019-06-01', '2023-05-30'
    ]);
    $db->prepare('INSERT INTO experience (user_id, company_name, job_title, location, start_date, end_date, description) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
        $sofiaId, 'PixelCraft Studios', 'Junior UI/UX Designer', 'Makati City', '2023-07-01', '2024-12-31', 'Designed mobile and web interfaces for e-commerce clients. Conducted usability testing and iterated based on user feedback.'
    ]);
    out("Sofia Garcia — UX Designer & Researcher (ID: {$sofiaId})", 'ok');

    // ── Seeker 3: Miguel Torres ──────────────────────────────
    $miguelId = insertUser($db, 'demo_miguel@konekt.test', $demoPassword, 'job_seeker', 'Miguel', 'Torres');
    insertProfile($db, $miguelId, [
        'headline'  => 'Data Science Enthusiast',
        'bio'       => 'Recent graduate passionate about machine learning and data analytics. Built several projects using Python, scikit-learn, and TensorFlow. Eager to apply data science to real-world problems.',
        'location'  => 'Manila, Metro Manila',
        'phone'     => '+63 922 333 4455',
        'industry'  => 'Technology',
        'years_of_experience' => 1,
    ]);
    foreach (['Python' => 'advanced', 'Data Analysis' => 'intermediate', 'Machine Learning' => 'intermediate', 'SQL' => 'intermediate', 'TensorFlow' => 'beginner', 'Data Visualization' => 'intermediate', 'Statistics' => 'intermediate', 'R' => 'beginner'] as $sk => $level) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO user_skills (user_id, skill_id, proficiency_level) VALUES (?, ?, ?)')->execute([$miguelId, $sid, $level]);
        }
    }
    $db->prepare('INSERT INTO education (user_id, institution, degree, field_of_study, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        $miguelId, 'Ateneo de Manila University', 'bachelors', 'Applied Mathematics with Data Science', '2019-06-01', '2023-05-30'
    ]);
    out("Miguel Torres — Data Science Enthusiast (ID: {$miguelId})", 'ok');

    // ── Seeker 4: Isabella Ramos ─────────────────────────────
    $isabellaId = insertUser($db, 'demo_isabella@konekt.test', $demoPassword, 'job_seeker', 'Isabella', 'Ramos');
    insertProfile($db, $isabellaId, [
        'headline'  => 'IT Support Specialist',
        'bio'       => 'Detail-oriented IT professional with hands-on experience in network administration, system troubleshooting, and cybersecurity. Looking for a team where I can lead technical support operations.',
        'location'  => 'Cebu City, Cebu',
        'phone'     => '+63 923 444 5566',
        'industry'  => 'Technology',
        'years_of_experience' => 4,
    ]);
    foreach (['IT Support' => 'expert', 'Network Security' => 'advanced', 'Linux' => 'advanced', 'Cybersecurity' => 'intermediate', 'Networking' => 'advanced', 'Leadership' => 'intermediate'] as $sk => $level) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO user_skills (user_id, skill_id, proficiency_level) VALUES (?, ?, ?)')->execute([$isabellaId, $sid, $level]);
        }
    }
    $db->prepare('INSERT INTO education (user_id, institution, degree, field_of_study, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        $isabellaId, 'University of San Carlos', 'bachelors', 'Information Technology', '2017-06-01', '2021-05-30'
    ]);
    $db->prepare('INSERT INTO experience (user_id, company_name, job_title, location, start_date, is_current, description) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
        $isabellaId, 'NetGuard IT Services', 'IT Support Technician', 'Cebu City', '2021-08-01', 1, 'Managing network infrastructure for SME clients. Handling security audits, server maintenance, and help desk operations.'
    ]);
    out("Isabella Ramos — IT Support Specialist (ID: {$isabellaId})", 'ok');

    // ── Seeker 5: Rafael Lim ─────────────────────────────────
    $rafaelId = insertUser($db, 'demo_rafael@konekt.test', $demoPassword, 'job_seeker', 'Rafael', 'Lim');
    insertProfile($db, $rafaelId, [
        'headline'  => 'Agricultural Technologist',
        'bio'       => 'Bridging technology and agriculture. Experienced in GIS mapping, drone operations, and data-driven crop management. Passionate about sustainable farming and food security.',
        'location'  => 'Los Baños, Laguna',
        'phone'     => '+63 924 555 6677',
        'industry'  => 'Agriculture',
        'years_of_experience' => 2,
    ]);
    foreach (['Agricultural Technology' => 'advanced', 'GIS Mapping' => 'advanced', 'Python' => 'intermediate', 'Data Analysis' => 'intermediate', 'Crop Management' => 'intermediate', 'Sustainable Agriculture' => 'intermediate', 'IoT' => 'beginner'] as $sk => $level) {
        $sid = getSkillId($db, $sk);
        if ($sid) {
            $db->prepare('INSERT IGNORE INTO user_skills (user_id, skill_id, proficiency_level) VALUES (?, ?, ?)')->execute([$rafaelId, $sid, $level]);
        }
    }
    $db->prepare('INSERT INTO education (user_id, institution, degree, field_of_study, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)')->execute([
        $rafaelId, 'University of the Philippines Los Baños', 'bachelors', 'Agricultural Engineering', '2018-06-01', '2022-05-30'
    ]);
    $db->prepare('INSERT INTO experience (user_id, company_name, job_title, location, start_date, end_date, description) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([
        $rafaelId, 'PhilRice', 'Research Assistant', 'Muñoz, Nueva Ecija', '2022-08-01', '2024-06-30', 'Assisted in precision agriculture research. Operated mapping drones and processed geospatial crop data.'
    ]);
    out("Rafael Lim — Agricultural Technologist (ID: {$rafaelId})", 'ok');


    // ══════════════════════════════════════════════════════════
    // 4. CONNECTIONS & MESSAGES
    // ══════════════════════════════════════════════════════════
    sectionHeader('4 · Connections & Messages');

    // Carlo ↔ Sofia (accepted)
    $db->prepare("INSERT INTO connections (requester_id, receiver_id, status, message) VALUES (?, ?, 'accepted', ?)")->execute([
        $carloId, $sofiaId, 'Hey Sofia! Saw your portfolio — amazing work. Let\'s connect!'
    ]);
    out("Connection: Carlo ↔ Sofia (accepted)", 'ok');

    // Carlo ↔ Miguel (accepted)
    $db->prepare("INSERT INTO connections (requester_id, receiver_id, status, message) VALUES (?, ?, 'accepted', ?)")->execute([
        $miguelId, $carloId, 'Hi Carlo, I\'m interested in data-driven web apps. Would love to chat!'
    ]);
    out("Connection: Miguel ↔ Carlo (accepted)", 'ok');

    // Sofia ↔ Miguel (accepted)
    $db->prepare("INSERT INTO connections (requester_id, receiver_id, status, message) VALUES (?, ?, 'accepted', ?)")->execute([
        $sofiaId, $miguelId, 'Hi Miguel! I\'m designing dashboards and need data insights — let\'s connect.'
    ]);
    out("Connection: Sofia ↔ Miguel (accepted)", 'ok');

    // Rafael ↔ Miguel (accepted)
    $db->prepare("INSERT INTO connections (requester_id, receiver_id, status, message) VALUES (?, ?, 'accepted', ?)")->execute([
        $rafaelId, $miguelId, 'Hey Miguel, fellow data enthusiast here but in agriculture. Let\'s share ideas!'
    ]);
    out("Connection: Rafael ↔ Miguel (accepted)", 'ok');

    // Isabella → Carlo (pending — for demo of pending request UI)
    $db->prepare("INSERT INTO connections (requester_id, receiver_id, status, message) VALUES (?, ?, 'pending', ?)")->execute([
        $isabellaId, $carloId, 'Hi Carlo! I\'m looking to transition into web development. Would love your advice.'
    ]);
    out("Connection: Isabella → Carlo (pending)", 'ok');

    // ── Messages ─────────────────────────────────────────────
    // Carlo ↔ Sofia conversation
    $msgs = [
        [$carloId, $sofiaId, 'Hey Sofia! Thanks for accepting my connection. I really loved your portfolio designs.', '-45 minutes'],
        [$sofiaId, $carloId, 'Thanks Carlo! I\'m currently redesigning a dashboard for a fintech client. Are you familiar with React chart libraries?', '-42 minutes'],
        [$carloId, $sofiaId, 'Absolutely! I\'ve used Recharts and Chart.js with React. Recharts is super easy to customize.', '-38 minutes'],
        [$sofiaId, $carloId, 'That\'s great to know. Maybe we can collaborate sometime — I do the design, you do the code? 😄', '-35 minutes'],
        [$carloId, $sofiaId, 'I\'d love that! Let me know whenever you have a project in mind.', '-30 minutes'],
    ];
    foreach ($msgs as $m) {
        $db->prepare("INSERT INTO messages (sender_id, receiver_id, content, is_read, sent_at) VALUES (?, ?, ?, 1, DATE_ADD(NOW(), INTERVAL ? MINUTE))")
           ->execute([$m[0], $m[1], $m[2], (int) $m[3]]);
    }
    out("Messages: Carlo ↔ Sofia (5 messages)", 'ok');

    // Miguel ↔ Rafael conversation
    $msgs2 = [
        [$rafaelId, $miguelId, 'Hi Miguel! I saw you\'re into data science. I work with agricultural data — maybe we can find some overlap.', '-120 minutes'],
        [$miguelId, $rafaelId, 'Hey Rafael! That sounds interesting. I\'ve been wanting to apply ML to environmental datasets. What kind of data do you work with?', '-115 minutes'],
        [$rafaelId, $miguelId, 'Mostly GIS crop data, satellite imagery, and IoT sensor readings from rice paddies. We use Python for everything.', '-110 minutes'],
        [$miguelId, $rafaelId, 'That\'s awesome. I built a time-series prediction model for my thesis — could definitely apply it to crop yield forecasting.', '-105 minutes'],
        [$rafaelId, $miguelId, 'We should definitely try that. I noticed GreenField is hiring a data scientist — you should check it out!', '-100 minutes'],
        [$miguelId, $rafaelId, 'Just saw it. The Agri-Data Scientist role looks perfect. Thanks for the heads up! 🙌', '-95 minutes'],
    ];
    foreach ($msgs2 as $m) {
        $db->prepare("INSERT INTO messages (sender_id, receiver_id, content, is_read, sent_at) VALUES (?, ?, ?, 1, DATE_ADD(NOW(), INTERVAL ? MINUTE))")
           ->execute([$m[0], $m[1], $m[2], (int) $m[3]]);
    }
    out("Messages: Miguel ↔ Rafael (6 messages)", 'ok');

    // Sofia → Miguel (short, with unread)
    $db->prepare("INSERT INTO messages (sender_id, receiver_id, content, is_read, sent_at) VALUES (?, ?, ?, 0, DATE_ADD(NOW(), INTERVAL -10 MINUTE))")
       ->execute([$sofiaId, $miguelId, 'Hey Miguel! I\'m working on a data dashboard UI — can I pick your brain about what metrics matter most? 📊']);
    out("Messages: Sofia → Miguel (1 unread message)", 'ok');


    // ══════════════════════════════════════════════════════════
    // 5. JOB APPLICATIONS
    // ══════════════════════════════════════════════════════════
    sectionHeader('5 · Job Applications');

    // Carlo applied to Full-Stack Developer (shortlisted)
    $db->prepare("INSERT INTO job_applications (job_id, user_id, cover_letter, status) VALUES (?, ?, ?, 'shortlisted')")->execute([
        $job1, $carloId, "Dear Hiring Manager,\n\nI am writing to express my interest in the Full-Stack Web Developer position at TechVault Philippines. With 3 years of experience in PHP, Laravel, and React, I am confident I can contribute to your engineering team.\n\nI have built several production web applications and am passionate about writing clean, maintainable code. I look forward to discussing how my skills align with your team's needs.\n\nBest regards,\nCarlo Mendoza"
    ]);
    out("Carlo → Full-Stack Developer @ TechVault (shortlisted)", 'ok');

    // Sofia applied to UI/UX Designer (reviewing)
    $db->prepare("INSERT INTO job_applications (job_id, user_id, cover_letter, status) VALUES (?, ?, ?, 'reviewing')")->execute([
        $job2, $sofiaId, "Dear TechVault Team,\n\nI am excited to apply for the UI/UX Designer position. With my background in multimedia arts and hands-on experience at PixelCraft Studios, I have developed a strong foundation in user-centered design.\n\nMy portfolio includes enterprise dashboards, e-commerce interfaces, and mobile apps. I am passionate about creating delightful user experiences.\n\nLooking forward to your response.\n\nWarm regards,\nSofia Garcia"
    ]);
    out("Sofia → UI/UX Designer @ TechVault (reviewing)", 'ok');

    // Miguel applied to Data Analyst (pending)
    $db->prepare("INSERT INTO job_applications (job_id, user_id, cover_letter, status) VALUES (?, ?, ?, 'pending')")->execute([
        $job3, $miguelId, "Dear Hiring Team,\n\nI am applying for the Data Analyst position at TechVault Philippines. As a recent Applied Mathematics graduate with a focus on data science, I bring strong skills in Python, SQL, and statistical analysis.\n\nI am eager to apply my academic knowledge to real business problems and grow as a data professional.\n\nThank you for considering my application.\n\nSincerely,\nMiguel Torres"
    ]);
    out("Miguel → Data Analyst @ TechVault (pending)", 'ok');

    // Miguel also applied to Agri-Data Scientist (pending)
    $db->prepare("INSERT INTO job_applications (job_id, user_id, cover_letter, status) VALUES (?, ?, ?, 'pending')")->execute([
        $job5, $miguelId, "Dear GreenField Agritech Team,\n\nI recently learned about the Agri-Data Scientist role from a connection and I am very interested. My thesis involved time-series prediction models which I believe could be applied to crop yield forecasting.\n\nI am passionate about using data science for social impact, and sustainable agriculture is an area I'd love to contribute to.\n\nBest,\nMiguel Torres"
    ]);
    out("Miguel → Agri-Data Scientist @ GreenField (pending)", 'ok');

    // Rafael applied to Farm Systems Engineer (reviewing)
    $db->prepare("INSERT INTO job_applications (job_id, user_id, cover_letter, status) VALUES (?, ?, ?, 'reviewing')")->execute([
        $job4, $rafaelId, "Dear Mr. Reyes,\n\nI am writing to apply for the Farm Systems Engineer position at GreenField Agritech. With my background in agricultural engineering from UPLB and 2 years of experience at PhilRice, I have hands-on expertise in GIS mapping, drone operations, and precision agriculture.\n\nI am excited about the opportunity to build IoT-based farming solutions for Filipino farmers.\n\nRespectfully,\nRafael Lim"
    ]);
    out("Rafael → Farm Systems Engineer @ GreenField (reviewing)", 'ok');

    // Isabella applied to IT Support Lead (pending)
    $db->prepare("INSERT INTO job_applications (job_id, user_id, cover_letter, status) VALUES (?, ?, ?, 'pending')")->execute([
        $job8, $isabellaId, "Dear MediCare Solutions Team,\n\nI am interested in the IT Support Lead position. With 4 years of experience managing network infrastructure and cybersecurity operations for SME clients, I am ready to take on a leadership role in healthcare IT.\n\nI am based in Cebu City and available to start immediately.\n\nThank you,\nIsabella Ramos"
    ]);
    out("Isabella → IT Support Lead @ MediCare (pending)", 'ok');


    // ══════════════════════════════════════════════════════════
    // COMMIT
    // ══════════════════════════════════════════════════════════
    $db->commit();

    $elapsed = round((microtime(true) - $startTime) * 1000);

    // ── Summary ──────────────────────────────────────────────
    echo '<div class="summary">';
    echo '<h2>🎉 Demo Data Seeded Successfully!</h2>';
    echo "<p style='color:#a0a0c0;font-size:13px;'>Completed in {$elapsed}ms</p>";

    echo '<h3 style="color:#e0e0e0;font-size:15px;margin-top:16px;">Login Credentials</h3>';
    echo '<p style="color:#a0a0c0;font-size:13px;">All accounts use password: <code>Demo@12345</code></p>';
    echo '<table>';
    echo '<tr><th>Role</th><th>Email</th><th>Name</th></tr>';
    $accounts = [
        ['Employer', 'demo_maria@konekt.test', 'Maria Santos'],
        ['Employer', 'demo_james@konekt.test', 'James Reyes'],
        ['Employer', 'demo_anna@konekt.test', 'Anna Cruz'],
        ['Job Seeker', 'demo_carlo@konekt.test', 'Carlo Mendoza'],
        ['Job Seeker', 'demo_sofia@konekt.test', 'Sofia Garcia'],
        ['Job Seeker', 'demo_miguel@konekt.test', 'Miguel Torres'],
        ['Job Seeker', 'demo_isabella@konekt.test', 'Isabella Ramos'],
        ['Job Seeker', 'demo_rafael@konekt.test', 'Rafael Lim'],
    ];
    foreach ($accounts as $a) {
        echo "<tr><td>{$a[0]}</td><td><code>{$a[1]}</code></td><td>{$a[2]}</td></tr>";
    }
    echo '</table>';

    echo '<p style="margin-top:16px;">';
    echo '<a href="login.php">🔑 Go to Login</a> &nbsp;|&nbsp; ';
    echo '<a href="find_jobs.php">💼 Browse Jobs</a> &nbsp;|&nbsp; ';
    echo '<a href="remove_demo.php">🗑️ Remove Demo Data</a>';
    echo '</p>';
    echo '</div>';

} catch (Throwable $e) {
    $db->rollBack();
    out('Seeding failed: ' . htmlspecialchars($e->getMessage()), 'err');
    echo '<pre style="color:#ff6b6b;font-size:12px;overflow:auto;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}

echo '</body></html>';
