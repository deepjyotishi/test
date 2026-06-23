<?php
require 'db.php';
try {
    echo "Altering table to re-cluster...\n";
    
    // Step 1: Add a key on Sno. first, so it continues to have a key to support AUTO_INCREMENT
    try {
        $pdo->exec("ALTER TABLE `log` ADD KEY `idx_sno` (`Sno.`)");
        echo "Successfully added idx_sno key.\n";
    } catch (PDOException $e) {
        echo "Key idx_sno already exists or: " . $e->getMessage() . "\n";
    }

    // Step 2: Drop the existing primary key and create composite primary key on (record_id, Sno.)
    try {
        $pdo->exec("ALTER TABLE `log` DROP PRIMARY KEY, ADD PRIMARY KEY (`record_id`, `Sno.`)");
        echo "Successfully changed primary key to composite (record_id, Sno.).\n";
    } catch (PDOException $e) {
        echo "Error altering primary key: " . $e->getMessage() . "\n";
    }

    // Show final status
    $stmt = $pdo->query("SHOW COLUMNS FROM `log`");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    $stmt2 = $pdo->query("SHOW INDEX FROM `log`");
    print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
    
} catch (PDOException $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}
?>
