-- Importējiet šo failu pirms 02_demo_users.sql.
-- MySQL / MariaDB. Esošie dati netiek dzēsti.
CREATE DATABASE IF NOT EXISTS noma CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE noma;

CREATE TABLE IF NOT EXISTS Users (
    User_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    Username VARCHAR(50) NOT NULL,
    Email VARCHAR(254) NOT NULL,
    Phone VARCHAR(30) DEFAULT NULL,
    Password VARCHAR(255) NOT NULL COMMENT 'Tikai paroles hash, nevis atklāta parole',
    First_Name VARCHAR(100) NOT NULL,
    Last_Name VARCHAR(100) NOT NULL,
    Role ENUM('client', 'admin') NOT NULL DEFAULT 'client',
    Status ENUM('active', 'blocked') NOT NULL DEFAULT 'active',
    Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Last_Login DATETIME DEFAULT NULL,
    PRIMARY KEY (User_ID),
    UNIQUE KEY uq_users_username (Username),
    UNIQUE KEY uq_users_email (Email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_remember_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    User_ID BIGINT UNSIGNED NOT NULL,
    selector CHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_remember_selector (selector),
    KEY ix_remember_expires (expires_at),
    CONSTRAINT fk_remember_user FOREIGN KEY (User_ID) REFERENCES Users (User_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_login_limits (
    bucket_key VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    failures INT UNSIGNED NOT NULL DEFAULT 0,
    window_started BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (bucket_key),
    KEY ix_limits_window (window_started)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
