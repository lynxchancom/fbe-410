<?php
header('Content-type: text/html; charset=utf-8');

require 'config.php';
if (!isset($_GET['b']) || !isset($_GET['t']) || !isset($_GET['p'])) {
	if (!isset($_SERVER['PATH_INFO'])) {
		die();
	}
	
	$pairs = explode('/', $_SERVER['PATH_INFO']);
	if (count($pairs) < 4) {
		die();
	}
	
	$board  = $pairs[1];
	$thread = $pairs[2];
	$posts  = $pairs[3];
} else {
	$board  = $_GET['b'];
	$thread = intval($_GET['t']);
	$posts  = intval($_GET['p']);
}

if ($board == '' || $thread == '' || $posts == '') {
	die();
}

$singlepost = (isset($_GET['single'])) ? true : false;

require KU_ROOTDIR . 'inc/functions.php';
require KU_ROOTDIR . 'inc/classes/board-post.class.php';

$executiontime_start = microtime_float();

$results = $tc_db->GetOne("SELECT COUNT(*) FROM `".KU_DBPREFIX."boards` WHERE `name` = '".mysqli_real_escape_string($tc_db->link, $board)."' LIMIT 1");
if ($results == 0) {
	die('Invalid board.');
}
$board_class = new Board($board);

if ($board_class->board_type == 1) {
	$replies = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts_" . $board_class->board_dir . "` WHERE `parentid` = '" . mysqli_real_escape_string($tc_db->link, $thread) . "'");
} else {
	$replies = false;
}
$postids = getQuoteIds($posts, $replies);
if (count($postids) == 0) {
	die('No valid posts specified.');
}

if ($board_class->board_type == 1) {
	$noboardlist = true;
	$hide_extra = true;
} else {
	$noboardlist = false;
	$hide_extra = false;
	$replies = false;
	
	$postidquery = '';
	foreach ($postids as $postid) {
		if ($postid == $thread) {
			$postidquery .= "(p.parentid = 0 AND ";
		} else {
			$postidquery .= "(p.parentid = '" . mysqli_real_escape_string($tc_db->link, $thread) . "' AND ";
		}
		$postidquery .= "p.id = '" . mysqli_real_escape_string($tc_db->link, $postid) . "') OR ";
	}
	$postidquery = substr($postidquery, 0, -4);
}

$board_class->InitializeSmarty();

$page ='';

if (!$singlepost) {
	$board_class->CachePageHeaderData();
	$page .= $board_class->PageHeader($thread, 0, -1, -1, false, true);
	$page .= threadLinks('return', $thread, $board_class->board_dir, $board_class->board_type, false, false, true, true);
} else {
	$tpl['title'] = '';
	$tpl['head'] = '';
}

if ($board_class->board_type == 1) {
	$page .= '<form id="delform" action="http://cgi.kusaba.org/board.php" method="post">' . "\n";
	
	$relative_id = 0;
	$ids_found = 0;
	
	if ($posts != '0') {
		$relative_to_normal = array();
		$sql = "SELECT * FROM `" . KU_DBPREFIX . "posts_" . $board_class->board_dir . "` WHERE (`parentid` = 0 AND `id` = '" . mysqli_real_escape_string($tc_db->link, $thread) . "') OR (`parentid` = '" . mysqli_real_escape_string($tc_db->link, $thread) . "') ORDER BY `id` ASC LIMIT " . mysqli_real_escape_string($tc_db->link, max($postids));
/*		if(array_key_exists($board_class->board_dir, unserialize(KU_FLAGBOARDS))) {
			die("Error");
			$sql = sprintf("SELECT p.*,f.code,f.country FROM posts_%s  p join flags f on (f.board = '%s' and f.board_id = p.id) WHERE (`parentid` = 0 AND `id` = %d) OR (`parentid` = %d ORDER BY `id` ASC LIMIT %d", mysqli_real_escape_string($tc_db->link, max($postids)));
		} */
		
//		$results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts_" . $board_class->board_dir . "` WHERE (`parentid` = 0 AND `id` = '" . mysqli_real_escape_string($tc_db->link, $thread) . "') OR (`parentid` = '" . mysqli_real_escape_string($tc_db->link, $thread) . "') ORDER BY `id` ASC LIMIT " . mysqli_real_escape_string($tc_db->link, max($postids)));
		$results = $tc_db->GetAll($sql);
		foreach ($results as $line) {
			$relative_id++;
			
			$relative_to_normal = $relative_to_normal + array($relative_id => $line);
		}
		
		foreach ($postids as $postid) {
			if (isset($relative_to_normal[$postid])) {
				$ids_found++;
				$newpost = $relative_to_normal[$postid];
				
				$page .= $board_class->BuildPost(false, $board_class->board_dir, $board_class->board_type, $relative_to_normal[$postid], 0, 0, $postid);
			}
		}
	} else {
		if(array_key_exists($board_class->board_dir, unserialize(KU_FLAGBOARDS))) {
			$sql = sprintf("SELECT p.*, f.code,f.country FROM posts_%s join flags f on (f.board = '%s' and f.board_id = p.id) where WHERE (`parentid` = 0 AND `id` = %d) OR (`parentid` = %d) ORDER BY `id` ASC", $board_class->board_dir, $board_class->board_dir, mysqli_real_escape_string($tc_db->link, $thread),  mysqli_real_escape_string($tc_db->link, $thread));
		}
		else {
//			$sql = "SELECT * FROM `" . KU_DBPREFIX . "posts_" . $board_class->board_dir . "` WHERE (`parentid` = 0 AND `id` = '" . mysqli_real_escape_string($tc_db->link, $thread) . "') OR (`parentid` = '" . mysqli_real_escape_string($tc_db->link, $thread) . "') ORDER BY `id` ASC";
			$sql = sprintf("SELECT * FROM posts_%s WHERE (`parentid` = 0 AND `id` = %d) OR (`parentid` = %d) ORDER BY `id` ASC", $board_class->board_dir, mysqli_real_escape_string($tc_db->link, $thread),  mysqli_real_escape_string($tc_db->link, $thread));
		}
		$results = $tc_db->GetAll($sql);
		foreach ($results as $line) {
			$relative_id++;
			$ids_found++;
			
			$page .= $board_class->BuildPost(false, $board_class->board_dir, $board_class->board_type, $line, 0, 0, $relative_id);
		}
	}
	
	if ($ids_found == 0) {
		$page .= _gettext('Unable to find records of any posts matching that quote syntax.');
	}
	
	$page .= '</form>';
} else {
	if (!$singlepost) {
		$page .= '<br>' . "\n";
	}
//	$sql = "SELECT * FROM `" . KU_DBPREFIX . "posts_" . $board_class->board_dir . "` WHERE (" . $postidquery . ") AND `IS_DELETED` = 0";
	if(array_key_exists($board_class->board_dir, unserialize(KU_FLAGBOARDS))) {
		$sql = sprintf("SELECT p.*,f.code,f.country FROM posts_%s p join flags f on (f.board = '%s' and f.board_id = p.id) WHERE (%s) AND `IS_DELETED` = 0", $board_class->board_dir, $board_class->board_dir, $postidquery);
	}
	else {
		$sql = sprintf("SELECT p.* FROM posts_%s p WHERE (%s) AND `IS_DELETED` = 0", $board_class->board_dir, $postidquery);
	}
	$results = $tc_db->GetAll($sql);
	foreach ($results as $line) {
		$page .= $board_class->BuildPost(false, $board_class->board_dir, $board_class->board_type, $line);
	}
	
	if (!$singlepost) {
		$page .= '<br clear="left">' . "\n";
	}
}

if (!$singlepost) {
	$page .= '<hr>' . "\n" .
	$board_class->Footer($noboardlist, (microtime_float() - $executiontime_start), $hide_extra);
}

if (!$singlepost) {
   	$board_class->PrintPage('', $page, true);
} else {
  	echo $page;
}

?>
