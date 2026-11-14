-- ============================================================
-- GHOREBAIRE FIX - COMPLETE MySQL DATABASE
-- Compatible with XAMPP / MySQL 8.0+
-- Import this file in phpMyAdmin.
-- ============================================================

CREATE DATABASE IF NOT EXISTS ghorebaire_fix
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ghorebaire_fix;

SET FOREIGN_KEY_CHECKS = 0;
DROP TRIGGER IF EXISTS trg_users_after_insert;
DROP TRIGGER IF EXISTS trg_bookings_after_insert;
DROP TRIGGER IF EXISTS trg_bookings_after_status_update;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. USERS: common login/account data for customer, technician and admin
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(25) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer','technician','admin') NOT NULL DEFAULT 'customer',
    street_address VARCHAR(255) DEFAULT NULL,
    area VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    date_of_birth DATE DEFAULT NULL,
    profile_image VARCHAR(500) DEFAULT NULL,
    account_status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    phone_verified TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_status (account_status)
) ENGINE=InnoDB;

-- 2. CUSTOMERS: customer-specific profile data
CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    preferred_contact ENUM('phone','email') NOT NULL DEFAULT 'phone',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. TECHNICIANS: professional profile, verification and availability
CREATE TABLE IF NOT EXISTS technicians (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    professional_title VARCHAR(150) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    experience_years TINYINT UNSIGNED NOT NULL DEFAULT 0,
    service_areas TEXT DEFAULT NULL,
    completed_jobs INT UNSIGNED NOT NULL DEFAULT 0,
    available_status ENUM('available','unavailable','busy') NOT NULL DEFAULT 'unavailable',
    verification_status ENUM('pending','approved','rejected','resubmission_required') NOT NULL DEFAULT 'pending',
    average_rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    total_reviews INT UNSIGNED NOT NULL DEFAULT 0,
    hourly_rate DECIMAL(10,2) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_technicians_user FOREIGN KEY (user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_technician_match (verification_status, available_status, average_rating)
) ENGINE=InnoDB;

-- 4. ADMINS: admin-specific identity and access level
CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    admin_title VARCHAR(100) DEFAULT 'Administrator',
    access_level ENUM('super_admin','admin','support') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admins_user FOREIGN KEY (user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. SERVICE CATEGORIES: Appliances, Electronics, Vehicles, etc.
CREATE TABLE IF NOT EXISTS service_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id INT UNSIGNED DEFAULT NULL,
    category_name VARCHAR(120) NOT NULL,
    description TEXT DEFAULT NULL,
    image_path VARCHAR(500) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) REFERENCES service_categories(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY uq_category_parent_name (parent_id, category_name),
    INDEX idx_category_active (is_active, display_order)
) ENGINE=InnoDB;

-- 6. SERVICES: subcategories/services under each category
CREATE TABLE IF NOT EXISTS services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    service_name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    base_price DECIMAL(10,2) DEFAULT NULL,
    estimated_minutes SMALLINT UNSIGNED DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_services_category FOREIGN KEY (category_id) REFERENCES service_categories(id)
      ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_service_category_name (category_id, service_name),
    INDEX idx_services_active (is_active)
) ENGINE=InnoDB;

