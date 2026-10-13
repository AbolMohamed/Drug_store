<?php
session_start();
include("../dbcon.php");

if(!isset($_SESSION['user_session'])){

    header("location:manage_users.php");
}
   if(isset($_POST['update'])){

$id = $_POST['id'];
$name= $_POST['name'];  
$username= $_POST['username'];    
$email=  $_POST['email'];
$mobile = $_POST['mobile'];
$isActive = $_POST['isActive'];  
$usertype = $_POST['usertype'];  
  $sql=" UPDATE tbl_users SET name='$name',username='$username',email='$email', mobile='$mobile', isActive= '$isActive', usertype='$usertype' WHERE id = '$id' ";

   $result =mysqli_query($con,$sql);

   echo "<h1>...LOADING</h1>";

   if($result){  

    echo "<script type='text/javascript'>window.top.location='manage_users.php'</script>";
          
   }
}
 
?>