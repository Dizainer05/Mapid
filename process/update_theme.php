<?php
session_start();
if (isset($_GET['theme'])) {
    $_SESSION['dark_mode'] = ($_GET['theme'] === 'true');
}
?>