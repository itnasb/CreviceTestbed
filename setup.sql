CREATE DATABASE IF NOT EXISTS crevice_db;
CREATE USER IF NOT EXISTS 'sa_user'@'%' IDENTIFIED BY 'sa_password';
GRANT ALL PRIVILEGES ON crevice_db.* TO 'sa_user'@'%';
FLUSH PRIVILEGES;

USE crevice_db;

-- Existing users table (The Easter Egg)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    password_hash VARCHAR(100),
    role VARCHAR(20)
);

-- Make seeding repeatable without duplicates
TRUNCATE TABLE users;

INSERT INTO users (username, password_hash, role) VALUES
('admin', '$2y$10$D0g.G0n3.W1ld.Exploit.Payload', 'superuser'),
('mssql_svc', '$2y$10$Svc.Accnt.Pass.123!', 'service');

-- New Phishing Response Tracker
CREATE TABLE IF NOT EXISTS phishing_sentiment_report (
    id INT AUTO_INCREMENT PRIMARY KEY,
    responder_name VARCHAR(100),
    response_text TEXT,
    aggression_score INT, -- 0: Nice, 1: Encouraging, 2: Passive Aggressive, 3: Overtly Aggressive
    report_date DATE
);

-- Make seeding repeatable without duplicates
TRUNCATE TABLE phishing_sentiment_report;

INSERT INTO phishing_sentiment_report (responder_name, response_text, aggression_score, report_date) VALUES
-- Score 0: Nice (Winner: Alice)
('Alice Jenkins', 'Thank you for keeping us safe! I almost clicked but checked the URL first.', 0, '2023-01-15'),
('Alice Jenkins', 'Great job on the training modules this month!', 0, '2023-02-12'),

-- Score 1: Encouraging (Winner: Bob)
('Bob Miller', 'Keep up the hard work, these tests are getting much harder to spot!', 1, '2023-03-10'),
('Bob Miller', 'I appreciate the vigilance of the IT team.', 1, '2023-04-05'),

-- Score 2: Passive Aggressive (Winner: Chad)
('Chadwick Thorton', 'Per my previous email, I do not have time for these little games.', 2, '2023-05-20'),
('Chadwick Thorton', 'I assume this is more important than my actual billable hours?', 2, '2023-06-15'),
('Chadwick Thorton', 'This correspondence—which was quite the perfidious little surprise—insinuated that I had clandestinely pilfered data. The entire charade was both capricious and incendiary and you should be reprimanded!', 2, '2023-09-12'),

-- Score 3: Overtly Aggressive (Winner: Karen)
('Karen Vane', 'If you send me one more fake email I will find your desk and throw your monitor out the window.', 3, '2023-07-01'),
('Karen Vane', 'Stop harassing me or I am coming to the server room to physically pull the plug.', 3, '2023-08-10'),
('Karen Vane', 'Your nefarious attempt to bamboozle me with a phishing simulation has pushed my equanimity past its precipice! If you persist, I shall be forced to physically descend upon your cubicle with pugnacious intent!', 3, '2023-11-20');
