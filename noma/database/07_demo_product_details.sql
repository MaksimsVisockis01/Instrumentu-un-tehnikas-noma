-- Papildu dati mācību prototipa preču lapām.
-- Importēt pēc 05_demo_catalog.sql un 06_product.sql.
-- Esošie apraksti un kalendāra periodi netiek pārrakstīti.
USE noma;
START TRANSACTION;

INSERT INTO Product_Details (Product_ID, Specifications, Condition_Notes, Rental_Terms, Pickup_Info)
SELECT p.Product_ID,
    CASE WHEN p.Slug = 'bosch-pbh-2100' THEN JSON_OBJECT(
        'Nominālā jauda', '550 W',
        'Patronas tips', 'SDS-plus',
        'Trieciena enerģija', '1,7 J',
        'Mehānisms', 'Pneimatisks',
        'Maks. urbuma Ø betonā', '20 mm',
        'Maks. urbuma Ø tēraudā / kokā', '13 mm / 30 mm',
        'Brīvgaitas ātrums', '0–2300 apgr./min',
        'Triecienu skaits minūtē', '0–5800',
        'Svars ar kabeli', '2,2 kg')
    ELSE JSON_OBJECT('Modelis', p.Name, 'Kategorija', c.Name) END,
    CASE p.Item_Condition
        WHEN 'new' THEN 'Demonstrācijas sludinājumā norādīta jauna prece oriģinālajā iepakojumā.'
        WHEN 'good' THEN 'Labs stāvoklis ar redzamām lietojuma pazīmēm. Šis ir demonstrācijas apraksts.'
        ELSE 'Teicams tehniskais un vizuālais stāvoklis. Šis ir demonstrācijas apraksts.' END,
    'Atgriezt tīru un tādā pašā komplektācijā. Lietot atbilstoši instrukcijai. Nenodot trešajām personām. Šie ir mācību prototipa nosacījumi.',
    CONCAT('Saņemšana klātienē: ', p.City, '. Precīza adrese prototipā nav norādīta.')
FROM Products p JOIN Categories c ON c.Category_ID = p.Category_ID
JOIN Users u ON u.User_ID = p.Owner_ID
WHERE u.Password = '!catalog_demo_account'
  AND p.Slug IN ('bosch-pbh-2100', 'sony-playstation-5', 'canon-eos-r6', 'meta-quest-3', 'karcher-k5-compact', 'makita-ddf484rtj')
  AND NOT EXISTS (SELECT 1 FROM Product_Details d WHERE d.Product_ID = p.Product_ID);

-- Paraugu parametri ņemti no lietotāja iesniegtā Bosch lapas maketa.
-- Datumi ir relatīvi tikai pirmajā importēšanas reizē.
INSERT INTO Product_Unavailability (Product_ID, Start_Date, End_Date, Note, Seed_Key)
SELECT p.Product_ID, DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY), DATE_ADD(CURRENT_DATE, INTERVAL 9 DAY),
    'Demonstrācijas kalendāra periods', 'demo-bosch-calendar-1'
FROM Products p JOIN Users u ON u.User_ID = p.Owner_ID
WHERE p.Slug = 'bosch-pbh-2100' AND u.Password = '!catalog_demo_account'
  AND NOT EXISTS (SELECT 1 FROM Product_Unavailability WHERE Seed_Key = 'demo-bosch-calendar-1');

INSERT INTO Product_Unavailability (Product_ID, Start_Date, End_Date, Note, Seed_Key)
SELECT p.Product_ID, DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY), DATE_ADD(CURRENT_DATE, INTERVAL 16 DAY),
    'Demonstrācijas kalendāra periods', 'demo-bosch-calendar-2'
FROM Products p JOIN Users u ON u.User_ID = p.Owner_ID
WHERE p.Slug = 'bosch-pbh-2100' AND u.Password = '!catalog_demo_account'
  AND NOT EXISTS (SELECT 1 FROM Product_Unavailability WHERE Seed_Key = 'demo-bosch-calendar-2');
COMMIT;
