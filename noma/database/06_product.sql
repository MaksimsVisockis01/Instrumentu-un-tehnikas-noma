-- 4. posms: preces lapa un demonstrācijas rezervācijas.
-- Importēt pēc 04_catalog.sql. Esošie dati netiek dzēsti.
USE noma;

CREATE TABLE IF NOT EXISTS Product_Details (
    Product_ID BIGINT UNSIGNED NOT NULL,
    Specifications JSON DEFAULT NULL,
    Condition_Notes TEXT NOT NULL,
    Rental_Terms TEXT NOT NULL,
    Pickup_Info VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (Product_ID),
    CONSTRAINT fk_details_product FOREIGN KEY (Product_ID)
        REFERENCES Products (Product_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Reservations (
    Reservation_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    Product_ID BIGINT UNSIGNED NOT NULL,
    Client_ID BIGINT UNSIGNED NOT NULL,
    Start_Date DATE NOT NULL,
    End_Date DATE NOT NULL,
    Daily_Price DECIMAL(10,2) UNSIGNED NOT NULL,
    Rental_Price DECIMAL(18,2) UNSIGNED NOT NULL,
    Deposit DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
    Total_Price DECIMAL(18,2) UNSIGNED NOT NULL,
    Status ENUM('demo_confirmed', 'demo_cancelled') NOT NULL DEFAULT 'demo_confirmed',
    Request_Key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Cancelled_At DATETIME DEFAULT NULL,
    PRIMARY KEY (Reservation_ID),
    UNIQUE KEY uq_reservation_request (Request_Key),
    KEY ix_reservation_product_dates (Product_ID, Status, Start_Date, End_Date),
    KEY ix_reservation_client (Client_ID, Product_ID, Status),
    CONSTRAINT fk_reservation_product FOREIGN KEY (Product_ID) REFERENCES Products (Product_ID),
    CONSTRAINT fk_reservation_client FOREIGN KEY (Client_ID) REFERENCES Users (User_ID),
    CONSTRAINT chk_reservation_dates CHECK (End_Date > Start_Date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
