# Database Queries

This document outlines the SQL queries used in the application, grouped by their purpose.

## Authentication

*   `SELECT * FROM `index` WHERE user_id = ? AND role = ?`
    *   Purpose: This query retrieves user data from the `index` table based on the provided `user_id` and `role`.
    *   Parameters: `user_id`, `role`
    *   Explanation: This query is used to verify if a user exists with the given credentials. The `passcode` (password) is later verified using `password_verify()`.

*   `SELECT * FROM student_info WHERE student_id = ?`
    *   Purpose: Retrieves student-specific information.
    *   Parameters: `user_id` (used as `student_id`)
    *   Explanation: After successful authentication from the `index` table, this query fetches additional details about the student from the `student_info` table.

*   `SELECT * FROM staff_info WHERE staff_id = ? AND staff_type = 'Professor'`
    *   Purpose: Retrieves professor-specific information.
    *   Parameters: `user_id` (used as `staff_id`)
    *   Explanation: After successful authentication, this query fetches professor details from `staff_info`. It also checks if the `staff_type` is 'Professor'.

*   `SELECT * FROM hod WHERE hod_id = ?`
    *   Purpose: Retrieves HOD-specific information.
    *   Parameters: `user_id` (used as `hod_id`)
    *   Explanation: Retrieves HOD details after authentication.

*   `SELECT * FROM staff_info WHERE staff_id = ? AND staff_type = 'Staff'`
    *   Purpose: Retrieves staff-specific information.
    *   Parameters: `user_id` (used as `staff_id`)
    *   Explanation: Retrieves staff details from `staff_info` after authentication. It checks if the `staff_type` is 'Staff'.

## Password Reset

*   `SELECT u.* FROM users u WHERE u.enrollment_no = ? AND u.email = ?`
    *   Purpose: This query checks if a user exists in the `users` table with the provided `enrollment_no` and `email`.
    *   Parameters: `enrollment_no`, `email`
    *   Explanation: Used to verify the user's identity before allowing them to reset their password.

*   `UPDATE users SET password = ? WHERE enrollment_no = ?`
    *   Purpose: This query updates the user's password in the `users` table.
    *   Parameters: `password` (hashed), `enrollment_no`
    *   Explanation: This query is executed after the user has successfully verified their identity and provided a new password. The password should be hashed before being stored in the database.

## Change Password

*   `SELECT passcode FROM \`index\` WHERE user_id = ?`
    *   Purpose: Retrieves the hashed password from the `index` table for the given `user_id`.
    *   Parameters: `user_id`
    *   Explanation: Used to verify the user's current password before allowing them to change it.

*   `UPDATE \`index\` SET passcode = ?, change_passcode = 1 WHERE user_id = ?`
    *   Purpose: Updates the password and `change_passcode` flag in the `index` table.
    *   Parameters: `passcode` (new hashed password), `user_id`
    *   Explanation: Updates the user's password in the `index` table and sets `change_passcode` to 1, indicating that the user has changed their password.
