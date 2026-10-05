<?php

require_once __DIR__ . '/Sessions.php';
require_once __DIR__ . '/../config/database.php';

class Auth
{
    private $mysqli;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function register($name, $email, $password)
    {
        // Check if email already exists
        $stmt = $this->mysqli->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $stmt->close();
            return "Email already exists.";
        }

        $stmt->close();

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // All new registrations are customers
        $role = "customer";
        $isActive = 1;

        $stmt = $this->mysqli->prepare(
            "INSERT INTO users (name, email, password, role, is_active)
             VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssssi",
            $name,
            $email,
            $hashedPassword,
            $role,
            $isActive
        );

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }

        $stmt->close();

        return "Registration failed.";
    }

    public function login($email, $password)
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, name, email, password, role, is_active
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            $stmt->close();
            return "Invalid email or password.";
        }

        $user = $result->fetch_assoc();

        $stmt->close();

        // Check active status
        if ((int) $user['is_active'] !== 1) {
            return "Your account is inactive.";
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            return "Invalid email or password.";
        }

        // Regenerate session ID after successful login
        session_regenerate_id(true);

        // Store the logged-in user in the existing session variables
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Login Status
    |--------------------------------------------------------------------------
    */

    public function isLoggedIn()
    {
        return isset(
            $_SESSION['user_id'],
            $_SESSION['user_role']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Role Checks
    |--------------------------------------------------------------------------
    */

    public function isAdmin()
    {
        return $this->isLoggedIn()
            && $_SESSION['user_role'] === 'admin';
    }

    public function isCustomer()
    {
        return $this->isLoggedIn()
            && $_SESSION['user_role'] === 'customer';
    }

    /*
    |--------------------------------------------------------------------------
    | User Data
    |--------------------------------------------------------------------------
    */

    public function getUserId()
    {
        return $_SESSION['user_id'] ?? null;
    }

    public function getUserName()
    {
        return $_SESSION['user_name'] ?? null;
    }

    public function getUserEmail()
    {
        return $_SESSION['user_email'] ?? null;
    }

    public function getUserRole()
    {
        return $_SESSION['user_role'] ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout()
    {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }
}