<?php
if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
}
session_destroy();
header("location: minor.php");
exit;
?>

