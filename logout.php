<?php

session_start();

if(!isset($_SESSION['user_session'])){

    header("location:index.php");
}

session_start();
session_unset();
session_destroy();
header('location:main_page/index.php');

?><!-- For more projects: Visit codeastro.com  -->