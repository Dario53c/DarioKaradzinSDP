<?php
// classes/UserClass.php

class User {
    
    private $conn;

    public function __construct(PDO $dbConnection) {
        $this->conn = $dbConnection;
    }


    // Login method to authenticate user
    public function login(string $email, string $password): array {
        try {
            $stmt = $this->conn->prepare("SELECT id, username, password_hash FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (password_verify($password, $user['password_hash'])) {
                    return [
                        'status' => 'success',
                        'message' => 'Login successful!',
                        'user_data' => [
                            'id' => $user['id'],
                            'username' => $user['username']
                        ]
                    ];
                } else {
                    return ['status' => 'error', 'message' => 'Incorrect password!'];
                }
            } else {
                return ['status' => 'error', 'message' => 'Email not found!'];
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'An internal server error occurred.'];
        }
    }
    

    // Register method to create a new user
    public function register(string $username, string $email, string $password): array {
        try {
            $checkStmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
            $checkStmt->bindParam(':username', $username, PDO::PARAM_STR);
            $checkStmt->bindParam(':email', $email, PDO::PARAM_STR);
            $checkStmt->execute();

            if ($checkStmt->fetchColumn() > 0) {
                return ['status' => 'error', 'message' => 'Username or email already taken!'];
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insertStmt = $this->conn->prepare(
                "INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)"
            );

            $insertStmt->bindParam(':username', $username, PDO::PARAM_STR);
            $insertStmt->bindParam(':email', $email, PDO::PARAM_STR);
            $insertStmt->bindParam(':password_hash', $hashedPassword, PDO::PARAM_STR);
            
            if ($insertStmt->execute()) {
                return ['status' => 'success', 'message' => 'Registration successful!'];
            } else {
                return ['status' => 'error', 'message' => 'Failed to register user.'];
            }

        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'An internal server error occurred.'];
        }
    }
}