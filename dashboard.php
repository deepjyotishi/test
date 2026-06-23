<?php
require_once 'db.php';
require_login();

$userId = $_SESSION['user_id'];
$level = $_SESSION['Level'];

// Define mapping from Level to specific page
$pages = [
    'unit' => 'unit.php',
    'area' => 'area.php',
    'LnR'  => 'lnr.php',
    'man'  => 'man.php',
    'comm' => 'comm.php',
    'HR'   => 'hr.php',
    'CMD'  => 'cmd.php',
];

$portalPage = $pages[$level] ?? null;

// Fetch some basic stats based on the user level
$pendingCount = 0;
if ($level === 'unit') {
    // Unit creates records, so "pending" could mean total records they created, but we don't have created_by field.
    // So we just show total records in DB for now.
    $stmt = $pdo->query("SELECT COUNT(*) FROM `data`");
    $pendingCount = $stmt->fetchColumn();
} else if ($portalPage) {
    // For other levels, count records forwarded to them
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `data` WHERE `forward-to` = ?");
    $stmt->execute([$level]);
    $pendingCount = $stmt->fetchColumn();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Dashboard | SECL Land Outsee</title>
</head>
<body style="background:#f9f9f9;">
    <?php render_nav(); ?>
    
    <div class="record-container" style="text-align: center; padding: 40px;">
        <img src="coal.png" alt="secl" style="width: 150px; margin-bottom: 20px;">
        <h1 style="color: #0056b3;">Welcome, <?php echo htmlspecialchars($userId); ?></h1>
        <p style="font-size: 18px;">You are logged in with access level: <strong><?php echo htmlspecialchars(strtoupper($level)); ?></strong></p>
        
        <div style="margin: 30px auto; max-width: 400px; background: #e9ecef; padding: 20px; border-radius: 8px;">
            <h3 style="margin-bottom: 10px;">Activity Overview</h3>
            <p style="font-size: 24px; font-weight: bold; color: #dc3545;"><?php echo $pendingCount; ?></p>
            <p>Records currently assigned to your level</p>
        </div>

        <?php if ($portalPage): ?>
            <a href="<?php echo htmlspecialchars($portalPage); ?>" class="btn" style="padding: 15px 30px; font-size: 18px;">Enter Your Workflow Portal</a>
        <?php else: ?>
            <p class="error">Your access level does not have a mapped portal. Please contact an administrator.</p>
        <?php endif; ?>
    </div>
</body>
</html>
