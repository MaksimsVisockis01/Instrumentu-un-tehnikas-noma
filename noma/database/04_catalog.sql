-- 3. posms. Importēt pēc 01_schema.sql un 03_registration.sql.
-- MySQL 8 / MariaDB. Atkārtota importēšana nedzēš esošos datus.
USE noma;

CREATE TABLE IF NOT EXISTS Categories (
    Category_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    Slug VARCHAR(80) NOT NULL,
    Name VARCHAR(100) NOT NULL,
    Display_Order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (Category_ID),
    UNIQUE KEY uq_category_slug (Slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Products (
    Product_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    Owner_ID BIGINT UNSIGNED NOT NULL,
    Category_ID BIGINT UNSIGNED NOT NULL,
    Slug VARCHAR(120) NOT NULL,
    Name VARCHAR(180) NOT NULL,
    Description TEXT NOT NULL,
    Price DECIMAL(10,2) UNSIGNED NOT NULL,
    Deposit DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
    Status ENUM('active', 'inactive', 'maintenance') NOT NULL DEFAULT 'active',
    Item_Condition ENUM('new', 'excellent', 'good') NOT NULL DEFAULT 'excellent',
    City VARCHAR(80) NOT NULL DEFAULT 'Rīga',
    Image_Path VARCHAR(255) DEFAULT NULL,
    Featured_Rank INT NOT NULL DEFAULT 100,
    Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (Product_ID),
    UNIQUE KEY uq_product_slug (Slug),
    KEY ix_product_category_status (Category_ID, Status),
    KEY ix_product_status_price (Status, Price),
    CONSTRAINT fk_product_owner FOREIGN KEY (Owner_ID) REFERENCES Users (User_ID),
    CONSTRAINT fk_product_category FOREIGN KEY (Category_ID) REFERENCES Categories (Category_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bloķētie kalendāra periodi. Rezervāciju modulis būs atsevišķā posmā.
-- Intervāls [Start_Date, End_Date): atgriešanas diena vairs nav bloķēta.
CREATE TABLE IF NOT EXISTS Product_Unavailability (
    Block_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    Product_ID BIGINT UNSIGNED NOT NULL,
    Start_Date DATE NOT NULL,
    End_Date DATE NOT NULL,
    Note VARCHAR(160) NOT NULL DEFAULT '',
    Seed_Key VARCHAR(120) DEFAULT NULL,
    PRIMARY KEY (Block_ID),
    UNIQUE KEY uq_block_seed (Seed_Key),
    KEY ix_block_product_dates (Product_ID, Start_Date, End_Date),
    CONSTRAINT fk_block_product FOREIGN KEY (Product_ID) REFERENCES Products (Product_ID) ON DELETE CASCADE,
    CONSTRAINT chk_block_dates CHECK (End_Date > Start_Date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Favorites (
    User_ID BIGINT UNSIGNED NOT NULL,
    Product_ID BIGINT UNSIGNED NOT NULL,
    Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (User_ID, Product_ID),
    CONSTRAINT fk_favorite_user FOREIGN KEY (User_ID) REFERENCES Users (User_ID) ON DELETE CASCADE,
    CONSTRAINT fk_favorite_product FOREIGN KEY (Product_ID) REFERENCES Products (Product_ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
