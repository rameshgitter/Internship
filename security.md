# Security

This document outlines the security measures implemented in the application to protect against common web vulnerabilities.

## Role-Based Access Control (RBAC)

The application implements role-based access control to restrict access to sensitive functionality based on the user's role.  Different user roles (student, professor, HOD, staff) have access to different dashboards and functionalities.

-   The `$_SESSION['role']` variable is used to store the user's role after successful login.
-   Each page checks the user's role and redirects them to the appropriate dashboard or displays an error message if they don't have the required permissions.

```php
// Example of role-based access control
session_start();
if ($_SESSION['role'] !== 'professor') {
    header('Location: unauthorized.php');
    exit();
}
```

## Authentication Flow

The authentication flow involves the following steps:

1.  User submits their User ID, password, and role on the `login.php` page.
2.  The system retrieves the user's record from the `index` table based on the provided User ID and role.
3.  The system verifies the user's password using `password_verify()` against the stored hash.
4.  If the credentials are valid, the system regenerates the session ID using `session_regenerate_id(true)` to prevent session fixation attacks.
5.  The system stores the user's `user_id` and `role` in the `$_SESSION` variable.
6.  The user is redirected to the appropriate dashboard based on their role.

## CSRF/XSS Prevention

The application implements several measures to prevent CSRF and XSS attacks:

-   **Prepared Statements:** Prepared statements with parameterized queries are used to prevent SQL injection attacks, a common prerequisite for XSS.

    ```php
    // Example of prepared statement
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    ```

-   **Output Encoding:** The `htmlspecialchars()` function is used to encode output, preventing XSS attacks by ensuring that user-supplied data is treated as text, not code.

    ```php
    // Example of output encoding
    <?= htmlspecialchars($userInput) ?>
    ```

-   **Input Validation:** Input validation is performed to ensure that user input matches expected formats and lengths.

-   While not explicitly found, implementing **CSRF tokens** on forms would significantly enhance security by preventing cross-site request forgery attacks. Example:

    ```php
    // Generating a CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Verifying the CSRF token on form submission
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
    ```