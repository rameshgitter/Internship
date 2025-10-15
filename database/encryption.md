# Encryption and Security

This document outlines the encryption and security measures implemented in the application to protect sensitive data.

## Password Hashing

The application uses the `password_hash()` function with the `PASSWORD_DEFAULT` algorithm (bcrypt) to securely hash passwords before storing them in the database. This prevents plain-text passwords from being exposed in case of a data breach.  The `password_verify()` function is used to compare a given password with the stored hash during authentication.

```php
// Example of password hashing during registration:
$password = $_POST['password'];
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Example of password verification during login:
if (password_verify($password, $user['passcode'])) {
    // Password is correct
}
```

## Session Handling

Sessions are used to maintain user authentication and store user-specific data during their interaction with the application.

-   `session_start()` is called at the beginning of each page to initialize the session.
-   `session_regenerate_id(true)` is used after successful login to prevent session fixation attacks.
-   `$_SESSION` variables are used to store user information like `user_id`, `role`, and other relevant data.
-   `session_unset()` and `session_destroy()` are used during logout to clear and destroy the session.
-   Session timeout is implemented by checking the `last_activity` timestamp. If the user is inactive for a specified time, the session is destroyed.

```php
// Starting a session
session_start();

// Regenerating session ID to prevent fixation attacks
session_regenerate_id(true);

// Storing user data in session
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['role'] = $user['role'];

// Checking for session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) { // 30 minutes
    session_unset();     // unset $_SESSION variable for the run-time 
    session_destroy();   // destroy session data in storage
}
$_SESSION['last_activity'] = time(); // update last activity time stamp
```

## Secure Data Storage Techniques

-   **Prepared Statements:** Prepared statements with parameterized queries are used to prevent SQL injection attacks.  Instead of directly embedding user input into SQL queries, placeholders are used, and the input is passed separately to the database.

    ```php
    // Example of prepared statement
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    ```

-   **HTTPS:** All communication between the client and server should be encrypted using HTTPS to protect data in transit.

-   **Input Validation and Sanitization:** User input is validated and sanitized to prevent Cross-Site Scripting (XSS) and other injection attacks.

-   **Encryption of Sensitive Data:** Besides password hashing, other sensitive data, like Personally Identifiable Information (PII), should be encrypted at rest in the database.
