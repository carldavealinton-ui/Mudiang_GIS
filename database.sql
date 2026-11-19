-- Select the target database before importing this schema.


-- Users table (admins, staff, residents)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    contact VARCHAR(50),
    address VARCHAR(255),
    role ENUM('admin', 'staff', 'resident') DEFAULT 'resident',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Complaints table (GIS‑based complaints)
CREATE TABLE complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    complaint_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    address VARCHAR(255) NOT NULL,
    complainant_name VARCHAR(100) NOT NULL,
    complainant_contact VARCHAR(50) NOT NULL,
    status ENUM('filed', 'acknowledged', 'processing', 'resolved') DEFAULT 'filed',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    assigned_to VARCHAR(100),
    resolution_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);


-- Insert default admin user (password: "password" hashed)
INSERT INTO users (username, password, full_name, email, role)
VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Administrator',
    'admin@barangay.gov',
    'admin'
);



-- ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
-- Safe migration / upgrade block
-- ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

-- Add missing columns to users (if they don't exist)
ALTER TABLE users
ADD COLUMN IF NOT EXISTS full_name VARCHAR(100) NOT NULL DEFAULT 'User' AFTER password,
ADD COLUMN IF NOT EXISTS email VARCHAR(100) AFTER full_name,
ADD COLUMN IF NOT EXISTS contact VARCHAR(50) AFTER email,
ADD COLUMN IF NOT EXISTS address VARCHAR(255) AFTER contact;

-- Extend role enum to include 'resident'
ALTER TABLE users
MODIFY COLUMN role ENUM('admin', 'staff', 'resident') DEFAULT 'resident';

-- Update existing admin record if full_name is missing or still default
UPDATE users
SET full_name = 'Administrator'
WHERE username = 'admin'
  AND (full_name IS NULL OR full_name = 'User');


-- Add user_id column to complaints (if not yet exists)
ALTER TABLE complaints
ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER id;

-- Re‑create foreign key constraint safely
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE complaints
DROP FOREIGN KEY IF EXISTS complaints_ibfk_1;

ALTER TABLE complaints
ADD CONSTRAINT complaints_ibfk_1
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;


-- Optional: add indexes for performance
ALTER TABLE complaints ADD INDEX IF NOT EXISTS idx_user_id (user_id);
ALTER TABLE complaints ADD INDEX IF NOT EXISTS idx_status (status);
ALTER TABLE complaints ADD INDEX IF NOT EXISTS idx_priority (priority);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_username (username);

-- Email verification columns
ALTER TABLE users
ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER email,
ADD COLUMN IF NOT EXISTS verification_token VARCHAR(64) NULL AFTER email_verified;

-- Mark existing admin as already verified
UPDATE users SET email_verified = 1 WHERE role = 'admin';

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    complaint_id INT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
);
ALTER TABLE notifications ADD INDEX IF NOT EXISTS idx_notif_user (user_id, is_read);
-- Evidence attachments (safe upgrade)
CREATE TABLE IF NOT EXISTS complaint_evidence (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complaint_id INT NOT NULL,
    path VARCHAR(255) NOT NULL UNIQUE,
    original_name VARCHAR(200) NOT NULL,
    mime_type VARCHAR(80) NOT NULL,
    size INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_evidence_complaint (complaint_id),
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
);

