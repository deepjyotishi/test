<?php
require_once 'db.php';
require_login();

$user_level = $_SESSION['Level'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';
$user_role = $_SESSION['role'] ?? 'user';
$is_admin = ($user_role === 'admin');

// Fetch all users for admin management and general reference
$stmt_users = $pdo->query("SELECT `user-id`, `Level`, `role` FROM `land-table` ORDER BY `Level`, `user-id`");
$all_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

$tab = $_GET['tab'] ?? 'received';
if ($tab === 'users' && !$is_admin) {
    $tab = 'received'; // fallback if not admin
}

$success_msg = '';
$error_msg = '';

if ($is_admin && isset($_GET['msg'])) {
    if ($_GET['msg'] === 'updated') $success_msg = 'User updated successfully!';
    elseif ($_GET['msg'] === 'deleted') $success_msg = 'User deleted successfully!';
}

// Handle User Management Actions (Admin only)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_user') {
        $add_id = trim($_POST['user_id'] ?? '');
        $add_name = trim($_POST['name'] ?? '');
        $add_email = trim($_POST['email'] ?? '');
        $add_phone = trim($_POST['phone'] ?? '');
        $add_address = trim($_POST['address'] ?? '');
        $add_level = $_POST['level'] ?? 'unit';
        $add_role = $_POST['role'] ?? 'user';
        
        if (empty($add_id) || empty($add_name) || empty($add_email)) {
            $error_msg = "User ID, Name, and Email are required.";
        } else {
            $stmtCheck = $pdo->prepare("SELECT `user-id` FROM `land-table` WHERE `user-id` = ? OR `email` = ?");
            $stmtCheck->execute([$add_id, $add_email]);
            if ($stmtCheck->rowCount() > 0) {
                $error_msg = "An account with this User ID or Email already exists.";
            } else {
                $default_password = $add_id . '@123';
                $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
                $date_created = date('Y-m-d');
                
                $sql = "INSERT INTO `land-table` (`Name`, `user-id`, `email`, `phone`, `address`, `Level`, `Date`, `password`, `role`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$add_name, $add_id, $add_email, $add_phone, $add_address, $add_level, $date_created, $hashed_password, $add_role])) {
                    $success_msg = "User created successfully. Default password is: " . htmlspecialchars($default_password);
                } else {
                    $error_msg = "Failed to create user.";
                }
            }
        }
    } elseif ($_POST['action'] === 'update_user') {
        $update_id = $_POST['edit_user_id'] ?? '';
        $update_level = $_POST['edit_level'] ?? '';
        $update_role = $_POST['edit_role'] ?? 'user';
        $update_password = $_POST['edit_password'] ?? '';
        
        if (!empty($update_id) && !empty($update_level)) {
            if (!empty($update_password)) {
                $hash = password_hash($update_password, PASSWORD_DEFAULT);
                $u_stmt = $pdo->prepare("UPDATE `land-table` SET `Level`=?, `role`=?, `password`=? WHERE `user-id`=?");
                $u_stmt->execute([$update_level, $update_role, $hash, $update_id]);
            } else {
                $u_stmt = $pdo->prepare("UPDATE `land-table` SET `Level`=?, `role`=? WHERE `user-id`=?");
                $u_stmt->execute([$update_level, $update_role, $update_id]);
            }
        }
        header("Location: dashboard.php?tab=users&msg=updated");
        exit;
    } elseif ($_POST['action'] === 'delete_user') {
        $delete_id = $_POST['delete_user_id'] ?? '';
        if (!empty($delete_id) && $delete_id !== 'admin') {
            $d_stmt = $pdo->prepare("DELETE FROM `land-table` WHERE `user-id`=?");
            $d_stmt->execute([$delete_id]);
        }
        header("Location: dashboard.php?tab=users&msg=deleted");
        exit;
    }
}

