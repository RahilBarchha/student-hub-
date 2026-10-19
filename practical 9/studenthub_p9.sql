CREATE DATABASE IF NOT EXISTS studenthub;
USE studenthub;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    course VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, fullname, email, password, mobile, course) VALUES
('rahil_admin', 'Rahil Patel', 'rahil@example.com', '$2y$10$wT.4eJ3mXUqOsqMfqEwFSuE97Wc3q50sN.QZ7zDqfU0j8Z7wF9e6O', '9876543210', 'BTech_IT'),
('priya_dave', 'Priya Dave', 'priya@example.com', '$2y$10$wT.4eJ3mXUqOsqMfqEwFSuE97Wc3q50sN.QZ7zDqfU0j8Z7wF9e6O', '9812345678', 'BTech_CSE')
ON DUPLICATE KEY UPDATE fullname=VALUES(fullname);
