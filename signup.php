<?php
session_start();
require_once 'db.php';
$error = '';
$step = 1;
$dev_otp_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'request_otp') {
        $name = trim($_POST['name'] ?? '');
        $user_id = trim($_POST['user-id'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $Level = trim($_POST['Level'] ?? '');
        $date = trim($_POST['date'] ?? date('Y-m-d'));
        $password = trim($_POST['password'] ?? '');
        $cpassword = trim($_POST['confirm-password'] ?? '');
        $other = trim($_POST['other'] ?? '');

        // Check if user already exists
        $stmtCheck = $pdo->prepare("SELECT `user-id` FROM `land-table` WHERE `user-id` = ? OR `email` = ?");
        $stmtCheck->execute([$user_id, $email]);
        if ($stmtCheck->rowCount() > 0) {
            $error = "User ID or Email already exists.";
        } else if ($password !== $cpassword) {
            $error = "Passwords do not match.";
        } else if (empty($password)) {
            $error = "Password cannot be empty.";
        } else {
            $otp = rand(100000, 999999);
            $_SESSION['signup_data'] = compact('name', 'user_id', 'email', 'phone', 'address', 'Level', 'date', 'password', 'other', 'otp');
            $dev_otp_message = "DEV MODE: Your OTP is $otp";
            $step = 2;
        }
    } else if (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
        $step = 2;
        $entered_otp = trim($_POST['otp'] ?? '');
        if (isset($_SESSION['signup_data']) && $entered_otp == $_SESSION['signup_data']['otp']) {
            $data = $_SESSION['signup_data'];
            $password_hashed = password_hash($data['password'], PASSWORD_DEFAULT);
            try {
                $sql = "INSERT INTO `land-table` (`Name`, `user-id`, `email`, `phone`, `address`, `Level`, `Date`, `password`, `other`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data['name'], $data['user_id'], $data['email'], $data['phone'], $data['address'], $data['Level'], $data['date'], $password_hashed, $data['other']]);
                unset($_SESSION['signup_data']);
                header('Location: index.php?message=' . urlencode('Sign-up successful. Please login.'));
                exit;
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
                $step = 1; // Fallback
            }
        } else {
            $error = "Invalid OTP. Please try again.";
            $dev_otp_message = "DEV MODE: Your OTP is " . ($_SESSION['signup_data']['otp'] ?? '');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=3">
    <title>Sign Up | SECL Land Outsee</title>
</head>
<body>
    <img src="coal.png" alt="secl" class="se">
    <div class="container">
        <h1>Sign Up</h1>
        
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        
        <?php if ($dev_otp_message): ?>
            <div style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; margin-bottom: 20px; border-radius: 4px; text-align: center; font-weight: bold;">
                <?php echo htmlspecialchars($dev_otp_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <p>Fill out the form to register your details.</p>
        <form action="signup.php" method="post">
            <input type="hidden" name="action" value="request_otp">
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" placeholder="Enter your name" required>
                </div>
                <div class="form-group">
                    <label for="user-id">User ID:</label>
                    <input type="text" id="user-id" name="user-id" placeholder="Enter your user ID" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number:</label>
                    <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" required>
                </div>
                <div class="form-group">
                    <label for="address">Address:</label>
                    <input type="text" id="address" name="address" placeholder="Enter your address" required>
                </div>
                <div class="form-group">
                    <label for="Level">Level:</label>
                    <select id="Level" name="Level" required>
                        <option value="" disabled selected>Select a level</option>
                        <option value="unit">unit</option>
                        <option value="area">area</option>
                        <option value="LnR">LnR</option>
                        <option value="man">man</option>
                        <option value="comm">comm</option>
                        <option value="HR">HR</option>
                        <option value="CMD">CMD</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">Date:</label>
                    <input type="date" id="date" name="date" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" placeholder="Create a password" required>
                </div>
                <div class="form-group">
                    <label for="confirm-password">Confirm Password:</label>
                    <input type="password" id="confirm-password" name="confirm-password" placeholder="Confirm your password" required>
                </div>
                <div class="form-group full-width">
                    <label for="other">Other details:</label>
                    <textarea id="other" name="other" placeholder="Any additional information"></textarea>
                </div>
                <div class="form-group full-width">
                    <button class="btn" type="submit">Request OTP</button>
                </div>
            </div>
        </form>
        
        <?php else: ?>
        <p>An OTP has been generated. Please enter it below to verify your account.</p>
        <form action="signup.php" method="post">
            <input type="hidden" name="action" value="verify_otp">
            <div class="form-group full-width">
                <label for="otp">Enter 6-Digit OTP:</label>
                <input type="text" id="otp" name="otp" placeholder="123456" required style="font-size: 24px; text-align: center; letter-spacing: 5px;">
            </div>
            <div class="form-group full-width">
                <button class="btn" type="submit" style="background:#28a745;">Verify OTP & Register</button>
            </div>
        </form>
        <?php endif; ?>

        <p style="text-align:center; margin-top: 15px;"><a href="index.php">Back to homepage</a></p>
    </div>
</body>
</html>
