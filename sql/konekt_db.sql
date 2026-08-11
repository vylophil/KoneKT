-- ============================================================
-- KONEKT — Online Job Portal Management System
-- Database Schema
-- ============================================================
-- Server:    127.0.0.1 (Laragon / MySQL)
-- Database:  konekt_db
-- ============================================================

CREATE DATABASE IF NOT EXISTS konekt_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE konekt_db;

-- ============================================================
-- 1. USERS — Core authentication & role table
-- ============================================================
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('job_seeker', 'employer') NOT NULL,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_users_email (email),
    INDEX idx_users_role (role)
) ENGINE=InnoDB;

-- ============================================================
-- 2. PROFILES — Extended user information (LinkedIn-style)
-- ============================================================
CREATE TABLE profiles (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNIQUE NOT NULL,
    headline            VARCHAR(255),
    bio                 TEXT,
    location            VARCHAR(255),
    phone               VARCHAR(20),
    website             VARCHAR(500),
    avatar_url          VARCHAR(500),
    resume_url          VARCHAR(500),
    linkedin_url        VARCHAR(500),
    github_url          VARCHAR(500),
    industry            VARCHAR(255),
    years_of_experience INT DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 3. SKILLS — Master skills catalog
-- ============================================================
CREATE TABLE skills (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) UNIQUE NOT NULL,
    category    VARCHAR(100),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_skills_name (name),
    INDEX idx_skills_category (category)
) ENGINE=InnoDB;

