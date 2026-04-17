<?php
$start=microtime(true);
try {
  $pdo = new PDO("mysql:host=localhost;port=3307;dbname=PHDHRM;charset=utf8mb4", "root", "");
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->query("SELECT 1")->fetch();
  echo "db_connect_and_select_ms=" . round((microtime(true)-$start)*1000,2) . PHP_EOL;
} catch (Throwable $e) {
  echo "db_error=" . $e->getMessage() . PHP_EOL;
}
