<?php
session_start();
require_once 'db.php';
$error = '';
$success = '';
$step = 1;
$dev_otp_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'request_otp') {
        $user_id = trim($_POST['user-id'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $new_password = trim($_POST['new-password'] ?? '');

        if ($user_id === '' || $email === '' || $new_password === '') {
            $error = 'Please fill out all fields.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT `user-id` FROM `land-table` WHERE `user-id` = ? AND `email` = ?");
                $stmt->execute([$user_id, $email]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $otp = rand(100000, 999999);
                    $_SESSION['reset_data'] = compact('user_id', 'email', 'new_password', 'otp');
                    
                    // In a production environment, send this via mail()
                    // mail($email, "Password Reset OTP", "Your OTP is $otp");
                    error_log("Password Reset OTP for $email: $otp");
                    
                    $dev_otp_message = "An OTP has been sent to your registered email address.";
                    $step = 2;
                } else {
                    $error = 'No matching User ID and Email found.';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } else if (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
        $step = 2;
        $entered_otp = trim($_POST['otp'] ?? '');
        if (isset($_SESSION['reset_data']) && $entered_otp == $_SESSION['reset_data']['otp']) {
            $data = $_SESSION['reset_data'];
            $hashed = password_hash($data['new_password'], PASSWORD_DEFAULT);
            try {
                $updateStmt = $pdo->prepare("UPDATE `land-table` SET `password` = ? WHERE `user-id` = ?");
                $updateStmt->execute([$hashed, $data['user_id']]);
                $success = 'Password successfully updated. You can now login.';
                unset($_SESSION['reset_data']);
                $step = 3; // Success state
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        } else {
            $error = "Invalid OTP. Please try again.";
            // $dev_otp_message = "DEV MODE: Your OTP is " . ($_SESSION['reset_data']['otp'] ?? '');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | File Management</title>
    <link rel="stylesheet" href="style.css?v=3">
</head>
<body class="login-page" style="background:#f9f9f9;">
    <img src="coal.png" alt="secl" class="se">
    <div class="container">
        <h1 style="color: #0056b3;">Reset Password</h1>
        
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <div style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; margin-bottom: 20px; border-radius: 4px; text-align: center; font-weight: bold;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($dev_otp_message && $step === 2): ?>
            <div style="background-color: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; padding: 15px; margin-bottom: 20px; border-radius: 4px; text-align: center; font-weight: bold;">
                <?php echo htmlspecialchars($dev_otp_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <p>Enter your User ID and Email to request a password reset OTP.</p>
        <form action="forgot_password.php" method="post">
            <input type="hidden" name="action" value="request_otp">
            <div class="form-grid">
                <div class="form-group">
                    <label for="user-id">User ID:</label>
                    <input type="text" id="user-id" name="user-id" placeholder="Enter your user ID" required>
                </div>
                <div class="form-group">
                    <label for="email">Registered Email:</label>
                    <input type="email" id="email" name="email" placeholder="Enter your registered email" required>
                </div>
                <div class="form-group full-width">
                    <label for="new-password">New Password:</label>
                    <input type="password" id="new-password" name="new-password" placeholder="Enter new password" required>
                </div>
                <div class="form-group full-width">
                    <button class="btn" style="background:#0056b3;" type="submit">Request OTP</button>
                </div>
            </div>
        </form>

        <?php elseif ($step === 2): ?>
        <p>An OTP has been generated. Please enter it below to confirm your password reset.</p>
        <form action="forgot_password.php" method="post">
            <input type="hidden" name="action" value="verify_otp">
            <div class="form-group full-width">
                <label for="otp">Enter 6-Digit OTP:</label>
                <input type="text" id="otp" name="otp" placeholder="123456" required style="font-size: 24px; text-align: center; letter-spacing: 5px;">
            </div>
            <div class="form-group full-width">
                <button class="btn" type="submit" style="background:#28a745;">Verify OTP & Reset Password</button>
            </div>
        </form>
        <?php endif; ?>

        <p style="text-align:center; margin-top: 15px;"><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>
