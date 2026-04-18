SELECT responder_name AS user,
       response_text AS message
FROM phishing_sentiment_report
WHERE responder_name = (
    SELECT responder_name
    FROM phishing_sentiment_report
    WHERE aggression_score = 0
    LIMIT 1
)
ORDER BY id;