<?php
$pdo = new PDO("mysql:host=localhost;port=3307;dbname=PHDHRM;charset=utf8mb4", "root", "");
$row = $pdo->query("SELECT logo, sidebar_logo, login_image, title FROM applications ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
print_r($row);
