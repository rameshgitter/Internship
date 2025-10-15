-- Clean Database Setup - Drop and Recreate Everything
DROP DATABASE IF EXISTS college_management;
CREATE DATABASE college_management;
USE college_management;

-- 1. Index table (renamed from user_credentials)
CREATE TABLE `index` (
    user_id VARCHAR(30) NOT NULL PRIMARY KEY,
    passcode VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL,
    change_passcode INT(2) DEFAULT 0  -- 0 = needs to change, 1 = changed
);

-- 2. Department table
CREATE TABLE department (
    department_id VARCHAR(5) PRIMARY KEY,
    department_name VARCHAR(50),
    department_phone INT(10),
    department_email VARCHAR(50),
    department_hod VARCHAR(30),
    CONSTRAINT chk_dept_email CHECK (department_email LIKE '%@%'),
    CONSTRAINT fk_dept_hod FOREIGN KEY (department_hod) REFERENCES `index`(user_id)
);

-- 3. HOD table
CREATE TABLE hod (
    hod_id VARCHAR(30) PRIMARY KEY,
    first_name VARCHAR(20) NOT NULL,
    middle_name VARCHAR(20),
    last_name VARCHAR(20),
    designation VARCHAR(25),
    mobile_no VARCHAR(15),
    email VARCHAR(50),
    department_name VARCHAR(50),
    doj DATE,
    dol DATE,
    CONSTRAINT chk_hod_email CHECK (email LIKE '%@%'),
    CONSTRAINT fk_hod_user FOREIGN KEY (hod_id) REFERENCES `index`(user_id)
);

-- 4. Student info table
CREATE TABLE student_info (
    id INT(10) PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(30),
    student_type VARCHAR(10) NOT NULL,
    department_id VARCHAR(5),
    gender VARCHAR(10),
    first_name VARCHAR(20) NOT NULL,
    middle_name VARCHAR(20),
    last_name VARCHAR(20),
    dob DATE,
    mobile_no VARCHAR(15) NOT NULL,
    email VARCHAR(50) NOT NULL,
    address VARCHAR(50),
    city VARCHAR(20),
    state VARCHAR(20),
    pin INT(10),
    country VARCHAR(20),
    guardian_first_name VARCHAR(20) NOT NULL,
    guardian_middle_name VARCHAR(20),
    guardian_last_name VARCHAR(20),
    guardian_mobile_no VARCHAR(15) NOT NULL,
    guardian_email VARCHAR(50),
    enrolment_date DATE,
    blood_group VARCHAR(5),
    registration_no VARCHAR(30),
    registration_date DATE,
    category_of_phd VARCHAR(30),
    status VARCHAR(20) NOT NULL,
    graduate_date DATE,
    CONSTRAINT chk_student_gender CHECK (gender IN ('Male', 'Female', 'Other')),
    CONSTRAINT chk_student_email CHECK (email LIKE '%@%'),
    CONSTRAINT chk_guardian_email CHECK (guardian_email LIKE '%@%'),
    CONSTRAINT chk_student_status CHECK (status IN ('Graduated', 'Ongoing')),
    CONSTRAINT fk_student_user FOREIGN KEY (student_id) REFERENCES `index`(user_id),
    CONSTRAINT fk_student_dept FOREIGN KEY (department_id) REFERENCES department(department_id)
);

-- 5. Subjects pool table
CREATE TABLE subjects_pool (
    taught_in VARCHAR(10) NOT NULL,
    subject_type VARCHAR(20),
    subject_code VARCHAR(10),
    year_of_introduction DATE,
    subject_name VARCHAR(50),
    subject_semester INT(2) NOT NULL,
    credit INT(2),
    lecture_hours INT(2),
    tutorial_hours INT(2),
    practical_hours INT(2),
    PRIMARY KEY (subject_code, year_of_introduction),
    CONSTRAINT chk_taught_in CHECK (taught_in IN ('UG', 'PG', 'PhD', 'Other')),
    CONSTRAINT chk_semester CHECK (subject_semester > 0 AND subject_semester < 11)
);

-- 6. Semester registration table
CREATE TABLE semester_registration (
    student_id VARCHAR(30),
    semester INT(2) NOT NULL,
    sem_reg_date DATE,
    PRIMARY KEY (student_id),
    CONSTRAINT chk_sem_reg_semester CHECK (semester > 0 AND semester < 11),
    CONSTRAINT fk_sem_reg_student FOREIGN KEY (student_id) REFERENCES `index`(user_id)
);

-- 7. Subjects offered table
CREATE TABLE subjects_offered (
    taught_in VARCHAR(10) NOT NULL,
    subject_semester INT(2) NOT NULL,
    academic_year INT(4),
    session VARCHAR(10),
    CONSTRAINT chk_offered_taught_in CHECK (taught_in IN ('UG', 'PG', 'PhD', 'Other')),
    CONSTRAINT chk_offered_semester CHECK (subject_semester > 0 AND subject_semester < 11)
);

-- 8. Staff/Employee table (for professors and employees)
CREATE TABLE staff_info (
    id INT(10) PRIMARY KEY AUTO_INCREMENT,
    staff_id VARCHAR(30),
    staff_type VARCHAR(20) NOT NULL, -- Professor, Employee, etc.
    department_id VARCHAR(5),
    first_name VARCHAR(20) NOT NULL,
    middle_name VARCHAR(20),
    last_name VARCHAR(20),
    designation VARCHAR(25),
    mobile_no VARCHAR(15),
    email VARCHAR(50),
    doj DATE,
    dol DATE,
    CONSTRAINT chk_staff_email CHECK (email LIKE '%@%'),
    CONSTRAINT fk_staff_user FOREIGN KEY (staff_id) REFERENCES `index`(user_id),
    CONSTRAINT fk_staff_dept FOREIGN KEY (department_id) REFERENCES department(department_id)
);

