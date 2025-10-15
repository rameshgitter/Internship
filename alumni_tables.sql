-- Alumni Info Table
CREATE TABLE alumni_info (
    alumni_id VARCHAR(30) PRIMARY KEY,
    first_name VARCHAR(20) NOT NULL,
    last_name VARCHAR(20) NOT NULL,
    email VARCHAR(50) NOT NULL,
    mobile_no VARCHAR(15),
    batch VARCHAR(9), -- e.g., '2021-2025'
    graduation_year INT(4),
    degree VARCHAR(10),
    department_id VARCHAR(5),
    current_position VARCHAR(50),
    current_organization VARCHAR(100),
    location VARCHAR(50),
    profile_picture VARCHAR(255),
    bio TEXT,
    linkedin_url VARCHAR(255),
    other_links TEXT
);

-- Alumni Events Table
CREATE TABLE alumni_events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    event_date DATE,
    location VARCHAR(100),
    created_by VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Alumni Achievements Table
CREATE TABLE alumni_achievements (
    achievement_id INT AUTO_INCREMENT PRIMARY KEY,
    alumni_id VARCHAR(30),
    title VARCHAR(100) NOT NULL,
    description TEXT,
    achievement_date DATE,
    FOREIGN KEY (alumni_id) REFERENCES alumni_info(alumni_id)
);

-- Alumni Feedback Table
CREATE TABLE alumni_feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    alumni_id VARCHAR(30),
    message TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alumni_id) REFERENCES alumni_info(alumni_id)
);

-- Alumni Pending Table (for self-registration/approval)
CREATE TABLE alumni_pending (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(20),
    last_name VARCHAR(20),
    email VARCHAR(50),
    graduation_year INT(4),
    degree VARCHAR(10),
    department_id VARCHAR(5),
    mobile_no VARCHAR(15),
    proof_document VARCHAR(255),
    status VARCHAR(20) DEFAULT 'Pending', -- Pending, Approved, Rejected
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
