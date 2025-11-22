<?php

//action.php

include('database_connection.php');

if (isset($_POST["action"])) {

	if ($_POST["action"] == "fetch_single") {
		$query = "SELECT * FROM mechanic_reg WHERE mid = :mid";
		$statement = $connect->prepare($query);
		$statement->execute([':mid' => $_POST["mid"]]);
		$result = $statement->fetchAll();
		foreach ($result as $row) {
			$output['mname'] = $row['mname'];
			$output['mphone'] = $row['mphone'];
			$output['shopaddress'] = $row['shopaddress'];
			$output['maadhar'] = $row['maadhar'];
			$output['dlnum'] = $row['drivinglicence'];
		}
		echo json_encode($output);
	}

	if ($_POST["action"] == "delete") {
		$query = "DELETE FROM mechanic_reg WHERE mid = :id";
		$statement = $connect->prepare($query);
		$statement->execute([':id' => $_POST["id"]]);
		echo '<p>Data Deleted</p>';
	}
}

?>