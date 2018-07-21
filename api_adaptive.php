<?php
require("adaptive_config.php");
$adaptive_boards = unserialize(KU_ADAPTIVE_CAPTCHA);

function fail() {
	return 0;
}

function ok() {
	return 1;
}


if(!isset($_GET['board'])) {
	echo fail(); echo fail();
	exit;
}

$board = strval($_GET['board']);
if(array_key_exists($board, $adaptive_boards) && $adaptive_boards[$board] > 0)
{
	$adaptive_string = sprintf("%s:%s:%s", KU_SECRET, $board, $_SERVER['REMOTE_ADDR']);
	$adaptive_hash = sha1($adaptive_string);
	if(isset($_COOKIE[$board]) && $_COOKIE[$board] == $adaptive_hash)
	{
		echo ok();
	}
	else 
	{
		echo fail();
	}
	exit;
}
else 
{
	echo fail();
	exit;
}
?>
