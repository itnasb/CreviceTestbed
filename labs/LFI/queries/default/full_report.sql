SELECT aggression_score, responder_name, CONCAT('\n', response_text) AS message 
FROM phishing_sentiment_report 
ORDER BY aggression_score ASC, report_date DESC;
