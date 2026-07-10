-- ── 1. RENTAL APPLICATIONS TABLE ─────────────────────────
CREATE TABLE IF NOT EXISTS `rental_applications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reference_code` VARCHAR(50) NOT NULL UNIQUE,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `state` VARCHAR(50) NOT NULL,
    `zip_code` VARCHAR(20) NOT NULL,
    `vehicle` VARCHAR(100) NOT NULL,
    `duration` VARCHAR(50) NOT NULL,
    `insurance_option` VARCHAR(100) NOT NULL,
    `file_license_path` VARCHAR(255) NOT NULL,
    `file_insurance_path` VARCHAR(255) NOT NULL,
    `file_address_path` VARCHAR(255) NOT NULL,
    `file_selfie_path` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Pending',
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. DETAILING QUOTES TABLE ────────────────────────────
CREATE TABLE IF NOT EXISTS `detailing_quotes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reference_code` VARCHAR(50) NOT NULL UNIQUE,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `vehicle_make` VARCHAR(100) NOT NULL,
    `vehicle_model` VARCHAR(100) NOT NULL,
    `service` VARCHAR(100) NOT NULL,
    `appointment_date` DATE DEFAULT NULL,
    `message` TEXT DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Pending',
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. VEHICLE INQUIRIES TABLE ───────────────────────────
CREATE TABLE IF NOT EXISTS `vehicle_inquiries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reference_code` VARCHAR(50) NOT NULL UNIQUE,
    `full_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `vehicle_type` VARCHAR(100) NOT NULL,
    `preferred_brand` VARCHAR(100) NOT NULL,
    `min_budget` VARCHAR(50) NOT NULL,
    `max_budget` VARCHAR(50) NOT NULL,
    `requirements` TEXT DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Pending',
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
