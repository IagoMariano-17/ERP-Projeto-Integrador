<?php
if (!isset($_SESSION['usuario_id'])) {

    header('Location: auth/login.php');
    exit;
}
?>