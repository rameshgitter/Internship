# Workflow

This document outlines the workflows for different user roles within the system.

## User Roles

-   **Student:** Enrolls in courses, submits assignments, views grades.
-   **Professor:** Creates courses, manages assignments, grades submissions.
-   **HOD (Head of Department):** Approves courses, manages professors, oversees departmental activities.
-   **Staff:** Manages user accounts, handles administrative tasks.

## User Flows

### Student

```mermaid
sequenceDiagram
    participant Student
    participant System

    Student->>System: Login
    alt Successful Login
        System->>Student: Display Dashboard
        Student->>System: Enroll in Course
        System->>System: Update Enrollment
        Student->>System: Submit Assignment
        System->>System: Store Assignment
        Student->>System: View Grades
        System->>Student: Logout
    else Failed Login
        System->>Student: Display Error Message
    end
```

### Professor

```mermaid
sequenceDiagram
    participant Professor
    participant System

    Professor->>System: Login
    alt Successful Login
        System->>Professor: Display Dashboard
        Professor->>System: Create Course
        System->>System: Store Course Details
        Professor->>System: Manage Assignments
        Professor->>System: Grade Submissions
        Professor->>System: Logout
    else Failed Login
        System->>Professor: Display Error Message
    end
```

### HOD

```mermaid
sequenceDiagram
    participant HOD
    participant System

    HOD->>System: Login
    alt Successful Login
        System->>HOD: Display Dashboard
        HOD->>System: Approve Courses
        HOD->>System: Manage Professors
        HOD->>System: Logout
    else Failed Login
        System->>HOD: Display Error Message
    end
```

### Staff

```mermaid
sequenceDiagram
    participant Staff
    participant System

    Staff->>System: Login
    alt Successful Login
        System->>Staff: Display Dashboard
        Staff->>System: Manage User Accounts
        Staff->>System: Handle Administrative Tasks
        Staff->>System: Logout
    else Failed Login
        System->>Staff: Display Error Message
    end
```

## Step-by-Step Process

### Login

1.  User enters username and password.
2.  System authenticates the user against the database.
3.  If authentication is successful, the system retrieves the user's role and displays the appropriate dashboard.
4.  If authentication fails, the system displays an error message.

### Registration

1.  User clicks on the "Register" link.
2.  System displays the registration form.
3.  User fills out the registration form with their details.
4.  System validates the user input.
5.  If the input is valid, the system creates a new user account in the database.
6.  System sends a verification email to the user's email address.

### Verification

1.  User clicks on the verification link in the email.
2.  System verifies the user's email address.
3.  System activates the user's account.
4.  User can now log in to the system.
