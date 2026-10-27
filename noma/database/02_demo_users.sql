-- Tikai lokālai pārbaudei. Demo paroles ir norādītas README_RU.md.
-- Atkārtota importēšana nemaina esošos kontus.
USE noma;

INSERT INTO Users (Username, Email, Phone, Password, First_Name, Last_Name, Role)
SELECT 'janisb', 'janis@example.com', '+37120000001',
       '$2y$12$7xn7pJPMAgxpTMJF47KFqOMACA4Jgbrwg4TTaJ7T9Rq2F2hdobAiK',
       'Jānis', 'Bērziņš', 'client'
WHERE NOT EXISTS (SELECT 1 FROM Users WHERE Username = 'janisb' OR Email = 'janis@example.com');

INSERT INTO Users (Username, Email, Phone, Password, First_Name, Last_Name, Role)
SELECT 'admin', 'admin@example.com', '+37120000002',
       '$2y$12$Bh7kPNXCtnuKLX2Fb5T1N.19nKWH4xXGRSVkXtTJwpFZty64q1LcS',
       'Noma', 'Administrators', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM Users WHERE Username = 'admin' OR Email = 'admin@example.com');
