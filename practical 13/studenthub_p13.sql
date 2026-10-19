CREATE DATABASE IF NOT EXISTS studenthub;
USE studenthub;

CREATE TABLE IF NOT EXISTS students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    mobile VARCHAR(15) NOT NULL,
    course VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    gender VARCHAR(10) NOT NULL,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO students (student_id, name, email, mobile, course, year, gender, status) VALUES
(1, 'Aarav Patel', 'aarav.api@example.com', '9823456781', 'BTech_IT', 2, 'Male', 'Active'),
(2, 'Priya Dave', 'priya.api@example.com', '9812345672', 'BTech_CSE', 3, 'Female', 'Active'),
(3, 'Spandan Shah', 'spandan.api@example.com', '9712345673', 'BTech_AI', 1, 'Male', 'Active'),
(4, 'Ananya Iyer', 'ananya.api@example.com', '9876543201', 'BTech_CSE', 4, 'Female', 'Active'),
(5, 'Rohan Sharma', 'rohan.api@example.com', '9822334402', 'BTech_IT', 2, 'Male', 'Active')
ON DUPLICATE KEY UPDATE name=VALUES(name);
