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

INSERT INTO students (name, email, mobile, course, year, gender, status) VALUES
('Aarav Patel', 'aarav.patel@example.com', '9823456781', 'BTech_IT', 2, 'Male', 'Active'),
('Priya Dave', 'priya.dave@example.com', '9812345672', 'BTech_CSE', 3, 'Female', 'Active'),
('Spandan Shah', 'spandan.shah@example.com', '9712345673', 'BTech_AI', 1, 'Male', 'Active'),
('Ananya Iyer', 'ananya.iyer@example.com', '9876543201', 'BTech_CSE', 4, 'Female', 'Active'),
('Rohan Sharma', 'rohan.sharma@example.com', '9822334402', 'BTech_IT', 2, 'Male', 'Active'),
('Neha Mehta', 'neha.mehta@example.com', '9711223303', 'BCA', 1, 'Female', 'Active'),
('Devansh Trivedi', 'devansh.t@example.com', '9633445504', 'MCA', 2, 'Male', 'Active'),
('Kavya Joshi', 'kavya.joshi@example.com', '9855667705', 'BTech_AI', 3, 'Female', 'Active'),
('Manav Desai', 'manav.desai@example.com', '9744556606', 'BTech_CSE', 1, 'Male', 'Active'),
('Tanvi Bhatt', 'tanvi.bhatt@example.com', '9899887707', 'BTech_IT', 4, 'Female', 'Active'),
('Yash Vora', 'yash.vora@example.com', '9611224408', 'BCA', 2, 'Male', 'Active'),
('Ritu Rajput', 'ritu.rajput@example.com', '9833441109', 'MCA', 1, 'Female', 'Active'),
('Harsh Solanki', 'harsh.solanki@example.com', '9722338810', 'BTech_AI', 2, 'Male', 'Active'),
('Ishita Sengupta', 'ishita.s@example.com', '9866772211', 'BTech_CSE', 3, 'Female', 'Active'),
('Aditya Chudasama', 'aditya.c@example.com', '9811993312', 'BTech_IT', 1, 'Male', 'Active'),
('Krupa Vaghela', 'krupa.v@example.com', '9744229913', 'BCA', 3, 'Female', 'Active'),
('Parth Prajapati', 'parth.prajapati@example.com', '9855331114', 'MCA', 2, 'Male', 'Active'),
('Drishti Patel', 'drishti.p@example.com', '9633882215', 'BTech_AI', 4, 'Female', 'Active'),
('Siddharth Verma', 'siddharth.v@example.com', '9722446616', 'BTech_CSE', 2, 'Male', 'Active'),
('Diya Kapadia', 'diya.kapadia@example.com', '9899114417', 'BTech_IT', 3, 'Female', 'Active'),
('Varun Panchal', 'varun.panchal@example.com', '9822668818', 'BCA', 1, 'Male', 'Active'),
('Bhavya Rawal', 'bhavya.rawal@example.com', '9711883319', 'BTech_AI', 2, 'Male', 'Inactive')
ON DUPLICATE KEY UPDATE name=VALUES(name);
