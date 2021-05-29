<?php
    $dsn = 'mysql:host=localhost;dbname=StockAnalytics2';
    $username = 'StockAnalytics';
    $password = 'password';

    try {
        $db = new PDO($dsn, $username, $password);
    } catch (PDOException $e) {
        $error_message = $e->getMessage();
        include('database_error.php');
        exit();
    }
?>
