<?php
require_once 'db.php';
require_login();

$id = $_GET['id'] ?? '';
$error = '';

if (empty($id)) {
    die("Invalid Record ID");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $forward_to = $_POST['forward-to'] ?? '';
    $comment = $_POST['comment'] ?? '';

    if ($forward_to === $_SESSION['user_id']) {
        $error = "You cannot forward a record to yourself.";
    } else if (!empty($forward_to)) {
        // Fetch current record name
        $stmt = $pdo->prepare("SELECT name FROM data WHERE id = ?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn() ?: 'Unknown';

        // Update record
        $updateStmt = $pdo->prepare("UPDATE data SET `forward-to` = ? WHERE id = ?");
        $updateStmt->execute([$forward_to, $id]);

        // Insert log
        $log_sql = "INSERT INTO `log` (`record_id`, `name`, `forwarded_from`, `forwarded_to`, `forwarded_date`, `comment`) VALUES (?, ?, ?, ?, ?, ?)";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([$id, $name, $_SESSION['user_id'], $forward_to, date('Y-m-d H:i:s'), trim($comment)]);

        // Redirect back to dashboard
        header("Location: " . strtolower($_SESSION['Level']) . ".php");
        exit;
    } else {
        $error = "Please select a user to forward to.";
    }
}

// Fetch all users for dropdown
$stmt_users = $pdo->query("SELECT `user-id`, `Level` FROM `land-table` ORDER BY `Level`, `user-id`");
$all_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forward Record | SECL</title>
    <link rel="stylesheet" href="style.css?v=4">
</head>
<body>
    <?php render_nav(); ?>
    <div class="container">
        <h1>Forward Record #<?= htmlspecialchars($id) ?></h1>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form action="forward.php?id=<?= htmlspecialchars($id) ?>" method="post">
            <div class="form-group">
                <label for="forward-to">Forward To:</label>
                <select id="forward-to" name="forward-to" required>
                    <option value="" disabled selected>Select User</option>
                    <?php foreach ($all_users as $u): ?>
                        <?php if ($u['user-id'] === $_SESSION['user_id']) continue; ?>
                        <option value="<?= htmlspecialchars($u['user-id']) ?>">
                            <?= htmlspecialchars(strtoupper($u['Level']) . ' - ' . $u['user-id']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="comment">Forwarding Comment:</label>
                <textarea id="comment" name="comment" placeholder="Add any instructions or notes..."></textarea>
            </div>
            <div class="form-group">
                <button type="submit" class="btn">Forward Record</button>
            </div>
        </form>
        <p style="text-align:center; margin-top:15px;"><a href="javascript:history.back()">Cancel</a></p>
    </div>
</body>
</html>
