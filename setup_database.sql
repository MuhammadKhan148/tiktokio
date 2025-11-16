-- Database Setup SQL Script
-- Run this with: mysql -u root -p < setup_database.sql

-- Create database
CREATE DATABASE IF NOT EXISTS `tiktokio.mobi` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Create user and set password
CREATE USER IF NOT EXISTS 'tiktokio.mobi'@'localhost' IDENTIFIED BY 'TfjfPrtjC4Z4wmBm';

-- Grant privileges
GRANT ALL PRIVILEGES ON `tiktokio.mobi`.* TO 'tiktokio.mobi'@'localhost';

-- Flush privileges
FLUSH PRIVILEGES;

-- Use the database
USE `tiktokio.mobi`;

-- Source the main SQL file
SOURCE tiktokio_mobi.sql;

