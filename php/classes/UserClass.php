<?php
// classes/UserClass.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception; // Ensure database connection is included


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
            $secret = bin2hex(random_bytes(16));

            $insertStmt = $this->conn->prepare(
                "INSERT INTO users (username, email, password_hash, verification_secret) VALUES (:username, :email, :password_hash, :verification_secret)"
            );

            $insertStmt->bindParam(':username', $username, PDO::PARAM_STR);
            $insertStmt->bindParam(':email', $email, PDO::PARAM_STR);
            $insertStmt->bindParam(':password_hash', $hashedPassword, PDO::PARAM_STR);
            $insertStmt->bindParam(':verification_secret', $secret, PDO::PARAM_STR);
            
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

    public function getUserEmailById(int $id): ?string {
        try {
            $stmt = $this->conn->prepare("SELECT email FROM users WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            // Fetch the email directly as a string
            $email = $stmt->fetchColumn();
            return ($email !== false) ? $email : null; // fetchColumn returns false if no row
        } catch (PDOException $e) {
            error_log("Error fetching user email by ID: " . $e->getMessage());
            return null;
        }
    }

    public function getSecretById(int $id): ?string {
        try {
            $stmt = $this->conn->prepare("SELECT verification_secret FROM users WHERE id = :id AND verified = 0");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && isset($result['verification_secret'])) {
                return $result['verification_secret'];
            }
            return null; // User not found by ID, or already verified, or secret missing/null
        } catch (PDOException $e) {
            error_log("Database error fetching verification secret by ID: " . $e->getMessage());
            return null;
        }
    }

    public function getVerificationSecretByEmail(string $email): ?string {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null; // Invalid email format
        }

        try {
            $stmt = $this->conn->prepare("SELECT verification_secret FROM users WHERE email = :email AND verified = 0");
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && isset($result['verification_secret'])) {
                return $result['verification_secret'];
            }
            return null; // User not found by email, or already verified, or secret missing/null
        } catch (PDOException $e) {
            error_log("Database error fetching verification secret by email: " . $e->getMessage());
            return null;
        }
    }

    public function verifyUserByEmail(string $email): array {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'message' => 'Invalid email address format.'];
        }

        try {
            $stmt = $this->conn->prepare("UPDATE users SET verified = 1 WHERE email = :email");
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                return ['status' => 'success', 'message' => 'User email verified successfully.'];
            } else {
                return ['status' => 'error', 'message' => 'User not found or already verified.'];
            }

        } catch (PDOException $e) {
            error_log("Database error during user verification: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'A database error occurred.'];
        } catch (Exception $e) {
            error_log("General error during user verification: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'An unexpected error occurred.'];
        }
    }

    public function getUserVerified(int $id) {
        try {
            $stmt = $this->conn->prepare("SELECT verified FROM users WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            // Option 1: fetch(PDO::FETCH_ASSOC) - What you have now
            $result = $stmt->fetch(PDO::FETCH_ASSOC); 
            if ($result && isset($result['verified'])) {
                return $result['verified']; 
            }
            return null; 
        } catch (PDOException $e) {
            error_log("Error fetching user verification status by ID: " . $e->getMessage());
            return null;
        }
    }

    public function getUserById(int $id): ?array {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching user by ID: " . $e->getMessage());
            return null;
        }
    }

    public function updateProfileImage(int $userId, string $imagePath): bool {
        try {
            $stmt = $this->conn->prepare("UPDATE users SET profile_pic_url = :profile_image WHERE id = :id");
            $stmt->bindParam(':profile_image', $imagePath, PDO::PARAM_STR);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating profile image: " . $e->getMessage());
            return false;
        }
    }

    public function updateProfile(int $userId, string $aboutMe, string $email, string $username): bool {
        try {
            $stmt = $this->conn->prepare("UPDATE users SET about_me = :about_me, email = :email, username = :username WHERE id = :id");
            $stmt->bindParam(':about_me', $aboutMe, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating profile: " . $e->getMessage());
            return false;
        }
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool {
        try {
            // Fetch the current password hash
            $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE id = :id");
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return false;
            }

            // Hash the new password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update the password
            $updateStmt = $this->conn->prepare("UPDATE users SET password_hash = :new_password WHERE id = :id");
            $updateStmt->bindParam(':new_password', $newPasswordHash, PDO::PARAM_STR);
            $updateStmt->bindParam(':id', $userId, PDO::PARAM_INT);
            return $updateStmt->execute();
        } catch (PDOException $e) {
            error_log("Error changing password: " . $e->getMessage());
            return false;
        }
    }

    public function sendMailjetVerificationEmail($toEmail, $secret, $toName = '') {
    try {

        $scriptName = $_SERVER['SCRIPT_NAME'];
        $basePath = dirname($scriptName);
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USERNAME'];
        $mail->Password   = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['SMTP_PORT'];
        
        $mail->setFrom('dariokaradzin@karadzin-dario-sssd.xyz', 'Dario Karadzin');
        $mail->addAddress($toEmail, $toName ?: $toEmail);

        $mail->isHTML(true); 

        $mail->Subject = 'Verify Your Email Address';

        $verificationLink = "http://".$_SERVER['HTTP_HOST'].$basePath."/verify-email?email=" . 
                            urlencode($toEmail) . 
                            "&secret=" . urlencode($secret);

        $mail->Body = '
            <p>Hello ' . htmlspecialchars($toName ?: 'User') . ',</p>
            <p>Thank you for registering! Please verify your email by clicking the link below:</p>
            <p><a href="' . $verificationLink . '">Verify Email</a></p>
            <p>Or copy this URL into your browser:<br>' . $verificationLink . '</p>
            <p>If you didn\'t request this, please ignore this email.</p>
        ';

        $mail->AltBody = "Hello,\n\nPlease verify your email by visiting this link:\n" . 
                         $verificationLink . "\n\nIf you didn't request this, ignore this email.";

        $mail->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
        }
    }

}