-- 9. Verification table (for HOD assigning staff for verification)
CREATE TABLE verification_assignments (
    id INT(10) PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(30),
    assigned_staff_id VARCHAR(30),
    assigned_by_hod VARCHAR(30),
    verification_type VARCHAR(20) DEFAULT 'Document',
    assignment_date DATE,
    verification_status VARCHAR(20) DEFAULT 'Pending',
    verification_date DATE,
    remarks TEXT,
    CONSTRAINT fk_verify_student FOREIGN KEY (student_id) REFERENCES `index`(user_id),
    CONSTRAINT fk_verify_staff FOREIGN KEY (assigned_staff_id) REFERENCES `index`(user_id),
    CONSTRAINT fk_verify_hod FOREIGN KEY (assigned_by_hod) REFERENCES `index`(user_id),
    CONSTRAINT chk_verify_status CHECK (verification_status IN ('Pending', 'Verified', 'Rejected'))
);

-- 10. Marks table for storing student marks
CREATE TABLE student_marks (
    id INT(10) PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(30),
    subject_code VARCHAR(10),
    subject_name VARCHAR(100),
    credit INT(2),
    letter_grade VARCHAR(3),
    total_grade_point INT(4),
    semester INT(2),
    academic_year VARCHAR(9), -- e.g., "2023-2024"
    internal_marks DECIMAL(5,2),
    external_marks DECIMAL(5,2),
    total_marks DECIMAL(5,2),
    grade VARCHAR(2),
    uploaded_by VARCHAR(30), -- Professor who uploaded
    upload_date DATE,
    CONSTRAINT fk_marks_student FOREIGN KEY (student_id) REFERENCES `index`(user_id),
    CONSTRAINT fk_marks_uploader FOREIGN KEY (uploaded_by) REFERENCES `index`(user_id)
);

-- Insert sample departments
INSERT INTO department (department_id, department_name, department_phone, department_email) VALUES
('CSE', 'Computer Science and Engineering', 332668456, 'cse@iiests.ac.in'),
('ECE', 'Electronics and Communication Engineering', 332668457, 'ece@iiests.ac.in'),
('ME', 'Mechanical Engineering', 332668458, 'me@iiests.ac.in'),
('CE', 'Civil Engineering', 332668459, 'ce@iiests.ac.in'),
('EE', 'Electrical Engineering', 332668460, 'ee@iiests.ac.in');

-- Insert sample users with password = userid
INSERT INTO `index` (user_id, passcode, role, change_passcode) VALUES
('HOD001', 'HOD001', 'hod', 0),
('PROF001', 'PROF001', 'professor', 0),
('STAFF001', 'STAFF001', 'staff', 0),
('STU001', 'STU001', 'student', 0),
('STU002', 'STU002', 'student', 0);

-- Insert sample HOD
INSERT INTO hod (hod_id, first_name, last_name, designation, mobile_no, email, department_name, doj) VALUES
('HOD001', 'Dr. Rajesh', 'Kumar', 'Head of Department', '9876543210', 'rajesh.kumar@iiests.ac.in', 'Computer Science and Engineering', '2020-01-01');

-- Update department with HOD
UPDATE department SET department_hod = 'HOD001' WHERE department_id = 'CSE';

-- Insert sample employees
INSERT INTO staff_info (staff_id, staff_type, department_id, first_name, last_name, designation, mobile_no, email, doj) VALUES
('PROF001', 'Professor', 'CSE', 'Dr. Priya', 'Sharma', 'Professor', '9876543211', 'priya.sharma@iiests.ac.in', '2018-07-01'),
('STAFF001', 'Employee', 'CSE', 'Amit', 'Singh', 'Administrative Staff', '9876543212', 'amit.singh@iiests.ac.in', '2019-03-15');

-- Insert sample students
INSERT INTO student_info (student_id, student_type, department_id, gender, first_name, last_name, dob, mobile_no, email, guardian_first_name, guardian_last_name, guardian_mobile_no, enrolment_date, status) VALUES
('STU001', 'UG', 'CSE', 'Male', 'Rahul', 'Verma', '2002-05-15', '987654321', 'rahul.verma@student.iiests.ac.in', 'Suresh', 'Verma', '987654322', '2023-08-01', 'Ongoing'),
('STU002', 'UG', 'CSE', 'Female', 'Priya', 'Patel', '2002-08-20', '987654323', 'priya.patel@student.iiests.ac.in', 'Ramesh', 'Patel', '987654324', '2023-08-01', 'Ongoing');

-- Insert sample subjects
INSERT INTO subjects_pool (taught_in, subject_type, subject_code, year_of_introduction, subject_name, subject_semester, credit, lecture_hours, tutorial_hours, practical_hours) VALUES
('UG', 'Core', 'CS101', '2020-01-01', 'Programming Fundamentals', 1, 4, 3, 1, 2),
('UG', 'Core', 'CS102', '2020-01-01', 'Data Structures', 2, 4, 3, 1, 2),
('UG', 'Core', 'CS201', '2020-01-01', 'Database Management Systems', 3, 4, 3, 1, 2),
('UG', 'Core', 'CS202', '2020-01-01', 'Operating Systems', 4, 4, 3, 1, 2),
('UG', 'Elective', 'CS301', '2020-01-01', 'Machine Learning', 5, 3, 3, 0, 2),
('UG', 'Elective', 'CS302', '2020-01-01', 'Web Development', 5, 3, 3, 0, 2);