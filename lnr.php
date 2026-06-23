<?php
require_once 'db.php';
require_login();

// Access Control
if ($_SESSION['Level'] !== 'LnR') {
    die("Access denied. This page is only for LnR level users.");
}

// Handle Form Submission (Update only for LnR)


// Fetch all users for the dynamic dropdown
$stmt_users = $pdo->query("SELECT `user-id`, `Level` FROM `land-table` ORDER BY `Level`, `user-id`");
$all_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

// Fetch records assigned to the current user
$stmt = $pdo->prepare("SELECT * FROM `data` WHERE `forward-to` = ? OR `created_by` = ? ORDER BY `id` DESC");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Attach logs to each record
foreach ($records as &$row) {
    $log_stmt = $pdo->prepare("SELECT * FROM `log` WHERE `record_id` = ? ORDER BY `Sno.` ASC");
    $log_stmt->execute([$row['id']]);
    $row['logs'] = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($row);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LnR Portal - SECL Land Outsee</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="background:#f9f9f9;">

    <?php render_nav(); ?>

    <div class="record-container">
        <h2>Records Forwarded to LnR</h2>
        <p style="text-align:left; color:#666; font-size:14px; margin-bottom:15px;">Click 'Edit' on a record below to review and forward it.</p>
        <div style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0056b3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="min-width: 20px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" id="tableSearch" onkeyup="searchTable()" placeholder="Search records by Name, Aadhar, Email..." style="width: 100%; max-width: 350px; padding: 10px 15px; border: 1px solid #cbd5e0; border-radius: 6px; outline: none; transition: box-shadow 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Father's Name</th>
                        <th>D.O.B</th>
                        <th>Email</th>
                        <th>Aadhar</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Notified Land</th>
                        <th>Acquired Land</th>
                        <th>Needed Documents</th>
                        <th>Documents</th>
                        <th>Forward To</th>
                    <th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                    <tr><td colspan="15" style="text-align:center;">No records currently assigned to LnR.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($records as $row): ?>
                    <tr>
                        
                        
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['father-name']) ?></td>
                        <td><?= htmlspecialchars($row['d-o-b']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['aadhar']) ?></td>
                        <td><?= htmlspecialchars($row['phone']) ?></td>
                        <td><?= htmlspecialchars($row['address']) ?></td>
                        <td><?= htmlspecialchars($row['notified-land']) ?></td>
                        <td><?= htmlspecialchars($row['acquired-land']) ?></td>
                        <td><?= htmlspecialchars($row['needed-documents']) ?></td>
                        <td>
                            <?php 
                            $docs_html = [];
                            for ($i = 1; $i <= 10; $i++) {
                                if (!empty($row['doc'.$i])) {
                                    $docs_html[] = "<a href='" . htmlspecialchars($row['doc'.$i]) . "' target='_blank'>Doc $i</a>";
                                }
                            }
                            echo !empty($docs_html) ? implode(" | ", $docs_html) : "N/A";
                            ?>
                        </td>
                        <td><strong style="color: #0056b3;"><?= htmlspecialchars($row['forward-to']) ?></strong></td>
                    <td>
                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn" style="background:#007bff; text-decoration:none; display:inline-block; padding: 4px 8px; font-size:12px;">Edit</a>
                        </td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<script>
function searchTable() {
    var input, filter, table, tr, td, i, j, txtValue;
    input = document.getElementById("tableSearch");
    filter = input.value.toUpperCase();
    table = document.querySelector("table");
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) {
        tr[i].style.display = "none";
        td = tr[i].getElementsByTagName("td");
        for (j = 0; j < td.length; j++) {
            if (td[j]) {
                txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                    break;
                }
            }
        }
    }
}
</script>
</body>
</html>
