USE crevice_db;

TRUNCATE TABLE phishing_sentiment_report;

INSERT INTO phishing_sentiment_report (responder_name, response_text, aggression_score, report_date) VALUES
('Alice Jenkins', 'Thank you for keeping us safe! I almost clicked but checked the URL first.', 0, '2023-01-15'),
('Alice Jenkins', 'Great job on the training modules this month!', 0, '2023-02-12'),
('Bob Miller', 'Keep up the hard work, these tests are getting much harder to spot!', 1, '2023-03-10'),
('Bob Miller', 'I appreciate the vigilance of the IT team.', 1, '2023-04-05'),
('Chadwick Thorton', 'Per my previous email, I do not have time for these little games.', 2, '2023-05-20'),
('Chadwick Thorton', 'I assume this is more important than my actual billable hours?', 2, '2023-06-15'),
('Chadwick Thorton', 'This correspondence—which was quite the perfidious little surprise—insinuated that I had clandestinely pilfered data. The entire charade was both capricious and incendiary and you should be reprimanded!', 2, '2023-09-12'),
('Karen Vane', 'If you send me one more fake email I will find your desk and throw your monitor out the window.', 3, '2023-07-01'),
('Karen Vane', 'Stop harassing me or I am coming to the server room to physically pull the plug.', 3, '2023-08-10'),
('Karen Vane', 'Your nefarious attempt to bamboozle me with a phishing simulation has pushed my equanimity past its precipice! If you persist, I shall be forced to physically descend upon your cubicle with pugnacious intent!', 3, '2023-11-20');
