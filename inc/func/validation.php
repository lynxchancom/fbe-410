<?php
/**
 * Run a greater than zero check on each ID in the array
 * 
 * @param array $ids Array of thread IDs
 */   
function validateIds($ids) {
	foreach ($ids AS $id) {
		if ($id <= 0) {
			debug_print_backtrace();
			die("error.  parentid <= 0");
		}
	}
}

function isNormalUser($authority) {
	if ($authority == 1 || $authority == 2 || $authority == 4) {
		return false;
	} else {
		return true;
	}
}

?>
