<?php
require 'db.php';
$stmt = $pdo->query('SELECT * FROM log');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total rows: " . count($rows) . "\n";
foreach ($rows as $row) {
    echo sprintf("Sno: %d | record_id: %d | name: %s | forwarded_date: %s\n", 
        $row['Sno.'], $row['record_id'], $row['name'], $row['forwarded_date']);
}
?>
