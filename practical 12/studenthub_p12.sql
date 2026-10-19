CREATE DATABASE IF NOT EXISTS studenthub;
USE studenthub;

CREATE TABLE IF NOT EXISTS events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    event_date DATE NOT NULL,
    venue VARCHAR(100) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) DEFAULT 'Open',
    poster VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO events (title, category, event_date, venue, description, status, poster) VALUES
('National Hackathon 2026', 'Technical', '2026-11-15', 'Main Auditorium', 'A 36-hour national coding and robotics hackathon for engineering students.', 'Open', ''),
('Cyber Security Symposium', 'Technical', '2026-11-20', 'Seminar Hall B', 'Industry experts discuss digital forensics, penetration testing, and ethical hacking.', 'Open', ''),
('Cultural Annual Fest', 'Cultural', '2026-12-05', 'Campus Grounds', 'Annual university cultural extravaganza featuring music, dance, and drama.', 'Open', ''),
('Inter-College Cricket Tournament', 'Sports', '2026-11-25', 'University Sports Arena', 'Championship tournament featuring 16 collegiate cricket teams.', 'Closed', ''),
('AI & Machine Learning Conclave', 'Technical', '2026-12-12', 'Tech Park Lab 4', 'Hands-on workshops on deep learning and generative AI architectures.', 'Open', ''),
('Alumni Leadership Talk', 'Academic', '2026-11-30', 'Conference Room A', 'Distinguished alumni share career milestones and startup leadership strategies.', 'Open', '')
ON DUPLICATE KEY UPDATE venue=VALUES(venue);
