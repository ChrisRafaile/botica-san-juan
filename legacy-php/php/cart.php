<?php
session_start();
include 'config.php';

$action = $_GET['action'];
$id = $_GET['id'];

switch ($action) {
    case 'add':
        if (!isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id] = 0;
        }
        $_SESSION['cart'][$id]++;
        break;

    case 'remove':
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]--;
            if ($_SESSION['cart'][$id] <= 0) {
                unset($_SESSION['cart'][$id]);
            }
        }
        break;

    case 'empty':
        unset($_SESSION['cart']);
        break;
}

header("Location: ../cart.php");
exit();
?>