$records = [];
if ($tab === 'received') {
    // Received Files: currently assigned to me
    $stmt = $pdo->prepare("SELECT * FROM `data` WHERE `forward-to` = ? ORDER BY `id` DESC");
    $stmt->execute([$user_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($tab === 'forwarded') {
    // Forwarded Files: I created or forwarded them, but they are NOT currently assigned to me
    $sql = "SELECT d.* FROM `data` d 
            WHERE (d.`created_by` = ? OR d.`id` IN (SELECT `record_id` FROM `log` WHERE `forwarded_from` = ?))
            AND d.`forward-to` != ?
            ORDER BY d.`id` DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_id, $user_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
elseif ($tab === 'created') {
    // Created Files: I created them
    $stmt = $pdo->prepare("SELECT * FROM `data` WHERE `created_by` = ? ORDER BY `id` DESC");
    $stmt->execute([$user_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Attach logs to each file if showing a file table
if ($tab === 'received' || $tab === 'forwarded' || $tab === 'created') {
    foreach ($records as &$row) {
        $log_stmt = $pdo->prepare("SELECT * FROM `log` WHERE `record_id` = ? ORDER BY `Sno.` ASC");
        $log_stmt->execute([$row['id']]);
        $row['logs'] = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($row);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | File Management</title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary-color: #0284c7;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --radius-md: 8px;
            --radius-lg: 12px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }
        .dashboard-layout {
            display: flex;
            width: 98%;
            margin: 20px auto;
            gap: 20px;
        }
        .sidebar {
            flex: 0 0 250px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(16px);
            padding: 20px 0;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(255, 255, 255, 0.5);
            height: fit-content;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li a {
            display: block;
            padding: 12px 20px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 600;
            border-left: 4px solid transparent;
            transition: all 0.2s;
        }
        .sidebar-menu li a:hover {
            background: #f1f5f9;
            color: var(--text-primary);
        }
        .sidebar-menu li a.active {
            background: #e0f2fe;
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }
        .main-content {
            flex: 1;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(16px);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        h2 {
            color: var(--text-primary);
            margin-bottom: 25px;
            font-weight: 700;
            font-size: 24px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 15px;
        }
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
            background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;
        }
        .modal-content {
            background: #fff; padding: 30px; border-radius: var(--radius-lg); max-width: 600px; width: 90%;
            box-shadow: var(--shadow-md); max-height: 90vh; overflow-y: auto;
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.5rem;
        }
        .modal-close {
            background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);
        }
    </style>
</head>
<body style="background:#f9f9f9;">
    <?php render_nav(); ?>
    
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="dashboard.php?tab=received" class="<?= $tab === 'received' ? 'active' : '' ?>">Received Files</a></li>
                <li><a href="dashboard.php?tab=forwarded" class="<?= $tab === 'forwarded' ? 'active' : '' ?>">Forwarded Files</a></li>
                <li><a href="dashboard.php?tab=created" class="<?= $tab === 'created' ? 'active' : '' ?>">Created Files</a></li>
                <li><a href="dashboard.php?tab=track" class="<?= $tab === 'track' ? 'active' : '' ?>">Track File</a></li>
                <?php if ($is_admin): ?>
                <li><a href="dashboard.php?tab=users" class="<?= $tab === 'users' ? 'active' : '' ?>">Manage Users</a></li>
                <?php endif; ?>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <?php if ($tab === 'received' || $tab === 'forwarded' || $tab === 'created'): ?>
                <h2>
                    <?php 
                        if ($tab === 'received') echo 'Received Files (Currently Assigned to You)';
                        elseif ($tab === 'forwarded') echo 'Forwarded Files (Assigned to Others)';
                        elseif ($tab === 'created') echo 'Created Files (Files Created by You)';
                    ?>
                </h2>
                <p style="text-align:left; color:#666; font-size:14px; margin-bottom:15px;">Click 'View File' on a record below to review and forward it.</p>
                <div style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0056b3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="min-width: 20px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="tableSearch" onkeyup="searchTable()" placeholder="Search files by Subject, ID..." style="width: 100%; max-width: 350px; padding: 10px 15px; border: 1px solid #cbd5e0; border-radius: 6px; outline: none; transition: box-shadow 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th><th>Subject</th><th>Date</th><th>Documents</th><th>Forward To</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                            <tr><td colspan="6" style="text-align:center;">No files found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($records as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['id']) ?></td>
                                <td><?= htmlspecialchars($row['subject'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['date'] ?? '') ?></td>
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
                                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn" style="background:#007bff; text-decoration:none; display:inline-block; padding: 4px 8px; font-size:12px;">View File</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($tab === 'users' && $is_admin): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 2rem;">
                    <h2 style="margin: 0; border: none; padding: 0;">Manage Users</h2>
                    <button type="button" class="btn" onclick="openCreateModal()" style="background: var(--primary-color); color: white; padding: 0.5rem 1.25rem; font-weight: 600; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 0.5rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
                        Create New User
                    </button>
                </div>

                <?php if ($success_msg): ?>
                    <div style="background-color: #f0fdf4; color: #15803d; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid #86efac; box-shadow: var(--shadow-sm);">
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div style="background-color: #fef2f2; color: #b91c1c; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid #f87171; box-shadow: var(--shadow-sm);">
                        <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>

                <div style="background: #fff; box-shadow: var(--shadow-md); border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--border-color);">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); background-color: #f8fafc;">
                        <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-primary); font-weight: 600;">Existing User Accounts</h3>
                    </div>
                    
                    <div class="table-responsive" style="margin: 0;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background-color: white;">
                                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; letter-spacing: 0.05em; color: var(--text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--border-color); text-align: left;">User ID</th>
                                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; letter-spacing: 0.05em; color: var(--text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--border-color); text-align: left;">Level / Dept</th>
                                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; letter-spacing: 0.05em; color: var(--text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--border-color); text-align: left;">Role</th>
                                    <th style="padding: 1rem 1.5rem; font-size: 0.8rem; letter-spacing: 0.05em; color: var(--text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--border-color); text-align: left;">Actions</th>
                                </tr>
                            </thead>
                            <tbody style="background-color: white;">
                                <?php foreach ($all_users as $u): ?>
                                <tr style="border-bottom: 1px solid var(--border-color); transition: background-color 0.2s;">
                                    <td style="padding: 1rem 1.5rem;">
                                        <div style="font-weight: 500; color: var(--text-primary);"><?= htmlspecialchars($u['user-id']) ?></div>
                                    </td>
                                    <td style="padding: 1rem 1.5rem;">
                                        <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background-color: #e0e7ff; color: #4338ca;">
                                            <?= htmlspecialchars($u['Level']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem 1.5rem;">
                                        <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background-color: <?= ($u['role'] ?? 'user') === 'admin' ? '#fce7f3; color: #be185d;' : '#dcfce7; color: #15803d;' ?>">
                                            <?= htmlspecialchars(ucfirst($u['role'] ?? 'user')) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem 1.5rem;">
                                        <button class="btn" onclick="editUser('<?= htmlspecialchars($u['user-id']) ?>', '<?= htmlspecialchars($u['Level']) ?>', '<?= htmlspecialchars($u['role'] ?? 'user') ?>')" style="background: #e0f2fe !important; border: 1px solid #bae6fd; color: #0284c7 !important; padding: 0.5rem 1rem; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Create New User Modal -->
                <div id="createUserModal" class="modal-overlay">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 style="margin: 0; font-size: 1.5rem; color: var(--text-primary); border:none; padding:0;">Create New User</h2>
                            <button type="button" class="modal-close" onclick="closeCreateModal()">&times;</button>
                        </div>
                        
                        <form method="POST" action="dashboard.php?tab=users">
                            <input type="hidden" name="action" value="add_user">
                            
                            <div style="display: flex; gap: 1.5rem; margin-bottom: 1.25rem;">
                                <div style="flex: 1;">
                                    <label style="font-weight: 500; margin-bottom: 0.5rem; display: block; color: var(--text-secondary);">User ID <span style="color: red;">*</span></label>
                                    <input type="text" name="user_id" required placeholder="e.g. USER001" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                                    <small style="display: block; margin-top: 0.25rem; color: var(--text-secondary); font-size: 0.75rem;">Password will be auto-generated as UserID@123</small>
                                </div>
                                <div style="flex: 1;">
                                    <label style="font-weight: 500; margin-bottom: 0.5rem; display: block; color: var(--text-secondary);">Full Name <span style="color: red;">*</span></label>
                                    <input type="text" name="name" required placeholder="John Doe" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 1.5rem; margin-bottom: 1.25rem;">
                                <div style="flex: 1;">
                                    <label style="font-weight: 500; margin-bottom: 0.5rem; display: block; color: var(--text-secondary);">Email Address <span style="color: red;">*</span></label>
                                    <input type="email" name="email" required placeholder="john@example.com" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                                </div>
                                <div style="flex: 1;">
                                    <label style="font-weight: 500; margin-bottom: 0.5rem; display: block; color: var(--text-secondary);">Level / Dept <span style="color: red;">*</span></label>
                                    <select name="level" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background-color: #f8fafc;">
                                        <option value="unit">unit</option>
                                        <option value="area">area</option>
                                        <option value="LnR">LnR</option>
                                        <option value="man">man</option>
                                        <option value="comm">comm</option>
                                        <option value="HR">HR</option>
                                        <option value="CMD">CMD</option>
                                    </select>
                                </div>
                            </div>

                            <div style="display: flex; gap: 1.5rem; margin-bottom: 1.25rem;">
                                <div style="flex: 1;">
                                    <label style="font-weight: 500; margin-bottom: 0.5rem; display: block; color: var(--text-secondary);">Role <span style="color: red;">*</span></label>
                                    <select name="role" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background-color: #f8fafc;">
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <div style="flex: 1;">
                                    <label style="font-weight: 500; margin-bottom: 0.5rem; display: block; color: var(--text-secondary);">Phone Number</label>
                                    <input type="tel" name="phone" placeholder="1234567890" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 1.5rem; margin-bottom: 1.25rem;">
                                <div style="flex: 1;">
                                    <label style="font-weight: 500; margin-bottom: 0.5rem; display: block; color: var(--text-secondary);">Address</label>
                                    <input type="text" name="address" placeholder="City, Office" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                                </div>
                            </div>
                            
                            <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                                <button type="button" class="btn" onclick="closeCreateModal()" style="border: 1px solid var(--border-color); background: white !important; color: var(--text-secondary) !important; padding: 0.75rem 1.5rem;">Cancel</button>
                                <button type="submit" class="btn" style="background: var(--primary-color); color: white; padding: 0.75rem 1.5rem; font-weight: 600;">Create Account</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Edit User Modal -->
                <div id="edit-user-modal" class="modal-overlay">
                    <div class="modal-content" style="max-width:400px;">
                        <div class="modal-header">
                            <h3 style="margin: 0; font-size: 1.5rem; color: var(--text-primary); border:none; padding:0;">Edit User</h3>
                            <button type="button" class="modal-close" onclick="document.getElementById('edit-user-modal').style.display='none'">&times;</button>
                        </div>
                        <form method="POST" action="dashboard.php?tab=users">
                            <input type="hidden" name="action" value="update_user">
                            <input type="hidden" name="edit_user_id" id="edit_user_id">
                            
                            <div class="form-group" style="margin-bottom:15px;">
                                <label style="font-weight: 500; color: var(--text-secondary);">User ID</label>
                                <input type="text" id="display_user_id" disabled style="background:#f8fafc; border: 1px solid var(--border-color); padding: 0.75rem; border-radius: var(--radius-md); width:100%;">
                            </div>
                            
                            <div class="form-group" style="margin-bottom:15px;">
                                <label style="font-weight: 500; color: var(--text-secondary);">Level / Dept</label>
                                <select name="edit_level" id="edit_level" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background-color: #f8fafc;">
                                    <option value="unit">unit</option>
                                    <option value="area">area</option>
                                    <option value="LnR">LnR</option>
                                    <option value="man">man</option>
                                    <option value="comm">comm</option>
                                    <option value="HR">HR</option>
                                    <option value="CMD">CMD</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom:15px;">
                                <label style="font-weight: 500; color: var(--text-secondary);">Role</label>
                                <select name="edit_role" id="edit_role" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background-color: #f8fafc;">
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            
                            <div class="form-group" style="margin-bottom:15px;">
                                <label style="font-weight: 500; color: var(--text-secondary);">New Password (leave blank to keep current)</label>
                                <input type="password" name="edit_password" placeholder="***" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                            </div>
                            
                            <div style="display:flex; justify-content: flex-end; gap:10px; margin-top:20px; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                                <button type="button" class="btn" style="border: 1px solid var(--border-color); background: white !important; color: var(--text-secondary) !important; padding: 0.75rem 1.5rem;" onclick="document.getElementById('edit-user-modal').style.display='none'">Cancel</button>
                                <button type="submit" class="btn" style="background: var(--primary-color); color: white; padding: 0.75rem 1.5rem; font-weight: 600;">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                function openCreateModal() {
                    document.getElementById('createUserModal').style.display = 'flex';
                }
                function closeCreateModal() {
                    document.getElementById('createUserModal').style.display = 'none';
                }
                function editUser(id, level, role) {
                    document.getElementById('edit_user_id').value = id;
                    document.getElementById('display_user_id').value = id;
                    document.getElementById('edit_level').value = level;
                    document.getElementById('edit_role').value = role;
                    document.getElementById('edit-user-modal').style.display = 'flex';
                }
                </script>

            <?php elseif ($tab === 'track'): ?>
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 2rem;">
                    <h2 style="margin: 0; border: none; padding: 0;">Track File Movement</h2>
                    <p style="color: var(--text-secondary); font-size: 14px; margin-top: 5px;">Enter a File ID to view its movement history.</p>
                </div>

                <form method="GET" action="dashboard.php" style="display: flex; gap: 10px; margin-bottom: 30px;">
                    <input type="hidden" name="tab" value="track">
                    <input type="text" name="track_id" placeholder="Enter File ID..." required style="max-width: 300px;" value="<?= htmlspecialchars($_GET['track_id'] ?? '') ?>">
                    <button type="submit" class="btn" style="background: var(--primary-color); color: white; padding: 0 1.5rem; font-weight: 600;">Track</button>
                </form>

                <?php
                if (!empty($_GET['track_id'])) {
                    $track_id = trim($_GET['track_id']);
                    // Check if file exists in data
                    $stmtCheck = $pdo->prepare("SELECT `subject` FROM `data` WHERE `id` = ?");
                    $stmtCheck->execute([$track_id]);
                    $file_data = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$file_data) {
                        echo "<div style='background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; border: 1px solid #fca5a5;'>File ID not found.</div>";
                    } else {
                        $stmt = $pdo->prepare("SELECT `forwarded_date`, `forwarded_from`, `forwarded_to` FROM `log` WHERE `record_id` = ? ORDER BY `Sno.` ASC");
                        $stmt->execute([$track_id]);
                        $track_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        echo "<div style='background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; box-shadow: var(--shadow-sm);'>";
                        echo "<h3 style='margin-bottom: 15px; color: var(--text-primary); border-bottom: 1px solid #eee; padding-bottom: 10px;'>Movement History - File #".htmlspecialchars($track_id)."</h3>";
                        if (empty($track_logs)) {
                            echo "<p style='color: var(--text-secondary);'>No movement history available.</p>";
                        } else {
                            echo "<ul style='list-style: none; padding: 0; margin: 0;'>";
                            foreach ($track_logs as $index => $log) {
                                echo "<li style='padding: 15px 0; border-bottom: 1px solid #f1f5f9; display: flex; align-items: flex-start; gap: 15px;'>";
                                echo "<div style='background: #e0f2fe; color: #0284c7; font-weight: bold; padding: 5px 10px; border-radius: 4px; font-size: 12px; min-width: 30px; text-align: center;'>#".($index+1)."</div>";
                                echo "<div>";
                                echo "<div style='font-size: 12px; color: #64748b; margin-bottom: 4px;'>" . htmlspecialchars($log['forwarded_date'] ?? 'Unknown Date') . "</div>";
                                echo "<div style='color: #0f172a; font-size: 15px;'><strong>" . htmlspecialchars($log['forwarded_from'] ?? 'System') . "</strong> &rarr; <strong>" . htmlspecialchars($log['forwarded_to'] ?? 'Unknown') . "</strong></div>";
                                echo "</div>";
                                echo "</li>";
                            }
                            echo "</ul>";
                        }
                        echo "</div>";
                    }
                }
                ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function searchTable() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("tableSearch");
        if(!input) return;
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
