# Database Design

## Overview

The College Management System uses a normalized relational database design built on MySQL. The database follows a structured approach to store and manage academic data, user information, and administrative workflows.

## Database Schema

![ER Diagram](img/database_schema.png)

## Core Tables

### 1. Index Table (User Credentials)
**Purpose**: Central authentication and role management

```sql
CREATE TABLE `index` (
    user_id VARCHAR(30) NOT NULL PRIMARY KEY,
    passcode VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL,
    change_passcode INT(2) DEFAULT 0
);
```

**Fields**:
- `user_id`: Unique identifier for all users
- `passcode`: Hashed password
- `role`: User role (student, professor, hod, staff)
- `change_passcode`: Password change flag (0=needs change, 1=changed)

### 2. Department Table
**Purpose**: Department information and hierarchy

```sql
CREATE TABLE department (
    department_id VARCHAR(5) PRIMARY KEY,
    department_name VARCHAR(50),
    department_phone INT(10),
    department_email VARCHAR(50),
    department_hod VARCHAR(30),
    CONSTRAINT fk_dept_hod FOREIGN KEY (department_hod) REFERENCES `index`(user_id)
);
```

**Key Features**:
- Links to HOD through foreign key
- Email validation constraint
- Unique department identification

### 3. Student Information Table
**Purpose**: Comprehensive student data management

```sql
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
    CONSTRAINT fk_student_user FOREIGN KEY (student_id) REFERENCES `index`(user_id),
    CONSTRAINT fk_student_dept FOREIGN KEY (department_id) REFERENCES department(department_id)
);
```

**Key Features**:
- Complete personal and academic information
- Guardian details for emergency contacts
- Status tracking (Graduated/Ongoing)
- Multiple validation constraints

### 4. Staff Information Table
**Purpose**: Faculty and staff data management

```sql
CREATE TABLE staff_info (
    id INT(10) PRIMARY KEY AUTO_INCREMENT,
    staff_id VARCHAR(30),
    staff_type VARCHAR(20) NOT NULL,
    department_id VARCHAR(5),
    first_name VARCHAR(20) NOT NULL,
    middle_name VARCHAR(20),
    last_name VARCHAR(20),
    designation VARCHAR(25),
    mobile_no VARCHAR(15),
    email VARCHAR(50),
    doj DATE,
    dol DATE,
    CONSTRAINT fk_staff_user FOREIGN KEY (staff_id) REFERENCES `index`(user_id),
    CONSTRAINT fk_staff_dept FOREIGN KEY (department_id) REFERENCES department(department_id)
);
```

### 5. HOD Table
**Purpose**: Head of Department specific information

```sql
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
    CONSTRAINT fk_hod_user FOREIGN KEY (hod_id) REFERENCES `index`(user_id)
);
```

### 6. Subjects Pool Table
**Purpose**: Master catalog of all available subjects

```sql
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
    PRIMARY KEY (subject_code, year_of_introduction)
);
```

**Key Features**:
- Composite primary key for version control
- Credit and hour tracking
- Level classification (UG/PG/PhD)

### 7. Semester Registration Table
**Purpose**: Student semester enrollment tracking

```sql
CREATE TABLE semester_registration (
    student_id VARCHAR(30),
    semester INT(2) NOT NULL,
    sem_reg_date DATE,
    PRIMARY KEY (student_id),
    CONSTRAINT fk_sem_reg_student FOREIGN KEY (student_id) REFERENCES `index`(user_id)
);
```

### 8. Subjects Offered Table
**Purpose**: Semester-specific subject availability

```sql
CREATE TABLE subjects_offered (
    taught_in VARCHAR(10) NOT NULL,
    subject_semester INT(2) NOT NULL,
    academic_year INT(4),
    session VARCHAR(10)
);
```

### 9. Verification Assignments Table
**Purpose**: Staff verification workflow management

```sql
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
    CONSTRAINT fk_verify_hod FOREIGN KEY (assigned_by_hod) REFERENCES `index`(user_id)
);
```

### 10. Student Marks Table
**Purpose**: Academic performance tracking

```sql
CREATE TABLE student_marks (
    id INT(10) PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(30),
    subject_code VARCHAR(10),
    semester INT(2),
    academic_year VARCHAR(9),
    internal_marks DECIMAL(5,2),
    external_marks DECIMAL(5,2),
    total_marks DECIMAL(5,2),
    grade VARCHAR(2),
    uploaded_by VARCHAR(30),
    upload_date DATE,
    CONSTRAINT fk_marks_student FOREIGN KEY (student_id) REFERENCES `index`(user_id),
    CONSTRAINT fk_marks_uploader FOREIGN KEY (uploaded_by) REFERENCES `index`(user_id)
);
```

## Database Relationships

### Primary Relationships
1. **User-Role Hierarchy**: `index` table serves as the central authentication hub
2. **Department Structure**: Links HOD, staff, and students to departments
3. **Academic Tracking**: Connects students to subjects, semesters, and marks
4. **Verification Workflow**: Links students, staff, and HOD in approval processes

### Foreign Key Constraints
- All user-related tables reference the `index` table
- Department-based relationships maintain organizational structure
- Academic relationships ensure data integrity across semesters and subjects

## Data Integrity Features

### Constraints
- **Check Constraints**: Validate gender, email formats, status values
- **Foreign Key Constraints**: Maintain referential integrity
- **Unique Constraints**: Prevent duplicate entries
- **Not Null Constraints**: Ensure required fields are populated

### Validation Rules
- Email format validation using LIKE patterns
- Semester range validation (1-10)
- Status enumeration for students and verification
- Role-based access control through the index table

## Performance Optimizations

### Indexing Strategy
- Primary keys on all tables for fast lookups
- Foreign key indexes for join operations
- Composite indexes on frequently queried combinations

### Query Optimization
- Normalized structure reduces data redundancy
- Efficient join paths between related tables
- Proper data types for storage optimization

## Sample Data

The database includes sample data for testing:
- 5 departments (CSE, ECE, ME, CE, EE)
- 5 users with different roles
- Sample subjects for UG programs
- Test students and staff members

This design ensures scalability, data integrity, and efficient query performance while supporting all the system's functional requirements.
