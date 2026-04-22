SELECT 'users' AS tbl, COUNT(*) AS cnt
FROM `users`
WHERE LOWER(`email`) IN ('mpgmusicsolutions@gmail.com', 'nivramellug@gmail.com')

UNION ALL

SELECT 'laundry_customers' AS tbl, COUNT(*) AS cnt
FROM `laundry_customers`
WHERE LOWER(`email`) IN ('mpgmusicsolutions@gmail.com', 'nivramellug@gmail.com')

UNION ALL

SELECT 'password_reset_tokens' AS tbl, COUNT(*) AS cnt
FROM `password_reset_tokens`
WHERE LOWER(`email`) IN ('mpgmusicsolutions@gmail.com', 'nivramellug@gmail.com')

UNION ALL

SELECT 'tenants.receipt_email' AS tbl, COUNT(*) AS cnt
FROM `tenants`
WHERE LOWER(`receipt_email`) IN ('mpgmusicsolutions@gmail.com', 'nivramellug@gmail.com');
