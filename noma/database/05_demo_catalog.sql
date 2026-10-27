-- NOMA.LV mācību prototipa demonstrācijas katalogs. Importēt pēc 04_catalog.sql.
-- Atkārtota importēšana nemaina esošos ierakstus un nepārbīda aizņemtības datumus.
-- Paraugu īpašniekiem NAV derīgas paroles; tie nav konti, ar kuriem var pieslēgties.
USE noma;
START TRANSACTION;

INSERT INTO Categories (Slug, Name, Display_Order) SELECT 'tools', 'Elektroinstrumenti', 1 WHERE NOT EXISTS (SELECT 1 FROM Categories WHERE Slug = 'tools');
INSERT INTO Categories (Slug, Name, Display_Order) SELECT 'photo', 'Foto / Video tehnika', 2 WHERE NOT EXISTS (SELECT 1 FROM Categories WHERE Slug = 'photo');
INSERT INTO Categories (Slug, Name, Display_Order) SELECT 'gaming', 'Spēļu tehnika & VR', 3 WHERE NOT EXISTS (SELECT 1 FROM Categories WHERE Slug = 'gaming');
INSERT INTO Categories (Slug, Name, Display_Order) SELECT 'garden', 'Dārza aprīkojums', 4 WHERE NOT EXISTS (SELECT 1 FROM Categories WHERE Slug = 'garden');
INSERT INTO Categories (Slug, Name, Display_Order) SELECT 'cleaning', 'Tīrīšanas tehnika', 5 WHERE NOT EXISTS (SELECT 1 FROM Categories WHERE Slug = 'cleaning');

INSERT INTO Users (Username, Email, Password, First_Name, Last_Name, Role, Status) SELECT 'sample.catalog.owner.andris', 'andris@noma-demo.invalid', '!catalog_demo_account', 'Andris', 'Kalniņš', 'client', 'active' WHERE NOT EXISTS (SELECT 1 FROM Users WHERE Username = 'sample.catalog.owner.andris' OR Email = 'andris@noma-demo.invalid');

INSERT INTO Users (Username, Email, Password, First_Name, Last_Name, Role, Status) SELECT 'sample.catalog.owner.kristaps', 'kristaps@noma-demo.invalid', '!catalog_demo_account', 'Kristaps', 'Miezītis', 'client', 'active' WHERE NOT EXISTS (SELECT 1 FROM Users WHERE Username = 'sample.catalog.owner.kristaps' OR Email = 'kristaps@noma-demo.invalid');

INSERT INTO Users (Username, Email, Password, First_Name, Last_Name, Role, Status) SELECT 'sample.catalog.owner.laura', 'laura@noma-demo.invalid', '!catalog_demo_account', 'Laura', 'Zariņa', 'client', 'active' WHERE NOT EXISTS (SELECT 1 FROM Users WHERE Username = 'sample.catalog.owner.laura' OR Email = 'laura@noma-demo.invalid');

INSERT INTO Users (Username, Email, Password, First_Name, Last_Name, Role, Status) SELECT 'sample.catalog.owner.martins', 'martins@noma-demo.invalid', '!catalog_demo_account', 'Mārtiņš', 'Priedītis', 'client', 'active' WHERE NOT EXISTS (SELECT 1 FROM Users WHERE Username = 'sample.catalog.owner.martins' OR Email = 'martins@noma-demo.invalid');

INSERT INTO Users (Username, Email, Password, First_Name, Last_Name, Role, Status) SELECT 'sample.catalog.owner.kaspars', 'kaspars@noma-demo.invalid', '!catalog_demo_account', 'Kaspars', 'Siliņš', 'client', 'active' WHERE NOT EXISTS (SELECT 1 FROM Users WHERE Username = 'sample.catalog.owner.kaspars' OR Email = 'kaspars@noma-demo.invalid');

INSERT INTO Products (Owner_ID, Category_ID, Slug, Name, Description, Price, Deposit, Item_Condition, City, Image_Path, Featured_Rank)
SELECT u.User_ID, c.Category_ID, 'bosch-pbh-2100', 'Bosch perforators PBH 2100 RE', 'Perforators nelieliem remonta darbiem. Demonstrācijas komplektā paredzēts instruments un transportēšanas koferis. Piederumu sastāvs šajā prototipā ir ilustratīvs.', 15, 50, 'excellent', 'Rīga', 'assets/images/products/bosch.jpg', 1
FROM Users u JOIN Categories c ON c.Slug = 'tools'
WHERE u.Username = 'sample.catalog.owner.andris' AND u.Email = 'andris@noma-demo.invalid'
  AND u.Password = '!catalog_demo_account' AND u.Role = 'client' AND u.Status = 'active'
  AND NOT EXISTS (SELECT 1 FROM Products WHERE Slug = 'bosch-pbh-2100');

INSERT INTO Products (Owner_ID, Category_ID, Slug, Name, Description, Price, Deposit, Item_Condition, City, Image_Path, Featured_Rank)
SELECT u.User_ID, c.Category_ID, 'sony-playstation-5', 'Sony PlayStation 5 + 2 kontrolieri', 'Spēļu konsole brīvdienām vai vakaram kopā ar draugiem. Demonstrācijas sludinājumā paredzēti divi kontrolieri. Spēļu izvēle nav norādīta.', 18, 100, 'new', 'Rīga', 'assets/images/products/playstation.png', 2
FROM Users u JOIN Categories c ON c.Slug = 'gaming'
WHERE u.Username = 'sample.catalog.owner.kristaps' AND u.Email = 'kristaps@noma-demo.invalid'
  AND u.Password = '!catalog_demo_account' AND u.Role = 'client' AND u.Status = 'active'
  AND NOT EXISTS (SELECT 1 FROM Products WHERE Slug = 'sony-playstation-5');

