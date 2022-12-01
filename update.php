<?php 

$filename = '*.csv';
$handle = fopen($filename, 'r');

$data = [];

while(!feof($handle)) {
	
	$string  = fgets($handle);
	$string  = str_replace(';0;',';0.00;',$string);
	$string  = str_replace(' ','',$string);
	$string  = str_replace('','',$string);
	$string  = str_replace(',','.',$string);
	
	$array_string = explode(";", $string); 
		
	array_push(
		$data, 
		[
			'code'     => $array_string[1],
			'price'    => $array_string[3],
			'special'  => $array_string[4],
			'quantity' => $array_string[5]
		]
	);

}

fclose($handle);

$connect = mysqli_connect("*", "*", "*", "*");
$connect->set_charset('utf8mb4');

$query = mysqli_query($connect, "
	SELECT c.product_id, c.mpn, c.model, o.name, c.quantity, c.price, c.status, s.price AS special_old FROM oc_product c 
	LEFT JOIN oc_product_description o ON c.product_id = o.product_id 
	LEFT JOIN oc_product_special s ON c.product_id = s.product_id AND customer_group_id = '1'
	ORDER BY c.product_id;
	");
	
$array_bd = [];
$i = 0;

while($row = mysqli_fetch_assoc($query)) { 
	
	$array_bd[$i] = $row;
	
	foreach ($data as $value) {
		if ($value['code'] == $row['mpn']) {
			$array_bd[$i]['compare'] = 1;
			$array_bd[$i]['newPrice'] = $value['price'];
			$array_bd[$i]['special'] = $value['special'];
			$array_bd[$i]['newQuantity'] = $value['quantity'];
			break;
		} else {
			$array_bd[$i]['compare'] = 0;
			$array_bd[$i]['newPrice'] = '';
			$array_bd[$i]['special'] = '';
			$array_bd[$i]['newQuantity'] = '';
		}
	}  	
	$i++;
}

$query = mysqli_query($connect, "UPDATE oc_product SET quantity = 0");
$query = mysqli_query($connect, "UPDATE oc_product SET status = 0");
$query = mysqli_query($connect, "TRUNCATE TABLE oc_product_special");

foreach ($array_bd as $result) {
	
	$product_id = $result['product_id'];
	
	if ($result['newQuantity'] > 0) {		
		$query = "UPDATE oc_product SET quantity = '1000' WHERE product_id = '$product_id'";
		mysqli_query($connect, $query);
	}
	
	if ($result['compare'] == 1) {
		$query = "UPDATE oc_product SET status = '1' WHERE product_id = '$product_id'";
		mysqli_query($connect, $query);
	}
	
	if ($result['special'] > $result['newPrice']) {
		$price = round((int)$result['special'] * 1.2,2,PHP_ROUND_HALF_UP);
	} else {
		$price = $result['newPrice'];
	}

	$query = "UPDATE oc_product SET price = '$price' WHERE product_id = '$product_id'";
	mysqli_query($connect, $query);
	
	if ($result['special'] != '0.00' && !empty($result['special']) && $result['newQuantity'] != '0') {
		
		if (strtotime($result['date_end']) <= strtotime(date("Y-m-d H:i:s"))) {
			$date_sale = date('Y-m-d', strtotime("+1 days"));
		} else {
			$date_sale = $result['date_end'];
		}
		
		$price_special = $result['special'];
				
		$query = "INSERT INTO oc_product_special (product_id, customer_group_id, priority, price, date_start, date_end) VALUES ('$product_id', '1', '0', '$price_special', '0000-00-00', '$date_sale')";
		mysqli_query($connect, $query);
		
		$query = "INSERT INTO oc_product_special (product_id, customer_group_id, priority, price, date_start, date_end) VALUES ('$product_id', '2', '1', '$price_special', '0000-00-00', '$date_sale')";
		mysqli_query($connect, $query);
		
		
	}
	
}

echo 'success belmach.by';



