<?php

function database_connect(){
	try {
		$db = new PDO('sqlite:test.sqlite3');
		$db->exec("SET CHARACTER SET utf8");
	}
	catch(Exception $e) {
		die('Error: '.$e->getMessage());
	}
	return $db;
}
?>
