<?php
// db.php - Centralized Database Connection
date_default_timezone_set('Asia/Kolkata');

$db_host = 'localhost';
$db_name = 'secl database';
$db_user = 'root'; 
$db_pass = '';    

try {
    // PDO Connection for the application data
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Ensure the `id` column exists so ordering and updates work correctly for data table.
    try {
        $columnCheck = $pdo->query("SHOW COLUMNS FROM `data` LIKE 'id'");
        if ($columnCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
        }
        
        // Ensure the ID starts from 10000
        $pdo->exec("ALTER TABLE `data` AUTO_INCREMENT = 10000");

        $subjectCheck = $pdo->query("SHOW COLUMNS FROM `data` LIKE 'subject'");
        if ($subjectCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `subject` TEXT NULL AFTER `id`");
        }

        $dateCheck = $pdo->query("SHOW COLUMNS FROM `data` LIKE 'date'");
        if ($dateCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `date` DATE NULL AFTER `subject`");
        }

        $doc1Check = $pdo->query("SHOW COLUMNS FROM `data` LIKE 'doc1'");
        if ($doc1Check->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `doc1` VARCHAR(255) NULL");
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `doc2` VARCHAR(255) NULL");
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `doc3` VARCHAR(255) NULL");
        }
        $doc4Check = $pdo->query("SHOW COLUMNS FROM `data` LIKE 'doc4'");
        if ($doc4Check->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `doc4` VARCHAR(255) NULL");
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `doc5` VARCHAR(255) NULL");
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `doc6` VARCHAR(255) NULL");
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `doc7` VARCHAR(255) NULL");
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `doc8` VARCHAR(255) NULL");
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `doc9` VARCHAR(255) NULL");
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `doc10` VARCHAR(255) NULL");
        }
        $createdCheck = $pdo->query("SHOW COLUMNS FROM `data` LIKE 'created_at'");
        if ($createdCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `created_at` DATE NULL");
        }
        
        $createdByCheck = $pdo->query("SHOW COLUMNS FROM `data` LIKE 'created_by'");
        if ($createdByCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `data` ADD COLUMN `created_by` VARCHAR(255) NULL");
        }

        // Create log table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS `log` (
            `Sno.` INT NOT NULL AUTO_INCREMENT,
            `record_id` INT NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `forwarded_from` VARCHAR(50) NULL,
            `forwarded_to` VARCHAR(50) NULL,
            `forwarded_date` DATETIME NOT NULL,
            `comment` TEXT,
            PRIMARY KEY (`record_id`, `Sno.`),
            KEY `idx_sno` (`Sno.`)
        )");

        // Ensure Sno. column exists (handling legacy databases with log_id)
        $snoCheck = $pdo->query("SHOW COLUMNS FROM `log` LIKE 'Sno.'");
        if ($snoCheck->rowCount() === 0) {
            $logIdCheck = $pdo->query("SHOW COLUMNS FROM `log` LIKE 'log_id'");
            if ($logIdCheck->rowCount() > 0) {
                // Rename log_id to Sno. and keep it as auto_increment
                $pdo->exec("ALTER TABLE `log` CHANGE COLUMN `log_id` `Sno.` INT NOT NULL AUTO_INCREMENT");
            } else {
                // Add Sno. column
                $pdo->exec("ALTER TABLE `log` ADD COLUMN `Sno.` INT NOT NULL AUTO_INCREMENT FIRST");
            }
        }

        $colCheck1 = $pdo->query("SHOW COLUMNS FROM `log` LIKE 'forwarded_from'");
        if ($colCheck1->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `log` ADD COLUMN `forwarded_from` VARCHAR(50) NULL");
            $pdo->exec("ALTER TABLE `log` ADD COLUMN `forwarded_to` VARCHAR(50) NULL");
        }

        // Verify primary key clustering
        $pkCheck = $pdo->query("SHOW INDEX FROM `log` WHERE Key_name = 'PRIMARY' ORDER BY Seq_in_index ASC");
        $pkColumns = $pkCheck->fetchAll(PDO::FETCH_ASSOC);
        
        $isClustered = false;
        if (count($pkColumns) === 2 && $pkColumns[0]['Column_name'] === 'record_id' && $pkColumns[1]['Column_name'] === 'Sno.') {
            $isClustered = true;
        }
        
        if (!$isClustered) {
            // Need to drop current PK and create composite PK (record_id, Sno.)
            // Make sure Sno. has an index so it supports AUTO_INCREMENT before dropping PK
            try {
                $pdo->exec("ALTER TABLE `log` ADD KEY `idx_sno` (`Sno.`)");
            } catch (PDOException $e) {
                // Ignore if index already exists
            }
            
            try {
                $pdo->exec("ALTER TABLE `log` DROP PRIMARY KEY, ADD PRIMARY KEY (`record_id`, `Sno.`)");
            } catch (PDOException $e) {
                // Ignore if already adjusted
            }
        }
        
        // Add index on record_id for fast history fetching
        $indexCheck = $pdo->query("SHOW INDEX FROM `log` WHERE Key_name = 'idx_record_id'");
        if ($indexCheck->rowCount() === 0) {
            $pdo->exec("CREATE INDEX `idx_record_id` ON `log` (`record_id`)");
        }
    } catch (PDOException $e) {
        // Table might not exist yet or locked, ignore
    }
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper function to check session and redirect if not logged in
function require_login() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Helper function to render a common navigation bar
function render_nav() {
    $level = $_SESSION['Level'] ?? 'Unknown';
    $role = $_SESSION['role'] ?? 'user';
    $userId = $_SESSION['user_id'] ?? 'User';
    echo '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
          <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
          <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
          <style>
             .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #ced4da !important; border-radius: 4px !important; display: flex !important; align-items: center !important; }
             .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
             .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; }
            .navbar { background-color: #004085; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .nav-brand { font-size: 20px; font-weight: bold; letter-spacing: 1px; }
            .nav-links a { color: white; text-decoration: none; margin-left: 20px; font-weight: 500; transition: color 0.2s; }
            .nav-links a:hover { color: #cce5ff; }
            .nav-btn { background: #0069d9; padding: 8px 15px; border-radius: 4px; border: 1px solid #005cbf; }
            .nav-btn:hover { background: #005cbf; }
            .nav-btn-danger { background: #dc3545; border-color: #c82333; }
            .nav-btn-danger:hover { background: #c82333; }
          </style>
          <script>
            const originalSet = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, "value").set;
            Object.defineProperty(HTMLSelectElement.prototype, "value", {
                set: function(val) {
                    originalSet.call(this, val);
                    if (window.jQuery && $(this).data("select2")) {
                        $(this).trigger("change");
                    }
                }
            });
            $(document).ready(function() {
                if($("#forward-to").length) {
                    $("#forward-to").select2({ width: "100%" });
                }
            });
          </script>
          <div class="navbar" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <div style="flex: 1; text-align: left;">
                <span class="nav-brand"><a href="dashboard.php" style="color:white; text-decoration:none;">File Management</a></span>
            </div>
            <div style="flex: 1; text-align: center;">' . 
            ($role === 'admin' ? '<a href="edit.php" class="nav-btn" style="background:#28a745; display:inline-block; text-decoration:none; padding: 8px 20px; font-weight: bold; border-radius: 4px; color: white;">Create New File</a>' : '') 
            . '</div>
            <div class="nav-links" style="flex: 1; text-align: right; display: flex; justify-content: flex-end; align-items: center;">
                <span style="margin-right: 15px;">Logged in as: ' . htmlspecialchars($userId) . '</span>
                <a href="logout.php" class="nav-btn nav-btn-danger">Logout</a>
            </div>
          </div>';
}
?>
