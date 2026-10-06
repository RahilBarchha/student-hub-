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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    event_date DATE NOT NULL,
    venue VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    event_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'Confirmed',
    CONSTRAINT fk_reg_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    CONSTRAINT fk_reg_event FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    CONSTRAINT unique_student_event UNIQUE (student_id, event_id)
);

INSERT INTO students (name, email, mobile, course, year, gender) VALUES
('Aarav Patel', 'aarav@example.com', '9823456789', 'BTech_IT', 2, 'Male'),
('Priya Dave', 'priya@example.com', '9812345678', 'BTech_CSE', 3, 'Female'),
('Spandan Shah', 'spandan@example.com', '9712345678', 'BTech_AI', 1, 'Male')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO events (title, category, event_date, venue) VALUES
('National Hackathon 2026', 'Technical', '2026-11-15', 'Main Auditorium'),
('Cyber Security Symposium', 'Technical', '2026-11-20', 'Seminar Hall B'),
('Cultural Annual Fest', 'Cultural', '2026-12-05', 'Campus Grounds')
ON DUPLICATE KEY UPDATE title=VALUES(title);

INSERT IGNORE INTO registrations (student_id, event_id, status) VALUES
(1, 1, 'Confirmed'),
(2, 1, 'Confirmed'),
(2, 2, 'Confirmed'),
(3, 3, 'Confirmed');

DROP PROCEDURE IF EXISTS sp_get_student_events;

DELIMITER //
CREATE PROCEDURE sp_get_student_events(IN p_student_id INT)
BEGIN
    SELECT 
        s.name AS student_name,
        e.title AS event_title,
        e.event_date,
        e.venue,
        r.status,
        r.registered_at
    FROM registrations r
    JOIN students s ON r.student_id = s.student_id
    JOIN events e ON r.event_id = e.event_id
    WHERE s.student_id = p_student_id;
END //
DELIMITER ;
