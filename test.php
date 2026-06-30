<?php require 'db.php'; $stmt=$pdo->query('SELECT comment FROM log WHERE comment LIKE ''%Added file%'' LIMIT 5'); print_r($stmt->fetchAll(PDO::FETCH_ASSOC)); ?>