-- 7. TECHNICIAN SERVICES: which services a technician can perform
CREATE TABLE IF NOT EXISTS technician_services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    technician_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    experience_years TINYINT UNSIGNED DEFAULT 0,
    service_price DECIMAL(10,2) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tech_services_technician FOREIGN KEY (technician_id) REFERENCES technicians(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_tech_services_service FOREIGN KEY (service_id) REFERENCES services(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_technician_service (technician_id, service_id)
) ENGINE=InnoDB;

-- 8. TECHNICIAN SERVICE AREAS: searchable city/area coverage
CREATE TABLE IF NOT EXISTS technician_service_areas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    technician_id INT UNSIGNED NOT NULL,
    city VARCHAR(100) NOT NULL,
    area VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_tech_areas_technician FOREIGN KEY (technician_id) REFERENCES technicians(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_technician_area (technician_id, city, area),
    INDEX idx_area_search (city, area, is_active)
) ENGINE=InnoDB;

-- 9. SERVICE REQUESTS: customer's repair request before/alongside booking
CREATE TABLE IF NOT EXISTS service_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED DEFAULT NULL,
    service_id INT UNSIGNED DEFAULT NULL,
    category_name VARCHAR(120) NOT NULL,
    subcategory_name VARCHAR(150) DEFAULT NULL,
    title VARCHAR(180) DEFAULT NULL,
    description TEXT NOT NULL,
    address_line VARCHAR(255) NOT NULL,
    area VARCHAR(120) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    preferred_date DATE DEFAULT NULL,
    preferred_time TIME DEFAULT NULL,
    request_status ENUM('new','matching','technician_selected','booked','closed','cancelled') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_requests_customer FOREIGN KEY (customer_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_requests_category FOREIGN KEY (category_id) REFERENCES service_categories(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_requests_service FOREIGN KEY (service_id) REFERENCES services(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_request_customer (customer_id, created_at),
    INDEX idx_request_status (request_status, created_at)
) ENGINE=InnoDB;

-- 10. ATTACHMENTS: optional repair photos/files linked to a request
CREATE TABLE IF NOT EXISTS request_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) DEFAULT NULL,
    file_size BIGINT UNSIGNED DEFAULT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attachments_request FOREIGN KEY (request_id) REFERENCES service_requests(id)
      ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- 11. VERIFICATION DOCUMENTS: private technician documents
CREATE TABLE IF NOT EXISTS verification_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    technician_id INT UNSIGNED NOT NULL,
    document_type ENUM('national_id','trade_license','certificate','experience_proof','other') NOT NULL DEFAULT 'other',
    document_number VARCHAR(100) DEFAULT NULL,
    file_path VARCHAR(500) NOT NULL,
    review_status ENUM('pending','approved','rejected','resubmit') NOT NULL DEFAULT 'pending',
    admin_note TEXT DEFAULT NULL,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_verification_technician FOREIGN KEY (technician_id) REFERENCES technicians(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_verification_admin FOREIGN KEY (reviewed_by) REFERENCES admins(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_verification_status (review_status)
) ENGINE=InnoDB;

-- 12. BOOKINGS: confirmed service engagement; legacy columns retained for current prototype
CREATE TABLE IF NOT EXISTS bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_code VARCHAR(40) DEFAULT NULL UNIQUE,
    request_id BIGINT UNSIGNED DEFAULT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED DEFAULT NULL,
    technician_id INT UNSIGNED DEFAULT NULL,
    category VARCHAR(120) NOT NULL,
    subcategory VARCHAR(150) DEFAULT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255) NOT NULL,
    technician_name VARCHAR(150) NOT NULL DEFAULT 'Not assigned',
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    estimated_price DECIMAL(10,2) DEFAULT NULL,
    final_price DECIMAL(10,2) DEFAULT NULL,
    platform_commission DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('Pending','Requested','Accepted','On the Way','In Progress','Completed','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
    cancellation_reason TEXT DEFAULT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accepted_at DATETIME DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_booking_customer FOREIGN KEY (customer_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_booking_technician FOREIGN KEY (technician_id) REFERENCES technicians(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_booking_request FOREIGN KEY (request_id) REFERENCES service_requests(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_booking_customer (customer_id, created_at),
    INDEX idx_booking_technician (technician_id, status),
    INDEX idx_booking_status_date (status, booking_date)
) ENGINE=InnoDB;

-- 13. BOOKING STATUS HISTORY: records every booking status change
CREATE TABLE IF NOT EXISTS booking_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL,
    changed_by INT UNSIGNED DEFAULT NULL,
    note TEXT DEFAULT NULL,
    change_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_history_booking FOREIGN KEY (booking_id) REFERENCES bookings(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_history_user FOREIGN KEY (changed_by) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_history_booking_time (booking_id, change_time)
) ENGINE=InnoDB;

-- 14. PAYMENTS: payment attempts and results; no card/bank secrets are stored
CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    payment_reference VARCHAR(100) DEFAULT NULL UNIQUE,
    gateway_reference VARCHAR(150) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'BDT',
    payment_method ENUM('cash','bkash','nagad','card','bank_transfer','other') NOT NULL DEFAULT 'cash',
    payment_status ENUM('pending','success','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    gateway_name VARCHAR(100) DEFAULT NULL,
    payment_time DATETIME DEFAULT NULL,
    gateway_response TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id)
      ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_payment_booking (booking_id, created_at),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB;

-- 15. RATINGS AND REVIEWS: one review per completed booking
CREATE TABLE IF NOT EXISTS ratings_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL UNIQUE,
    customer_id INT UNSIGNED NOT NULL,
    technician_id INT UNSIGNED NOT NULL,
    stars TINYINT UNSIGNED NOT NULL,
    comment TEXT DEFAULT NULL,
    review_status ENUM('visible','hidden','reported') NOT NULL DEFAULT 'visible',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_review_booking FOREIGN KEY (booking_id) REFERENCES bookings(id)
      ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_review_customer FOREIGN KEY (customer_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_review_technician FOREIGN KEY (technician_id) REFERENCES technicians(id)
      ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_review_stars CHECK (stars BETWEEN 1 AND 5),
    INDEX idx_review_technician (technician_id, review_status)
) ENGINE=InnoDB;

-- 16. NOTIFICATIONS: in-app alerts for users
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    notification_type VARCHAR(60) NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    related_booking_id BIGINT UNSIGNED DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    delivery_status ENUM('queued','sent','failed','read') NOT NULL DEFAULT 'queued',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME DEFAULT NULL,
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_notification_booking FOREIGN KEY (related_booking_id) REFERENCES bookings(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_notification_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB;

-- 17. COMPLAINTS: customer booking-related problems and admin resolution
CREATE TABLE IF NOT EXISTS complaints (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    complaint_code VARCHAR(40) DEFAULT NULL UNIQUE,
    booking_id BIGINT UNSIGNED DEFAULT NULL,
    customer_id INT UNSIGNED NOT NULL,
    subject VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    complaint_status ENUM('open','under_review','resolved','rejected','closed') NOT NULL DEFAULT 'open',
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    admin_response TEXT DEFAULT NULL,
    assigned_admin_id INT UNSIGNED DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_complaint_booking FOREIGN KEY (booking_id) REFERENCES bookings(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_complaint_customer FOREIGN KEY (customer_id) REFERENCES users(id)
      ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_complaint_admin FOREIGN KEY (assigned_admin_id) REFERENCES admins(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_complaint_status (complaint_status, priority)
) ENGINE=InnoDB;

-- 18. SUPPORT ARTICLES: basic help and support content
CREATE TABLE IF NOT EXISTS support_articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(100) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 19. PLATFORM SETTINGS: editable commission and general settings
CREATE TABLE IF NOT EXISTS platform_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 20. ADMIN AUDIT LOGS: records important admin actions
CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED DEFAULT NULL,
    action_type VARCHAR(100) NOT NULL,
    target_table VARCHAR(100) DEFAULT NULL,
    target_id BIGINT UNSIGNED DEFAULT NULL,
    action_details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins(id)
      ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_audit_admin_date (admin_id, created_at)
) ENGINE=InnoDB;

-- Automatically create the correct role profile when an account is registered.
DELIMITER $$
CREATE TRIGGER trg_users_after_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    IF NEW.role = 'customer' THEN
        INSERT INTO customers (user_id) VALUES (NEW.id);
    ELSEIF NEW.role = 'technician' THEN
        INSERT INTO technicians (user_id) VALUES (NEW.id);
    ELSEIF NEW.role = 'admin' THEN
        INSERT INTO admins (user_id) VALUES (NEW.id);
    END IF;
END$$

-- Keep a status history record when a booking is first created.
CREATE TRIGGER trg_bookings_after_insert
AFTER INSERT ON bookings
FOR EACH ROW
BEGIN
    INSERT INTO booking_status_history (booking_id, status, changed_by, note)
    VALUES (NEW.id, NEW.status, NEW.user_id, 'Booking created');
END$$

-- Keep a status history record whenever booking status changes.
CREATE TRIGGER trg_bookings_after_status_update
AFTER UPDATE ON bookings
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO booking_status_history (booking_id, status, changed_by, note)
        VALUES (NEW.id, NEW.status, NULL, 'Booking status updated');
    END IF;
END$$
DELIMITER ;

-- Default service categories (safe to import more than once).
INSERT INTO service_categories (category_name, description, display_order)
SELECT 'Appliances', 'Home appliance repair and maintenance', 1
WHERE NOT EXISTS (SELECT 1 FROM service_categories WHERE parent_id IS NULL AND category_name = 'Appliances');

INSERT INTO service_categories (category_name, description, display_order)
SELECT 'Electronics', 'Electronic device repair and maintenance', 2
WHERE NOT EXISTS (SELECT 1 FROM service_categories WHERE parent_id IS NULL AND category_name = 'Electronics');

INSERT INTO service_categories (category_name, description, display_order)
SELECT 'Vehicles', 'Vehicle repair and maintenance services', 3
WHERE NOT EXISTS (SELECT 1 FROM service_categories WHERE parent_id IS NULL AND category_name = 'Vehicles');

INSERT INTO service_categories (category_name, description, display_order)
SELECT 'Other', 'Other repair and maintenance services', 4
WHERE NOT EXISTS (SELECT 1 FROM service_categories WHERE parent_id IS NULL AND category_name = 'Other');

-- Example service list. Admins can add/edit these later.
INSERT INTO services (category_id, service_name, description)
SELECT c.id, x.service_name, x.service_description
FROM service_categories c
JOIN (
    SELECT 'Appliances' AS category_name, 'Refrigerator Repair' AS service_name, 'Refrigerator troubleshooting and repair' AS service_description
    UNION ALL SELECT 'Appliances', 'Washing Machine Repair', 'Washing machine troubleshooting and repair'
    UNION ALL SELECT 'Appliances', 'Air Conditioner Repair', 'AC servicing and repair'
    UNION ALL SELECT 'Appliances', 'Microwave Oven Repair', 'Microwave oven servicing and repair'
    UNION ALL SELECT 'Electronics', 'Television Repair', 'LED, LCD and smart TV repair'
    UNION ALL SELECT 'Electronics', 'Laptop Repair', 'Laptop hardware and software repair'
    UNION ALL SELECT 'Electronics', 'Desktop Computer Repair', 'Desktop computer repair and maintenance'
    UNION ALL SELECT 'Electronics', 'Mobile Phone Repair', 'Smartphone repair and troubleshooting'
    UNION ALL SELECT 'Vehicles', 'Motorcycle Repair', 'Motorcycle servicing and repair'
    UNION ALL SELECT 'Vehicles', 'Car Repair', 'Car servicing and repair'
    UNION ALL SELECT 'Vehicles', 'Vehicle Electrical Repair', 'Vehicle electrical system troubleshooting'
) x ON x.category_name = c.category_name
WHERE NOT EXISTS (
    SELECT 1 FROM services s
    WHERE s.category_id = c.id AND s.service_name = x.service_name
);

INSERT INTO platform_settings (setting_key, setting_value, description)
VALUES ('default_commission_percent', '10', 'Default platform commission percentage; update according to business policy')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

-- END OF DATABASE
