<?php

include("connect.php");
$db=database_connect();

try {
	$gsheet_url = array_slice($argv, 1)[0];
} catch (Exception $e) {
	$caught = true;
    echo "Couldn't read Google Sheet URL argument: ",  $e->getMessage(), "\n";
}
if (!$caught) {
	$gsheet = new GoogleSheet($gsheet_url, $db);
}

class GoogleSheet {

	private $sheet_url;
	private $sheet_id;
	private $index_array;
	
	public function getSheetId() {
		return $this->sheet_id;
	}
	
	public function getTabsList() {
		return $this->index_array;
	}

	public function __construct($sheet_url, $db) {
		$this->sheet_url = $sheet_url;
		$this->setSheetID($sheet_url);
		$this->index_array = $this->setIndexArray();
		foreach($this->index_array as $key => $value) { // populate the tables
			$this->PopulateTableFromTab($db, $key, $value);
		}
	}
	
	public function setSheetID() {
		preg_match("'\/spreadsheets\/d\/([a-zA-Z0-9-_]+)'", $this->sheet_url, $sheet_id); // extract the ID from the URL
		$this->sheet_id = $sheet_id[1];
	}

	public function setIndexArray() { // build 2-dimension array as google sheet index
		$csvfile = fopen('https://docs.google.com/spreadsheet/pub?key='.$this->sheet_id.'&output=csv&gid=0', 'r');
		if (!$csvfile) {
			echo ("Error reading Google sheet for index tab");
			exit();
		} else{
			$tabs_table = array();
			while($row = fgetcsv($csvfile)) {
				$tabs_table[$row[0]] = $row[1];
			}
			array_splice($tabs_table,0,3); // enlève les 3 premières lignes du tableau
			return ($tabs_table);
		}
	}

	public function PopulateTableFromTab($db, $key, $gid) {
		$csvfile = fopen('https://docs.google.com/spreadsheet/pub?key='.$this->sheet_id.'&output=csv&gid='.$gid, 'r');
		if (!$csvfile) {
			echo ("Error reading Google sheet for ".$key." tab");
			exit();
		} else {

			# Delete old table
			echo ("######################## \n\n");
			echo ("Deleting table $key...\n\n");
			$db->query('DROP TABLE '.$key);

			# Create table
			$columns_nb = 0;
			# Count the max number of columns for this table
			while($row = fgetcsv($csvfile)) {
				$n = 0;
				while($row[$n] && $row[$n] != "") {
					if ($n > $columns_nb) {
						$columns_nb = $n;
					}
					$n += 1;
				}
			}
			// echo("COLUMNS TO CREATE: ".$columns_nb." \n"); // debug
			if($db->getAttribute(PDO::ATTR_DRIVER_NAME) == "mysql") {
				$partial_query_string = 'id INT(6) UNSIGNED PRIMARY KEY AUTO_INCREMENT';
			}
			else if($db->getAttribute(PDO::ATTR_DRIVER_NAME) == "sqlite") {
				$partial_query_string = 'id INTEGER PRIMARY KEY AUTOINCREMENT';
			}
			for ($n = 0; $n <= $columns_nb; $n++) {
				if($db->getAttribute(PDO::ATTR_DRIVER_NAME) == "mysql") {
					$partial_query_string = $partial_query_string.', col'.$n.' VARCHAR(255)';
				}
				else if($db->getAttribute(PDO::ATTR_DRIVER_NAME) == "sqlite") {
					$partial_query_string = $partial_query_string.', col'.$n.' VARCHAR';
				}
			}
			$query_string = 'CREATE TABLE "'.$key.'" ('.$partial_query_string.')';
			echo ($query_string."\n\n"); // debug
			$db->query($query_string);
		}
		fclose($csvfile);

		# Insert values
		$csvfile = fopen('https://docs.google.com/spreadsheet/pub?key='.$this->sheet_id.'&output=csv&gid='.$gid, 'r');
		if (!$csvfile) {
			echo ("Error reading Google sheet for ".$key." tab!");
		} else {
			while($row = fgetcsv($csvfile)) {
				$partial_query_string_1 = 'col0';
				$partial_query_string_2 = '"'.$row[0].'"';
				$n = 1;
				while($row[$n] && $row[$n] != "") {
					$partial_query_string_1 = $partial_query_string_1.', col'.$n;
					$partial_query_string_2 = $partial_query_string_2.', "'.$row[$n].'"';
					$n += 1;
				}
				$query_string = 'INSERT INTO `'.$key.'` ('.$partial_query_string_1.') VALUES ('.$partial_query_string_2.')';
				echo ($query_string."\n");
				$db->query($query_string);
			}
		}
		fclose($csvfile);
		echo("\n\n");

	}

}

?>