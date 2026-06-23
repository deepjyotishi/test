<?php
require_once 'db.php';
require_login();

// 1. Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    
    $name = $_POST['name'] ?? '';
    $father_name = $_POST['father-name'] ?? '';
    $dob = $_POST['d-o-b'] ?? '';
    $email = $_POST['email'] ?? '';
    $aadhar = $_POST['aadhar'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $notified_land = $_POST['notified-land'] ?? '';
    $acquired_land = $_POST['acquired-land'] ?? '';
    $needed_documents = $_POST['needed-documents'] ?? 'No';
    $forward_to = empty($id) ? '' : ($_POST['forward-to'] ?? '');
    $comment = $_POST['comment'] ?? '';

    for ($i=1; $i<=10; $i++) { ${'doc'.$i.'_path'} = ''; }

    if (!empty($id)) {
        $stmt_existing = $pdo->prepare("SELECT doc1, doc2, doc3, doc4, doc5, doc6, doc7, doc8, doc9, doc10 FROM `data` WHERE id = ?");
        $stmt_existing->execute([$id]);
        $existing_data = $stmt_existing->fetch(PDO::FETCH_ASSOC);
        if ($existing_data) {
            for ($i=1; $i<=10; $i++) {
                ${'doc'.$i.'_path'} = $existing_data['doc'.$i] ?? '';
            }
        }
    }

    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $uploaded_files = [];
    for ($i=1; $i<=10; $i++) {
        $doc_key = 'doc'.$i;
        if (empty(${'doc'.$i.'_path'}) && !empty($_FILES[$doc_key]['name'])) {
            $filename = basename($_FILES[$doc_key]['name']);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($ext === 'zip') {
                die("<div style='padding: 20px; font-family: sans-serif; color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; max-width: 600px; margin: 40px auto; text-align: center;'><h2>Security Block</h2><p>Error: <strong>.zip</strong> files are not permitted for security reasons.</p><a href='javascript:history.back()' style='display: inline-block; margin-top: 15px; padding: 10px 20px; background: #0056b3; color: white; text-decoration: none; border-radius: 4px;'>Go Back</a></div>");
            }
            ${'doc'.$i.'_path'} = $upload_dir . time() . '_'.$i.'_' . $filename;
            move_uploaded_file($_FILES[$doc_key]['tmp_name'], ${'doc'.$i.'_path'});
            $uploaded_files[] = $filename;
        }
    }
    $file_addition_note = "";
    if (!empty($uploaded_files)) {
        $file_addition_note = "\n[Added file(s): " . implode(", ", $uploaded_files) . "]";
    }

    if (!empty($id)) {
        $sql = "UPDATE `data` SET 
                `name` = ?, `father-name` = ?, `d-o-b` = ?, `email` = ?, 
                `aadhar` = ?, `phone` = ?, `address` = ?, `notified-land` = ?, 
                `acquired-land` = ?, `needed-documents` = ?, `forward-to` = ?,
                `doc1`=?, `doc2`=?, `doc3`=?, `doc4`=?, `doc5`=?, `doc6`=?, `doc7`=?, `doc8`=?, `doc9`=?, `doc10`=? 
                WHERE `id` = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $father_name, $dob, $email, $aadhar, $phone, $address, $notified_land, $acquired_land, $needed_documents, $forward_to, $doc1_path, $doc2_path, $doc3_path, $doc4_path, $doc5_path, $doc6_path, $doc7_path, $doc8_path, $doc9_path, $doc10_path, $id]);

        // Insert into log table
        $log_sql = "INSERT INTO `log` (`record_id`, `name`, `forwarded_from`, `forwarded_to`, `forwarded_date`, `comment`) VALUES (?, ?, ?, ?, ?, ?)";
        $log_stmt = $pdo->prepare($log_sql);
        $final_comment = trim($comment . $file_addition_note);
        $log_stmt->execute([$id, $name, $_SESSION['user_id'], $forward_to, date('Y-m-d H:i:s'), $final_comment]);
    } else {
        $sql = "INSERT INTO `data` (`name`, `father-name`, `d-o-b`, `email`, `aadhar`, `phone`, `address`, `notified-land`, `acquired-land`, `needed-documents`, `forward-to`, `doc1`, `doc2`, `doc3`, `doc4`, `doc5`, `doc6`, `doc7`, `doc8`, `doc9`, `doc10`, `created_at`, `created_by`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $created_at = $_POST['created_at'] ?? date('Y-m-d');
        $stmt->execute([$name, $father_name, $dob, $email, $aadhar, $phone, $address, $notified_land, $acquired_land, $needed_documents, $forward_to, $doc1_path, $doc2_path, $doc3_path, $doc4_path, $doc5_path, $doc6_path, $doc7_path, $doc8_path, $doc9_path, $doc10_path, $created_at, $_SESSION['user_id']]);
        $new_id = $pdo->lastInsertId();
        
        $log_sql = "INSERT INTO `log` (`record_id`, `name`, `forwarded_from`, `forwarded_to`, `forwarded_date`, `comment`) VALUES (?, ?, ?, ?, ?, ?)";
        $log_stmt = $pdo->prepare($log_sql);
        $initial_comment = trim('Initial record created. ' . $comment . $file_addition_note);
        $log_stmt->execute([$new_id, $name, $_SESSION['user_id'], $forward_to, date('Y-m-d H:i:s'), $initial_comment]);
    }
    
    $redirect_url = strtolower($_SESSION['Level']) . '.php';
    header("Location: " . $redirect_url);
    exit;
}

