<?php
session_start();
require_once 'db.php';
$error = '';
$debug = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = trim($_POST['user-id'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($user_id === '' || $password === '') {
        $error = 'Please enter both user ID and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT `user-id`, `password`, `Level` FROM `land-table` WHERE `user-id` = ?");
            $stmt->execute([$user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                if (password_verify($password, $row['password']) || $password === $row['password']) {
                    $_SESSION['user_id'] = $row['user-id'];
                    $_SESSION['Level']   = $row['Level'];
                    
                    $pages = [
                        'unit' => 'unit.php',
                        'area' => 'area.php',
                        'LnR'  => 'lnr.php',
                        'man'  => 'man.php',
                        'comm' => 'comm.php',
                        'HR'   => 'hr.php',
                        'CMD'  => 'cmd.php',
                    ];

                    if (isset($pages[$row['Level']])) {
                        header('Location: ' . $pages[$row['Level']]);
                        exit;
                    } else {
                        $error = 'Access denied: unrecognized account level.';
                    }
                } else {
                    $error = 'Invalid user ID or password.';
                }
            } else {
                $error=  'Invalid user ID or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=2">
    <title>Login | SECL Land Outsee</title>
</head>
<body class="login-page">
    <img src="coal.png" alt="secl" class="se">
    <div class="container">
        <h1>SECL Land Outsee login</h1>
        <p>Enter your user ID and password.</p>
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form action="login.php" method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label for="user-id">User ID:</label>
                    <input type="text" id="user-id" name="user-id" placeholder="Enter your user ID" required>
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <div class="form-group full-width">
                    <button class="btn">Login</button>
                </div>
            </div>
        </form>
        <p style="text-align:center; margin-top: 15px;"><a href="forgot_password.php">Forgot Password?</a></p>
        <p style="text-align:center; margin-top: 15px;"><a href="index.php">Back to homepage</a></p>
    </div>
</body>
</html>
