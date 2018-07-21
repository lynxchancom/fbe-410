<?php
/*
Serissa 1.2
 */

error_reporting(E_ALL);
class MySQL {	
	// Instance of MySQL Class
    private static $instance;
	// MySQL connection
	public $link;

	public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new MySQL();
        }
        return self::$instance;
    } 
	/* 
	Connect to a MySQL database 
	*/
	public function Connect($host, $username, $password, $database) {
		if(isset($link) && $link) return $link;
		$link = @mysqli_connect($host, $username, $password);
		$this->link = $link;
		if(mysqli_connect_error()) die("_" . mysqli_connect_error());
		
		if(!mysqli_select_db($link, $database)) die("." . mysqli_error($link));
		return true;
	}
	/* 
	Persistent connection to a MySQL database 
	*/
	public function PConnect($host, $username, $password, $database) {
		return $this->Connect($host, $username, $password, $database);
		if($link) return $link;
		$link = @mysql_pconnect($host, $username, $password);
		$this->link = $link;
		
		if(!$link) die(mysql_error());	
		
		if(!mysql_select_db($database, $link)) die(mysql_error());
		return true;
	}
	/* 
	Run a SQL query 
	*/
	public function Execute($sql) {
		// echo "<pre>"; debug_print_backtrace(); echo "</pre><br>";
		$this->query_id = mysqli_query($this->link, $sql);
		if(!$this->query_id) {
			echo "<pre>"; debug_print_backtrace(); echo "</pre>";
			die(mysqli_error($this->link));
		}
		$this->affected_rows = mysqli_affected_rows($this->link);
		return $this->query_id;
	}
	/* 
	Return an associative array 
	*/
	public function GetArray($query_id=null) {
//		var_dump($query_id);
		if ($query_id != null) {
			$this->query_id = $query_id;
		}
		if (isset($this->query_id)) {
			$this->record = mysqli_fetch_array($this->query_id);
		}
		else {
			die('Invalid query [GetAll] ' . mysqli_error($this->link));
		}
		return $this->record;
	}
	/* 
	Return all results in an array 
	*/
	public function GetAll($sql) {
		$query_id = $this->Execute($sql);
		$out = array();
		while ($row = $this->GetArray($query_id, $sql)){
			$out[] = $row;
		}
		return $out;
	}
	/* 
	Return one result 
	*/
	public function GetOne($sql) {
		$one = mysqli_query($this->link, $sql);
		if (!$one) {
			die('Invalid query [GetOne] ' . mysqli_error($this->link));
		}
		$result = mysqli_fetch_row($one);
		return $result[0];
    }
	/* Insert ID */
	public function Insert_ID() {
		return mysqli_insert_id($this->link);
	}	
	/* Return affected rows */
	public function Affected_Rows() {
		return mysqli_affected_rows($this->link);
	} 
}  
?>
