<?php

session_start();
include("../dbcon.php");

if(!isset($_SESSION['user_session'])){

    header("location:index.php");
}

   if(isset($_POST['update'])){

$id = $_POST['id'];
$pin = $_POST['pin'];

  $sql=" UPDATE stock SET fifo='$pin' WHERE id = '$id' ";

   $result =mysqli_query($con,$sql);

   echo "<h1>...LOADING</h1>";

   if($result){  

    echo "<script type='text/javascript'>window.top.location='pin_druges.php?invoice_number=$invoice_number'</script>";
          
   }
}
 
?>