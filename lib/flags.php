<?php

/* add flags record */

function create_flag_record($db, $board, $id, $ip) {
#	$log = fopen("/tmp/410.log", "a");
	$geo_code =  @geoip_country_code_by_name($ip);
	$geo_country = @geoip_country_name_by_name($ip);
	
	if(strlen($geo_code) < 2) { 
		$code = 'a1';
		$country = 'Select country';
	}
	else {
		$code = $geo_code;
		$country = $geo_country;
	}
#	fwrite($log, sprintf("%s, %s\n", $ip, print_r($geo)));

	$sql = sprintf("insert into flags (board, board_id, code, country, ip) values ('%s', %d, '%s', '%s', '%s')", mysqli_real_escape_string($db->link, $board), $id, mysqli_real_escape_string($db->link, strtolower($code)), mysqli_real_escape_string($db->link, $country), $ip);
	$db->Execute($sql);
}

?>
