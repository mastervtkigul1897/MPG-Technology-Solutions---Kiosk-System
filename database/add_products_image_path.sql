-- Run this in phpMyAdmin on your database.
-- Adds a nullable relative path column for product images.
-- Images are stored in /public/uploads/products and this column stores values like:
--   uploads/products/t2_20260402010101_ab12cd34.jpg

ALTER TABLE `products`
  ADD COLUMN `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL
  AFTER `price`;

