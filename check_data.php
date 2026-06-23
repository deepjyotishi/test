<?php
require 'db.php';
$stmt = $pdo->query('SELECT id, name FROM data');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