-- ============================================================
-- 4. USER_SKILLS — Skills a user possesses (many-to-many)
-- ============================================================
CREATE TABLE user_skills (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    skill_id            INT NOT NULL,
    proficiency_level   ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'beginner',
    endorsement_count   INT DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_user_skill (user_id, skill_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 5. EXPERIENCE — Work history entries
-- ============================================================
CREATE TABLE experience (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    company_name    VARCHAR(255) NOT NULL,
    job_title       VARCHAR(255) NOT NULL,
    location        VARCHAR(255),
    start_date      DATE NOT NULL,
    end_date        DATE,
    is_current      TINYINT(1) DEFAULT 0,
    description     TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_experience_user (user_id)
) ENGINE=InnoDB;

-- ============================================================
-- 6. EDUCATION — Education entries
-- ============================================================
CREATE TABLE education (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    institution     VARCHAR(255) NOT NULL,
    degree          ENUM('high_school', 'associate', 'bachelors', 'masters', 'doctorate', 'certification', 'other') NOT NULL,
    field_of_study  VARCHAR(255),
    start_date      DATE NOT NULL,
    end_date        DATE,
    is_current      TINYINT(1) DEFAULT 0,
    grade           VARCHAR(50),
    description     TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_education_user (user_id)
) ENGINE=InnoDB;

-- ============================================================
-- 7. COMPANIES — Employer company profiles
-- ============================================================
CREATE TABLE companies (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    name            VARCHAR(255) NOT NULL,
    description     TEXT,
    industry        VARCHAR(255),
    website         VARCHAR(500),
    logo_url        VARCHAR(500),
    location        VARCHAR(255),
    company_size    VARCHAR(50),
    founded_year    INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_companies_user (user_id)
) ENGINE=InnoDB;

-- ============================================================
-- 8. JOB_POSTINGS — Job listings created by employers
-- ============================================================
CREATE TABLE job_postings (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    company_id              INT NOT NULL,
    employer_id             INT NOT NULL,
    title                   VARCHAR(255) NOT NULL,
    description             TEXT NOT NULL,
    requirements            TEXT,
    responsibilities        TEXT,
    location                VARCHAR(255),
    job_type                ENUM('full_time', 'part_time', 'contract', 'internship', 'freelance') NOT NULL,
    work_arrangement        ENUM('on_site', 'remote', 'hybrid') DEFAULT 'on_site',
    salary_min              DECIMAL(12,2),
    salary_max              DECIMAL(12,2),
    salary_currency         VARCHAR(3) DEFAULT 'PHP',
    experience_level        ENUM('entry', 'mid', 'senior', 'executive') DEFAULT 'entry',
    min_experience_years    INT DEFAULT 0,
    education_requirement   ENUM('none', 'high_school', 'associate', 'bachelors', 'masters', 'doctorate') DEFAULT 'none',
    is_active               TINYINT(1) DEFAULT 1,
    deadline                DATE,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_jobs_company (company_id),
    INDEX idx_jobs_employer (employer_id),
    INDEX idx_jobs_active (is_active),
    INDEX idx_jobs_type (job_type),
    INDEX idx_jobs_arrangement (work_arrangement)
) ENGINE=InnoDB;

-- ============================================================
-- 9. JOB_SKILLS — Skills required for a job (many-to-many)
-- ============================================================
CREATE TABLE job_skills (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    job_id      INT NOT NULL,
    skill_id    INT NOT NULL,
    importance  ENUM('required', 'preferred', 'nice_to_have') DEFAULT 'required',

    UNIQUE KEY uq_job_skill (job_id, skill_id),
    FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 10. JOB_APPLICATIONS — Applications from job seekers
-- ============================================================
CREATE TABLE job_applications (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    job_id          INT NOT NULL,
    user_id         INT NOT NULL,
    cover_letter    TEXT,
    resume_url      VARCHAR(500),
    status          ENUM('pending', 'reviewing', 'shortlisted', 'interview', 'offered', 'accepted', 'rejected', 'withdrawn') DEFAULT 'pending',
    applied_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_application (job_id, user_id),
    FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_applications_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 11. SAVED_JOBS — Bookmarked jobs per user
-- ============================================================
CREATE TABLE saved_jobs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    job_id      INT NOT NULL,
    saved_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_saved_job (user_id, job_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 12. JOB_MATCHES — Computed match scores (matchmaking engine)
-- ============================================================
CREATE TABLE job_matches (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    job_id              INT NOT NULL,
    match_score         DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    skill_score         DECIMAL(5,2) DEFAULT 0.00,
    experience_score    DECIMAL(5,2) DEFAULT 0.00,
    education_score     DECIMAL(5,2) DEFAULT 0.00,
    computed_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_match (user_id, job_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE,
    INDEX idx_match_score (match_score DESC),
    INDEX idx_match_user (user_id, match_score DESC),
    INDEX idx_match_job (job_id, match_score DESC)
) ENGINE=InnoDB;

-- ============================================================
-- 13. CONNECTIONS — Networking connections (LinkedIn-style)
-- ============================================================
CREATE TABLE connections (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    requester_id    INT NOT NULL,
    receiver_id     INT NOT NULL,
    status          ENUM('pending', 'accepted', 'rejected', 'blocked') DEFAULT 'pending',
    message         TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_connection (requester_id, receiver_id),
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_conn_receiver (receiver_id, status),
    INDEX idx_conn_requester (requester_id, status)
) ENGINE=InnoDB;

-- ============================================================
-- 14. MESSAGES — Direct messages between connected users
-- ============================================================
CREATE TABLE messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT NOT NULL,
    receiver_id INT NOT NULL,
    content     TEXT NOT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    sent_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_msg_conversation (sender_id, receiver_id, sent_at),
    INDEX idx_msg_receiver (receiver_id, is_read)
) ENGINE=InnoDB;

-- ============================================================
-- 15. ENDORSEMENTS — Skill endorsements from connections
-- ============================================================
CREATE TABLE endorsements (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    endorser_id         INT NOT NULL,
    endorsed_user_id    INT NOT NULL,
    skill_id            INT NOT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_endorsement (endorser_id, endorsed_user_id, skill_id),
    FOREIGN KEY (endorser_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (endorsed_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 16. PROFILE_VIEWS — Track who viewed whose profile
-- ============================================================
CREATE TABLE profile_views (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    viewer_id   INT NOT NULL,
    viewed_id   INT NOT NULL,
    viewed_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (viewed_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_views_viewed (viewed_id, viewed_at DESC),
    INDEX idx_views_viewer (viewer_id, viewed_at DESC)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA — Common skills catalog
-- ============================================================
INSERT INTO skills (name, category) VALUES
    -- Programming Languages
    ('PHP', 'Programming'),
    ('JavaScript', 'Programming'),
    ('Python', 'Programming'),
    ('Java', 'Programming'),
    ('C#', 'Programming'),
    ('C++', 'Programming'),
    ('TypeScript', 'Programming'),
    ('Ruby', 'Programming'),
    ('Swift', 'Programming'),
    ('Kotlin', 'Programming'),
    ('Go', 'Programming'),
    ('Rust', 'Programming'),
    ('SQL', 'Programming'),
    ('R', 'Programming'),

    -- Web Development
    ('HTML/CSS', 'Web Development'),
    ('React', 'Web Development'),
    ('Angular', 'Web Development'),
    ('Vue.js', 'Web Development'),
    ('Node.js', 'Web Development'),
    ('Laravel', 'Web Development'),
    ('Django', 'Web Development'),
    ('Spring Boot', 'Web Development'),
    ('ASP.NET', 'Web Development'),
    ('WordPress', 'Web Development'),
    ('REST APIs', 'Web Development'),
    ('GraphQL', 'Web Development'),

    -- Databases
    ('MySQL', 'Databases'),
    ('PostgreSQL', 'Databases'),
    ('MongoDB', 'Databases'),
    ('Redis', 'Databases'),
    ('Oracle', 'Databases'),
    ('SQL Server', 'Databases'),

    -- Cloud & DevOps
    ('AWS', 'Cloud & DevOps'),
    ('Azure', 'Cloud & DevOps'),
    ('Google Cloud', 'Cloud & DevOps'),
    ('Docker', 'Cloud & DevOps'),
    ('Kubernetes', 'Cloud & DevOps'),
    ('CI/CD', 'Cloud & DevOps'),
    ('Git', 'Cloud & DevOps'),
    ('Linux', 'Cloud & DevOps'),

    -- Data & AI
    ('Machine Learning', 'Data & AI'),
    ('Data Analysis', 'Data & AI'),
    ('TensorFlow', 'Data & AI'),
    ('Data Visualization', 'Data & AI'),
    ('NLP', 'Data & AI'),
    ('Big Data', 'Data & AI'),

    -- Design
    ('UI/UX Design', 'Design'),
    ('Figma', 'Design'),
    ('Adobe Photoshop', 'Design'),
    ('Adobe Illustrator', 'Design'),
    ('Graphic Design', 'Design'),

    -- Business & Management
    ('Project Management', 'Business'),
    ('Agile/Scrum', 'Business'),
    ('Business Analysis', 'Business'),
    ('Marketing', 'Business'),
    ('Sales', 'Business'),
    ('Communication', 'Business'),
    ('Leadership', 'Business'),
    ('Problem Solving', 'Business'),
    ('Teamwork', 'Business'),
    ('Critical Thinking', 'Business'),

    -- Other Technical
    ('Cybersecurity', 'Technical'),
    ('Networking', 'Technical'),
    ('Mobile Development', 'Technical'),
    ('Blockchain', 'Technical'),
    ('IoT', 'Technical'),
    ('Embedded Systems', 'Technical'),
    ('Quality Assurance', 'Technical'),
    ('Technical Writing', 'Technical');
