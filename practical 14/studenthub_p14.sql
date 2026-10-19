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
    role VARCHAR(20) DEFAULT 'student',
    status VARCHAR(20) DEFAULT 'Active',
    last_login DATETIME NULL,
    remember_token VARCHAR(255) NULL,
    remember_expiry DATETIME NULL,
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

CREATE TABLE IF NOT EXISTS events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    event_date DATE NOT NULL,
    venue VARCHAR(100) NOT NULL,
    description TEXT,
    poster VARCHAR(255) NULL,
    status VARCHAR(20) DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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

CREATE TABLE IF NOT EXISTS registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    event_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'Confirmed'
);

INSERT INTO users (id, username, fullname, email, password, mobile, course, role, status) VALUES
(1, 'barchharahil720@gmail.com', 'Rahil Barchha', 'barchharahil720@gmail.com', '$2y$10$wUv6G87FzK.3vV2r4291.eb1oVb38hD9wz5iC1Y8hK9e1eZkE/oem', '9876543210', 'BTech_IT', 'admin', 'Active'),
(2, 'student_rohan', 'Rohan Sharma', 'rohan@example.com', '$2y$10$wUv6G87FzK.3vV2r4291.eb1oVb38hD9wz5iC1Y8hK9e1eZkE/oem', '9876543211', 'BTech_CSE', 'student', 'Active'),
(3, 'priya_student', 'Priya Dave', 'priya.user@example.com', '$2y$10$wUv6G87FzK.3vV2r4291.eb1oVb38hD9wz5iC1Y8hK9e1eZkE/oem', '9812345672', 'BTech_IT', 'student', 'Active'),
(4, 'aarav_student', 'Aarav Patel', 'aarav.user@example.com', '$2y$10$wUv6G87FzK.3vV2r4291.eb1oVb38hD9wz5iC1Y8hK9e1eZkE/oem', '9823456781', 'BTech_AI', 'student', 'Inactive'),
(5, 'ananya_user', 'Ananya Iyer', 'ananya.user@example.com', '$2y$10$wUv6G87FzK.3vV2r4291.eb1oVb38hD9wz5iC1Y8hK9e1eZkE/oem', '9876543201', 'BTech_CSE', 'student', 'Active')
ON DUPLICATE KEY UPDATE status=VALUES(status);

INSERT INTO registrations (student_id, event_id, registered_at, status) VALUES 
(1, 1, '2026-06-15 10:30:00', 'Confirmed'),
(2, 1, '2026-06-20 14:15:00', 'Confirmed'),
(3, 2, '2026-07-05 09:00:00', 'Confirmed'),
(4, 2, '2026-07-18 11:20:00', 'Confirmed'),
(5, 3, '2026-08-10 16:45:00', 'Confirmed'),
(1, 3, '2026-08-25 12:10:00', 'Confirmed'),
(2, 4, '2026-09-04 15:30:00', 'Confirmed'),
(3, 5, '2026-09-22 17:00:00', 'Confirmed'),
(4, 5, '2026-10-02 11:00:00', 'Confirmed'),
(5, 6, '2026-10-12 14:30:00', 'Confirmed');