INSERT INTO Products (Owner_ID, Category_ID, Slug, Name, Description, Price, Deposit, Item_Condition, City, Image_Path, Featured_Rank)
SELECT u.User_ID, c.Category_ID, 'canon-eos-r6', 'Canon EOS R6 — kameras korpuss', 'Bezspoguļa kameras korpuss fotografēšanas izmēģinājumiem. Objektīvs nav iekļauts demonstrācijas komplektā. Katalogā redzamie datumi palīdz izmēģināt pieejamības filtru.', 35, 200, 'good', 'Rīga', 'assets/images/products/canon.jpg', 3
FROM Users u JOIN Categories c ON c.Slug = 'photo'
WHERE u.Username = 'sample.catalog.owner.laura' AND u.Email = 'laura@noma-demo.invalid'
  AND u.Password = '!catalog_demo_account' AND u.Role = 'client' AND u.Status = 'active'
  AND NOT EXISTS (SELECT 1 FROM Products WHERE Slug = 'canon-eos-r6');

INSERT INTO Products (Owner_ID, Category_ID, Slug, Name, Description, Price, Deposit, Item_Condition, City, Image_Path, Featured_Rank)
SELECT u.User_ID, c.Category_ID, 'meta-quest-3', 'Meta Quest 3 VR brilles (128 GB)', 'Virtuālās realitātes brilles ar kontrolieriem. Demonstrācijas sludinājums VR pieredzes izmēģināšanai. Attēls ilustrē preces veidu.', 22, 120, 'excellent', 'Rīga', 'assets/images/products/meta.jpg', 4
FROM Users u JOIN Categories c ON c.Slug = 'gaming'
WHERE u.Username = 'sample.catalog.owner.martins' AND u.Email = 'martins@noma-demo.invalid'
  AND u.Password = '!catalog_demo_account' AND u.Role = 'client' AND u.Status = 'active'
  AND NOT EXISTS (SELECT 1 FROM Products WHERE Slug = 'meta-quest-3');

INSERT INTO Products (Owner_ID, Category_ID, Slug, Name, Description, Price, Deposit, Item_Condition, City, Image_Path, Featured_Rank)
SELECT u.User_ID, c.Category_ID, 'karcher-k5-compact', 'Kärcher K5 Compact', 'Augstspiediena mazgātājs āra tīrīšanas darbiem. Prototipa sludinājumā paredzēta ierīce ar šļūteni un uzgali. Prece un komplektācija ir demonstrācijas dati.', 20, 80, 'excellent', 'Rīga', 'assets/images/products/karcher.jpg', 5
FROM Users u JOIN Categories c ON c.Slug = 'cleaning'
WHERE u.Username = 'sample.catalog.owner.kaspars' AND u.Email = 'kaspars@noma-demo.invalid'
  AND u.Password = '!catalog_demo_account' AND u.Role = 'client' AND u.Status = 'active'
  AND NOT EXISTS (SELECT 1 FROM Products WHERE Slug = 'karcher-k5-compact');

INSERT INTO Products (Owner_ID, Category_ID, Slug, Name, Description, Price, Deposit, Item_Condition, City, Image_Path, Featured_Rank)
SELECT u.User_ID, c.Category_ID, 'makita-ddf484rtj', 'Makita DDF484RTJ urbjmašīna', 'Akumulatora urbjmašīna ikdienas montāžas darbiem. Demonstrācijas komplektā paredzēts instruments, akumulators un lādētājs. Foto ir ilustratīvs.', 12, 60, 'good', 'Rīga', 'assets/images/products/makita.jpg', 6
FROM Users u JOIN Categories c ON c.Slug = 'tools'
WHERE u.Username = 'sample.catalog.owner.andris' AND u.Email = 'andris@noma-demo.invalid'
  AND u.Password = '!catalog_demo_account' AND u.Role = 'client' AND u.Status = 'active'
  AND NOT EXISTS (SELECT 1 FROM Products WHERE Slug = 'makita-ddf484rtj');

-- Šis demo periods tiek izveidots tikai vienreiz: šodiena + 3 dienas.
-- SQL izmanto DB servera kalendāro dienu; lietotne izmanto Europe/Riga.
INSERT INTO Product_Unavailability (Product_ID, Start_Date, End_Date, Note, Seed_Key)
SELECT p.Product_ID, CURRENT_DATE, DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY),
       'Prototipa demonstrācijas aizņemtības periods', 'demo-canon-first-period'
FROM Products p JOIN Users u ON u.User_ID = p.Owner_ID
WHERE p.Slug = 'canon-eos-r6' AND u.Username = 'sample.catalog.owner.laura'
  AND u.Email = 'laura@noma-demo.invalid' AND u.Password = '!catalog_demo_account'
  AND NOT EXISTS (SELECT 1 FROM Product_Unavailability WHERE Seed_Key = 'demo-canon-first-period');
COMMIT;
