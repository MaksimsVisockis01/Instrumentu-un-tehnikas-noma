-- 2. posms: importējiet arī tad, ja 1. posma datubāze jau izveidota.
-- Esošie lietotāji un paroles netiek mainīti.
USE noma;

CREATE TABLE IF NOT EXISTS auth_registration_consents (
    User_ID BIGINT UNSIGNED NOT NULL,
    notice_version VARCHAR(20) NOT NULL,
    accepted_at DATETIME NOT NULL,
    PRIMARY KEY (User_ID),
    CONSTRAINT fk_registration_consent_user FOREIGN KEY (User_ID)
        REFERENCES Users (User_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
