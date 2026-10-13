<?php
session_start();
	require '../dbcon.php';

	// Add products into the cart table
	if (isset($_POST['pid'])) {
	  $invoice_number= $_GET['invoice_number'];
	  $pid = $_POST['pid'];
	  $pname = $_POST['pname'];
	  $pprice = $_POST['pprice'];
	  $profite = $_POST['profite'];
	  $pcode = $_POST['pcode'];
	  $pqty = $_POST['pqty'];
	  $total_price = $pprice * $pqty;

$select_sql= "SELECT * FROM stock where medicine_name = '$pname'";

$select_query= mysqli_query($con,$select_sql);

	while($row = mysqli_fetch_array($select_query)){
		$medicine_name =$row['medicine_name'];
		$category      = $row['category'];
		$quantity      = $row['quantity'];
        $sell_type     = $row['sell_type'];
		$cost          = $row['selling_price'];
		$profit        = $row['profit_price'];
		$expire_date   = $row['expire_date'];

	}

	$update_sql="UPDATE stock SET used_quantity = used_quantity + '$pqty' , remain_quantity = remain_quantity - '$pqty' where medicine_name = '$pname'";//*******UPDATING Stock if Sale Made **********

	$update_query = mysqli_query($con,$update_sql);

	$select_sql = "SELECT * FROM stock where medicine_name = '$pname'";

	$select_query = mysqli_query($con,$select_sql);

	while($row = mysqli_fetch_array($select_query)){

		  $quantity = $row['remain_quantity'];
	}
	   echo "<h1>....LOADING</h1>";

	   if($quantity <= 0){

	   	   $update_quantity_sql = "UPDATE stock set status =  'Unavailable' where medicine_name = '$pname'";//********Updating Unavailable if medicine_qty  is zero***********

	   	   $update_quantity_query = mysqli_query($con,$update_quantity_sql);
	   }

	$amount = $pqty*$cost;
	$profit_amt = $profit*$pqty;

               $insert_sql ="INSERT INTO on_hold values('','$invoice_number','$medicine_name','$category','$expire_date','$pqty','$sell_type','$cost','$amount','$profit_amt','$date')";//*****INSERTING INTO on_HOLD TABLE*******

	$insert_query = mysqli_query($con,$insert_sql);

	if($insert_query){

     header("location:product/view.php?invoice_number=$invoice_number");
  
     // echo "<script type='text/javascript'>window.location.href = salling.php?invoice_number=$invoice_number '</script>";
	}else{
      echo"Not found!!";
	}

  }

?>