-- Complaint conversations and message notifications
CREATE TABLE IF NOT EXISTS complaint_messages (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    complaint_id INT NOT NULL,
    user_id INT NOT NULL,
    body TEXT NOT NULL,
    client_token VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_sender_token (user_id, client_token),
    INDEX idx_complaint_messages (complaint_id, id),
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS complaint_message_reads (
    complaint_id INT NOT NULL,
    user_id INT NOT NULL,
    last_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (complaint_id, user_id),
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS event_type VARCHAR(20) NOT NULL DEFAULT 'complaint';
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS complaint_message_id BIGINT UNSIGNED NULL;
ALTER TABLE complaint_messages ADD COLUMN IF NOT EXISTS client_token VARCHAR(64) NULL;
ALTER TABLE complaint_messages ADD UNIQUE INDEX IF NOT EXISTS idx_sender_token (user_id, client_token);
ALTER TABLE complaint_messages ADD INDEX IF NOT EXISTS idx_complaint_messages (complaint_id, id);

-- Report workflow and attributed history
ALTER TABLE complaints MODIFY status ENUM('pending','in_progress','filed','acknowledged','processing','resolved') NOT NULL DEFAULT 'filed';
UPDATE complaints SET status='filed' WHERE status='pending';
UPDATE complaints SET status='processing' WHERE status='in_progress';
ALTER TABLE complaints MODIFY status ENUM('filed','acknowledged','processing','resolved') NOT NULL DEFAULT 'filed';
CREATE TABLE IF NOT EXISTS complaint_activity (
 id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
 complaint_id INT NOT NULL,
 actor_id INT NULL,
 actor_name VARCHAR(200) NOT NULL,
 event_type VARCHAR(30) NOT NULL,
 summary TEXT NOT NULL,
 source_message_id BIGINT UNSIGNED NULL UNIQUE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_report_activity (complaint_id,id),
 FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
 FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
);
-- Preserve attribution and original dates for earlier staff messages.
INSERT INTO complaint_activity (complaint_id,actor_id,actor_name,event_type,summary,source_message_id,created_at)
SELECT m.complaint_id,u.id,CONCAT(u.full_name,' (@',u.username,')'),'message',CONCAT('Sent a message: ',LEFT(m.body,300)),m.id,m.created_at
FROM complaint_messages m JOIN users u ON u.id=m.user_id
WHERE u.role IN ('admin','staff') AND NOT EXISTS (SELECT 1 FROM complaint_activity a WHERE a.source_message_id=m.id);

ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS session_version INT NOT NULL DEFAULT 0;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS assigned_staff_id INT NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS due_date DATE NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS acknowledged_at DATETIME NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS resolved_at DATETIME NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS resident_confirmed_at DATETIME NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS reopen_count INT NOT NULL DEFAULT 0;
ALTER TABLE complaint_evidence ADD COLUMN IF NOT EXISTS purpose VARCHAR(20) NOT NULL DEFAULT 'submission';
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS event_key VARCHAR(100) NULL;
CREATE UNIQUE INDEX IF NOT EXISTS idx_notification_event ON notifications(user_id,event_key);
CREATE TABLE IF NOT EXISTS login_attempts (
 attempt_key CHAR(64) PRIMARY KEY,
 failures INT NOT NULL DEFAULT 0,
 blocked_until DATETIME NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS account_activity (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_id INT NULL,
 target_id INT NULL,
 actor_name VARCHAR(200) NOT NULL,
 target_name VARCHAR(200) NOT NULL,
 summary VARCHAR(255) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_complaint_owner_due ON complaints(assigned_staff_id, status, due_date);

-- Resident features: drafts, supporters, announcements, and password resets
CREATE TABLE IF NOT EXISTS resident_drafts (
 user_id INT NOT NULL PRIMARY KEY,
 payload MEDIUMTEXT NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_draft_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS complaint_supporters (
 complaint_id INT NOT NULL,
 user_id INT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (complaint_id,user_id),
 KEY idx_supporter_user (user_id),
 CONSTRAINT fk_support_report FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
 CONSTRAINT fk_support_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS community_announcements (
 id INT AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(150) NOT NULL,
 body TEXT NOT NULL,
 category VARCHAR(30) NOT NULL DEFAULT 'General',
 published_at DATETIME NOT NULL,
 expires_at DATETIME NULL,
 is_published TINYINT(1) NOT NULL DEFAULT 0,
 created_by INT NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_announcements_live (is_published,published_at,expires_at),
 CONSTRAINT fk_announcement_author FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS password_reset_tokens (
 user_id INT NOT NULL PRIMARY KEY,
 token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS password_reset_limits (
 bucket CHAR(64) NOT NULL PRIMARY KEY,
 attempts INT NOT NULL DEFAULT 0,
 window_started DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Allow notifications without a complaint (including announcements)
ALTER TABLE notifications MODIFY complaint_id INT NULL;
