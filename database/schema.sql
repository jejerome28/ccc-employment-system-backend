-- Reference schema only. The migrations are the source of truth:
--   php artisan migrate --seed

CREATE DATABASE IF NOT EXISTS employee_management
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE employee_management;

CREATE TABLE users (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    remember_token  VARCHAR(100) NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE employees (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_code   VARCHAR(30) NOT NULL UNIQUE,
    first_name      VARCHAR(60) NOT NULL,
    last_name       VARCHAR(60) NOT NULL,
    email           VARCHAR(255) NULL UNIQUE,
    phone           VARCHAR(30) NULL,
    position        VARCHAR(80) NULL,
    department      VARCHAR(80) NULL,
    hire_date       DATE NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    INDEX employees_name_index (last_name, first_name),
    INDEX employees_status_index (status)
) ENGINE=InnoDB;

CREATE TABLE attendances (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id     BIGINT UNSIGNED NOT NULL,
    work_date       DATE NOT NULL,
    time_in         TIME NULL,
    time_out        TIME NULL,
    notes           VARCHAR(255) NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    UNIQUE KEY attendances_employee_date_unique (employee_id, work_date),
    INDEX attendances_work_date_index (work_date),
    CONSTRAINT attendances_employee_id_foreign
        FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE
) ENGINE=InnoDB;
