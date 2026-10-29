-- StudentHub database (Practical 8)
-- Import via phpMyAdmin > Import, or:  mysql -u root < sql/studenthub.sql
-- Test logins:  admin@studenthub.com / Admin@123   |   student@studenthub.com / Student@123

CREATE DATABASE IF NOT EXISTS studenthub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studenthub;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- Login accounts (students and admins)
CREATE TABLE users (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name         VARCHAR(60)  NOT NULL,
    email             VARCHAR(100) NOT NULL UNIQUE,
    mobile            CHAR(10)     NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    course            VARCHAR(40)  NULL,
    year              VARCHAR(20)  NULL,
    gender            ENUM('Male','Female','Other') NULL,
    role              ENUM('student','admin') NOT NULL DEFAULT 'student',
    status            ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login        DATETIME NULL,
    remember_selector VARCHAR(18) NULL,
    remember_hash     CHAR(64)    NULL,
    remember_expires  DATETIME    NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_remember_selector (remember_selector)
) ENGINE=InnoDB;

-- Student records (used for CRUD in Practical 11)
CREATE TABLE students (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NULL UNIQUE,
    enrollment_no VARCHAR(15)  NOT NULL UNIQUE,
    name          VARCHAR(60)  NOT NULL,
    email         VARCHAR(100) NOT NULL,
    course        VARCHAR(40)  NOT NULL,
    year          VARCHAR(20)  NOT NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Events (used for CRUD in Practical 12)
CREATE TABLE events (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(100) NOT NULL,
    description TEXT NULL,
    event_date  DATE NOT NULL,
    venue       VARCHAR(100) NOT NULL,
    category    VARCHAR(30)  NOT NULL,
    poster      VARCHAR(255) NULL,
    status      ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_by  INT UNSIGNED NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Which student registered for which event (many-to-many)
CREATE TABLE registrations (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id    INT UNSIGNED NOT NULL,
    event_id      INT UNSIGNED NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_event (student_id, event_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id)   REFERENCES events(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- Important actions (registration, login, access denied, ...)
CREATE TABLE audit_logs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(40)  NOT NULL,
    details    VARCHAR(255) NULL,
    ip_address VARCHAR(45)  NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- Seed data ----------
INSERT INTO users (full_name, email, mobile, password_hash, course, year, gender, role, status, created_at) VALUES
('Admin User',     'admin@studenthub.com',   '9876500001', '$2y$10$H9Do.FP42Gq3eQoNpAkkxOxxpJpuJY2o.5NoYqRS0Q88QJp9c6KXi',   NULL,        NULL,       'Other',  'admin',   'active',   DATE_SUB(NOW(), INTERVAL 200 DAY)),
('Devanshi Patel', 'student@studenthub.com', '9876500002', '$2y$10$ZlQN28C3bY321G.HbjeyGOn/APkFCA08tCcC9JE4PAwH2ROgyOOum', 'BTech CSE', '2nd Year', 'Female', 'student', 'active',   DATE_SUB(NOW(), INTERVAL 150 DAY)),
('Aarav Shah',     'aarav.shah@example.com', '9876500003', '$2y$10$ZlQN28C3bY321G.HbjeyGOn/APkFCA08tCcC9JE4PAwH2ROgyOOum', 'BTech CSE', '2nd Year', 'Male',   'student', 'active',   DATE_SUB(NOW(), INTERVAL 95 DAY)),
('Riya Mehta',     'riya.mehta@example.com', '9876500004', '$2y$10$ZlQN28C3bY321G.HbjeyGOn/APkFCA08tCcC9JE4PAwH2ROgyOOum', 'BTech IT',  '2nd Year', 'Female', 'student', 'active',   DATE_SUB(NOW(), INTERVAL 40 DAY)),
('Kabir Joshi',    'kabir.joshi@example.com','9876500005', '$2y$10$ZlQN28C3bY321G.HbjeyGOn/APkFCA08tCcC9JE4PAwH2ROgyOOum', 'BTech Electronics', '2nd Year', 'Male', 'student', 'inactive', DATE_SUB(NOW(), INTERVAL 12 DAY)),
('Isha Parmar',    'isha.parmar@example.com','9876500006', '$2y$10$ZlQN28C3bY321G.HbjeyGOn/APkFCA08tCcC9JE4PAwH2ROgyOOum', 'BTech CSE', '1st Year', 'Female', 'student', 'active',   DATE_SUB(NOW(), INTERVAL 3 DAY));

INSERT INTO students (user_id, enrollment_no, name, email, course, year, status) VALUES
(2,    '23CS001', 'Devanshi Patel', 'student@studenthub.com',     'BTech CSE',         '2nd Year', 'active'),
(3,    '23CS002', 'Aarav Shah',     'aarav.shah@example.com',     'BTech CSE',         '2nd Year', 'active'),
(4,    '23IT003', 'Riya Mehta',     'riya.mehta@example.com',     'BTech IT',          '2nd Year', 'active'),
(5,    '23EC004', 'Kabir Joshi',    'kabir.joshi@example.com',    'BTech Electronics', '2nd Year', 'active'),
(NULL, '22CS005', 'Ananya Desai',   'ananya.desai@example.com',   'BTech CSE',         '3rd Year', 'active'),
(NULL, '22IT006', 'Vivaan Trivedi', 'vivaan.trivedi@example.com', 'BTech IT',          '3rd Year', 'active'),
(6,    '24CS007', 'Isha Parmar',    'isha.parmar@example.com',    'BTech CSE',         '1st Year', 'active'),
(NULL, '24ME008', 'Rohan Bhatt',    'rohan.bhatt@example.com',    'BTech Mechanical',  '1st Year', 'active'),
(NULL, '21CS009', 'Meera Nair',     'meera.nair@example.com',     'BTech CSE',         '4th Year', 'active'),
(NULL, '21EC010', 'Arjun Solanki',  'arjun.solanki@example.com',  'BTech Electronics', '4th Year', 'inactive'),
(NULL, '23IT011', 'Diya Chauhan',   'diya.chauhan@example.com',   'BTech IT',          '2nd Year', 'active'),
(NULL, '22ME012', 'Yash Vyas',      'yash.vyas@example.com',      'BTech Mechanical',  '3rd Year', 'active'),
(NULL, '24IT013', 'Sneha Rana',     'sneha.rana@example.com',     'BTech IT',          '1st Year', 'active'),
(NULL, '23ME014', 'Harsh Pandya',   'harsh.pandya@example.com',   'BTech Mechanical',  '2nd Year', 'active'),
(NULL, '22EC015', 'Pooja Thakkar',  'pooja.thakkar@example.com',  'BTech Electronics', '3rd Year', 'active'),
(NULL, '24CS016', 'Nikhil Rao',     'nikhil.rao@example.com',     'BTech CSE',         '1st Year', 'active'),
(NULL, '23CS017', 'Tanvi Kapoor',   'tanvi.kapoor@example.com',   'BTech CSE',         '2nd Year', 'active'),
(NULL, '21IT018', 'Om Prajapati',   'om.prajapati@example.com',   'BTech IT',          '4th Year', 'inactive'),
(NULL, '22CS019', 'Kavya Iyer',     'kavya.iyer@example.com',     'BTech CSE',         '3rd Year', 'active'),
(NULL, '24EC020', 'Dev Gajjar',     'dev.gajjar@example.com',     'BTech Electronics', '1st Year', 'active'),
(NULL, '23ME021', 'Ritika Sharma',  'ritika.sharma@example.com',  'BTech Mechanical',  '2nd Year', 'active'),
(NULL, '22IT022', 'Jay Modi',       'jay.modi@example.com',       'BTech IT',          '3rd Year', 'active');

INSERT INTO events (title, description, event_date, venue, category, status, created_by) VALUES
('Tech Fest 2026',               'Coding competitions, robotics and innovation showcases.', '2026-11-15', 'Main Auditorium',  'Technical', 'open',   1),
('Career Development Workshop',  'Resume writing and interview techniques.',               '2026-11-18', 'Seminar Hall A',   'Career',    'open',   1),
('Annual Cultural Festival',     'Music, dance, drama and food stalls.',                   '2026-12-05', 'Open Air Theatre', 'Cultural',  'open',   1),
('AI/ML Bootcamp',               'Hands-on introduction to machine learning.',             '2026-11-28', 'Computer Lab 3',   'Workshop',  'open',   1),
('Inter-College Cricket Cup',    'Knockout cricket tournament.',                           '2026-11-22', 'University Ground','Sports',    'open',   1),
('Freshers Welcome 2026',        'Welcome party for first-year students.',                 '2026-09-01', 'Main Auditorium',  'Cultural',  'closed', 1),
('Web Development Workshop',     'HTML, CSS, JavaScript and PHP basics.',                  '2026-12-02', 'Computer Lab 1',   'Workshop',  'open',   1),
('Annual Sports Day',            'Track and field events and team games.',                 '2026-08-10', 'University Ground','Sports',    'closed', 1);

-- Dates are relative to the import day so the dashboard chart always has data
INSERT INTO registrations (student_id, event_id, registered_at) VALUES
(1, 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 4, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(2, 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, 2, DATE_SUB(NOW(), INTERVAL 35 DAY)),
(4, 3, DATE_SUB(NOW(), INTERVAL 45 DAY)),
(5, 4, DATE_SUB(NOW(), INTERVAL 62 DAY)),
(6, 5, DATE_SUB(NOW(), INTERVAL 75 DAY)),
(7, 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(9, 6, DATE_SUB(NOW(), INTERVAL 95 DAY)),
(11, 7, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(12, 8, DATE_SUB(NOW(), INTERVAL 125 DAY)),
(13, 6, DATE_SUB(NOW(), INTERVAL 110 DAY)),
(15, 2, DATE_SUB(NOW(), INTERVAL 28 DAY)),
(16, 4, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(17, 1, DATE_SUB(NOW(), INTERVAL 15 DAY));
