<?php

//action.php

include('database_connection.php');

if (isset($_POST["action"])) {
	if ($_POST["action"] == "insert") {
		$query = "
		INSERT INTO vehicledescription (spid,vcategory,vtype,vname,mid) VALUES (NULL, :vcat, :vtype, :vname, :mid)
		";
		$statement = $connect->prepare($query);
		$statement->execute([
			':vcat' => $_POST["vcat"],
			':vtype' => $_POST["vtype"],
			':vname' => $_POST["vname"],
			':mid' => $_POST["mid"]
		]);
		echo '<p>Data Inserted...</p>';
	}
	if ($_POST["action"] == "fetch_single") {
		$query = "
		SELECT * FROM vehicledescription WHERE spid = :id
		";
		$statement = $connect->prepare($query);
		$statement->execute([':id' => $_POST["id"]]);
		$result = $statement->fetchAll();
		foreach ($result as $row) {
			$output['vcat'] = $row['vcategory'];
			$output['vtype'] = $row['vtype'];
			$output['vname'] = $row['vname'];
			$output['mid'] = $row['mid'];
		}
		echo json_encode($output);
	}
	if ($_POST["action"] == "update") {
		$query = "
		UPDATE vehicledescription
		SET vcategory = :vcat,
		vtype = :vtype, vname = :vname, mid = :mid
		WHERE spid = :hidden_id
		";
		$statement = $connect->prepare($query);
		$statement->execute([
			':vcat' => $_POST["vcat"],
			':vtype' => $_POST["vtype"],
			':vname' => $_POST["vname"],
			':mid' => $_POST["mid"],
			':hidden_id' => $_POST["hidden_id"]
		]);
		echo '<p>Data Updated</p>';
	}
	if ($_POST["action"] == "delete") {
		$query = "DELETE FROM vehicledescription WHERE spid = :id";
		$statement = $connect->prepare($query);
		$statement->execute([':id' => $_POST["id"]]);
		echo '<p>Data Deleted</p>';
	}
}

?>