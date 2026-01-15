-- MBELMS database (simple)
CREATE DATABASE IF NOT EXISTS mbelms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE mbelms_db;

-- USERS (login + role + security questions for password reset)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'user') NOT NULL DEFAULT 'user',
    security_question ENUM(
        'favorite_color',
        'birth_city',
        'first_pet'
    ) NULL DEFAULT NULL,
    security_answer_hash VARCHAR(255) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- LOGIN ATTEMPTS (brute force protection)
CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    ip VARBINARY(16) NOT NULL,
    first_attempt_at DATETIME NOT NULL,
    last_attempt_at DATETIME NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_username_ip (username, ip),
    KEY idx_locked_until (locked_until),
    KEY idx_last_attempt_at (last_attempt_at)
) ENGINE = InnoDB;

-- MACHINES (sensitive operational data)
CREATE TABLE IF NOT EXISTS machines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    location VARCHAR(100) NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- EQUIPMENT (sensitive operational data)
CREATE TABLE IF NOT EXISTS equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    serial_no VARCHAR(100) NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- MACHINE BOOKINGS (with time slot support)
CREATE TABLE IF NOT EXISTS machine_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
    start_time DATETIME NULL,
    end_time DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (machine_id) REFERENCES machines (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX (machine_id),
    INDEX (user_id),
    INDEX (status),
    INDEX idx_time_slot (
        machine_id,
        start_time,
        end_time
    )
) ENGINE = InnoDB;

-- EQUIPMENT LOANS (one active loan per equipment)
CREATE TABLE IF NOT EXISTS equipment_loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('active', 'returned') NOT NULL DEFAULT 'active',
    borrowed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    returned_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (equipment_id) REFERENCES equipment (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX (equipment_id),
    INDEX (user_id),
    INDEX (status)
) ENGINE = InnoDB;

-- AUDIT LOGS (accountability)
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity VARCHAR(50) NULL,
    entity_id INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX (user_id),
    INDEX (action),
    INDEX (created_at)
) ENGINE = InnoDB;

-- Seed data

INSERT INTO
    machines (name, location, description)
VALUES (
        'CNC Mill A',
        'AMC Lab 1',
        '3-axis CNC milling machine'
    ),
    (
        '3D Printer B',
        'AMC Lab 2',
        'FDM printer'
    ),
    (
        'Laser Cutter C',
        'AMC Lab 3',
        'CO2 laser cutter'
    );

INSERT INTO
    equipment (name, serial_no, description)
VALUES (
        'Calipers',
        'EQ-1001',
        'Digital calipers'
    ),
    (
        'Soldering Iron',
        'EQ-2002',
        'Temperature controlled'
    ),
    (
        'Power Drill',
        'EQ-3003',
        'Cordless drill'
    );