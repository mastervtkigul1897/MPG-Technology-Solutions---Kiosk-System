START TRANSACTION;

-- Remove target addresses from auth/reset and customer data.
DELETE FROM `password_reset_tokens`
WHERE LOWER(`email`) IN ('mpgmusicsolutions@gmail.com', 'nivramellug@gmail.com');

DELETE FROM `laundry_customers`
WHERE LOWER(`email`) IN ('mpgmusicsolutions@gmail.com', 'nivramellug@gmail.com');

-- Clear tenant receipt email if it matches one of the removed addresses.
UPDATE `tenants`
SET `receipt_email` = NULL
WHERE LOWER(`receipt_email`) IN ('mpgmusicsolutions@gmail.com', 'nivramellug@gmail.com');

-- Remove user accounts with those addresses.
DELETE FROM `users`
WHERE LOWER(`email`) IN ('mpgmusicsolutions@gmail.com', 'nivramellug@gmail.com');

COMMIT;