// 2. Handle GET
$id = $_GET['id'] ?? '';
$data = [];
$logs = [];

if (!empty($id)) {
    $stmt = $pdo->prepare("SELECT * FROM `data` WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data) {
        $log_stmt = $pdo->prepare("SELECT * FROM `log` WHERE `record_id` = ? ORDER BY `Sno.` ASC");
        $log_stmt->execute([$id]);
        $logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        die("Record not found.");
    }
}

// Fetch users for dropdown
$stmt_users = $pdo->query("SELECT `user-id`, `Level` FROM `land-table` ORDER BY `Level`, `user-id`");
$all_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

function val($key, $default='') {
    global $data;
    return isset($data[$key]) ? htmlspecialchars($data[$key]) : htmlspecialchars($default);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Record - SECL Land Outsee</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="background:#f9f9f9;">
    <?php render_nav(); ?>
    <div class="record-container" style="max-width: 1400px; margin: 20px auto;">
        <div style="margin-bottom: 20px;">
            <a href="<?= htmlspecialchars(strtolower($_SESSION['Level'])) ?>.php" class="btn" style="background:#6c757d; text-decoration:none; display:inline-block; padding:8px 16px;">&larr; Back to Dashboard</a>
        </div>
        <h2><?= !empty($id) ? "Review Record #".htmlspecialchars($id) : "Create New Record" ?></h2>
        
        <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-start;">
            <div style="flex: 2; min-width: 300px; background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #cbd5e0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= val('id') ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" value="<?= val('name') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Father's Name</label>
                            <input type="text" name="father-name" value="<?= val('father-name') ?>">
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="d-o-b" value="<?= val('d-o-b') ?>">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= val('email') ?>">
                        </div>
                        <div class="form-group">
                            <label>Aadhar</label>
                            <input type="text" name="aadhar" value="<?= val('aadhar') ?>">
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" value="<?= val('phone') ?>">
                        </div>
                        <div class="form-group full-width">
                            <label>Address</label>
                            <textarea name="address" rows="2"><?= val('address') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Notified Land (Hectares)</label>
                            <input type="text" name="notified-land" value="<?= val('notified-land') ?>">
                        </div>
                        <div class="form-group">
                            <label>Acquired Land (Hectares)</label>
                            <input type="text" name="acquired-land" value="<?= val('acquired-land') ?>">
                        </div>
                        <div class="form-group">
                            <label>Needed Documents</label>
                            <select name="needed-documents">
                                <option value="Yes" <?= val('needed-documents') == 'Yes' ? 'selected' : '' ?>>Yes</option>
                                <option value="No" <?= val('needed-documents') == 'No' ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>
                        
                        <div id="dynamic-docs-container" style="grid-column: 1 / -1; background: #eef2f5; padding: 15px; border-radius: 6px;">
                            <h4 style="margin-top: 0; margin-bottom: 10px; color: #333;">Documents</h4>
                            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <?php 
                            $last_filled = 0;
                            for($k=1; $k<=10; $k++) {
                                if (val('doc'.$k)) $last_filled = $k;
                            }
                            ?>
                            <?php for($i=1; $i<=10; $i++): ?>
                            <?php 
                            $doc_val = val('doc'.$i); 
                            $is_hidden = ($i > $last_filled + 1) ? 'display:none !important;' : '';
                            ?>
                            <div class="form-group doc-group" id="doc<?= $i ?>-group" style="margin-bottom:10px; <?= $is_hidden ?>">
                                <label style="font-weight:600; display:block; margin-bottom:5px;">Document <?= $i ?> 
                                    <?php if($doc_val): ?>
                                        <a href="<?= $doc_val ?>" target="_blank" style="color: #28a745; font-size: 12px; text-decoration: none;">(View Uploaded)</a>
                                    <?php endif; ?>
                                </label>
                                <?php if(!$doc_val): ?>
                                    <input type="file" name="doc<?= $i ?>" id="doc<?= $i ?>" onchange="showNextDoc(<?= $i ?>)" style="width:100%;">
                                <?php else: ?>
                                    <span style="color: #6c757d; font-size: 14px;">Already uploaded</span>
                                <?php endif; ?>
                            </div>
                            <?php endfor; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($id)): ?>
                        <div class="form-group">
                            <label>Forward To</label>
                            <select name="forward-to" id="forward-to">
                                <?php $current_fwd = val('forward-to'); ?>
                                <option value="" disabled <?= empty($current_fwd) ? 'selected' : '' ?>>Select User to Forward</option>
                                <?php foreach ($all_users as $u): ?>
                                    <?php if ($u['user-id'] === $_SESSION['user_id']) continue; ?>
                                    <option value="<?= htmlspecialchars($u['user-id']) ?>" <?= $current_fwd == $u['user-id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['user-id']) ?> (<?= htmlspecialchars($u['Level']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group full-width">
                            <label>Comment (Added to Log)</label>
                            <textarea name="comment" rows="2" required></textarea>
                        </div>
                        
                        <div class="form-group full-width" style="margin-top: 15px;">
                            <button type="submit" class="btn full-width" style="padding: 12px; font-size: 16px; font-weight: bold;"><?= !empty($id) ? "Update & Forward Data" : "Create New Data" ?></button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- History section -->
            <div style="flex: 1; min-width: 350px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #cbd5e0; position: sticky; top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <h3 style="margin-top: 0; color: #0056b3; border-bottom: 2px solid #0056b3; padding-bottom: 10px; margin-bottom: 15px;">Record History</h3>
                <div style="max-height: 70vh; overflow-y: auto;">
                    <?php if (empty($id)): ?>
                        <p style="color:#666;">Creating a new record. No history yet.</p>
                    <?php elseif (empty($logs)): ?>
                        <p style="color:#666;">No previous history found.</p>
                    <?php else: ?>
                        <ul style="padding-left: 0; list-style-type: none; margin-bottom:0; color:#555; font-size:14px;">
                        <?php foreach($logs as $index => $log): ?>
                            <?php
                            $commentText = !empty($log['comment']) ? (string)$log['comment'] : '';
                            $displayComment = nl2br(htmlspecialchars($commentText));
                            ?>
                            <li style="margin-bottom: 15px; padding: 15px; background: #fff; border-left: 4px solid #17a2b8; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                <div style="font-size: 12px; color: #888; margin-bottom: 5px;"><?= htmlspecialchars($log['forwarded_date'] ?? '') ?></div>
                                <strong><?= htmlspecialchars($log['forwarded_from'] ?? 'System') ?></strong> &rarr; <strong><?= htmlspecialchars($log['forwarded_to'] ?? 'Unknown') ?></strong><br>
                                <div style="margin-top: 8px; color:#333;"><?= $displayComment ?></div>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>
</body>
<script>
function showNextDoc(currentDocIndex) {
    var nextDocIndex = currentDocIndex + 1;
    if (nextDocIndex <= 10) {
        var nextGroup = document.getElementById('doc' + nextDocIndex + '-group');
        if (nextGroup) {
            nextGroup.style.setProperty('display', 'flex', 'important');
        }
    }
}
</script>
</html>
