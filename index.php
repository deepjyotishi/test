<?php
$message = isset($_GET['message']) ? trim($_GET['message']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=4">
    <title>SECL Land Outsee | Welcome</title>
    <style>
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .hero-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(16px);
            padding: 60px 40px;
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 600px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.5);
            animation: fadeInUp 0.8s ease-out backwards;
        }
        .hero-logo {
            width: 120px;
            margin-bottom: 20px;
        }
        .hero h1 {
            font-size: 42px;
            color: #0f172a;
            font-weight: 800;
            margin-bottom: 15px;
            letter-spacing: -1px;
        }
        .hero p {
            font-size: 18px;
            color: #64748b;
            margin-bottom: 40px;
        }
        .hero .btn {
            padding: 16px 32px;
            font-size: 16px;
            margin: 0 10px;
            min-width: 150px;
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            box-shadow: none;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
    <div class="hero">
        <div class="hero-card">
            <img src="coal.png" alt="SECL Logo" class="hero-logo">
            <h1>SECL Land Outsee</h1>
            <p>The premium portal for efficient land record management, tracking, and inter-departmental forwarding.</p>
            
            <?php if ($message): ?>
                <div class="message" style="margin-bottom: 30px;"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <div>
                <a class="btn" href="login.php">Login to Portal</a>
                <a class="btn btn-secondary" href="signup.php">Create Account</a>
            </div>
        </div>
    </div>
</body>
</html>
