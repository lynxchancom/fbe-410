<?php

# check physical files should be deleted but still here
# it uses mysql temporary table. array diff or filter very slow and memory greedy

require 'config.php';
require KU_ROOTDIR . 'inc/functions.php';

$boards = $tc_db->GetAll("select name from boards order by name;");

function get_shouldbe($db, $board) {
	return $db->GetAll(sprintf("select id,filename,filetype from posts_%s where IS_DELETED < 1 and filename is not null and filename <> '' order by id", $board));
}

function create_table($dbh, $board, $uploads) {
	$dbh->Execute(sprintf('drop table if exists _uploads_%s;', $board));
	$create = sprintf('create TEMPORARY table _uploads_%s (`filename` varchar(50) NOT NULL, key `filename`(`filename`)) engine=memory;', $board);
	$dbh->Execute($create);

	foreach($uploads as $file) {
		if(preg_match("/^\./", $file)) {continue; }
		if(strlen($file) < 5) {continue; }
		if(preg_match('/(.*)\.\w+?$/', $file, $match)) {
			$insert = sprintf("insert into _uploads_%s values ('%s');", mysqli_real_escape_string($dbh->link, $board), mysqli_real_escape_string($dbh->link, $match[1]));
		}
		#echo $insert;
		$dbh->Execute($insert);
	}

	return $dbh->GetAll(sprintf('select filename from _uploads_%s where filename not in (select filename from posts_%s);', $board, $board));
}

function filter_shouldbenot($dbh, $board) {
	$base_src = sprintf('%s/src', $board);
	$base_thumb = sprintf('%s/thumb', $board);

	$uploads	= scandir($base_src);

	$return = array();
#	$thumbs		= scandir($base_thumb);

	echo sprintf("\tchecking directory %s (%d)\n", $base_src, count($uploads));

	$filenames = create_table($dbh, $board, $uploads);

	foreach ($filenames as $file) {
		array_push($return, sprintf('%s/%s*', $base_src, $file['filename']));
		array_push($return, sprintf('%s/%s*', $base_thumb, $file['filename']));
	}
	return $return;
}

function log_undeleted($fh, $undeleted) {
	foreach($undeleted as $file) {
		fwrite($fh, sprintf("%s\n", $file));
	}
}

$fh = fopen("cleanup.files", "w");
foreach($boards as $board) {
	echo $board['name'], "\n";
	$shouldbe = get_shouldbe($tc_db, $board['name']);
	if(isset($shouldbe)) {
#		echo "\tposts in database with files: ", count($shouldbe), "\n";
#		$files = filter_shouldbenot($tc_db, $board['name'], $shouldbe);
		$files = filter_shouldbenot($tc_db, $board['name']);
#		echo "\tfiltered files: ", count($files), "\n";
		if(count($files)) {
#			echo "\t\tsample: ", $files[0], "\n";
			log_undeleted($fh, $files);
		}
	}
	echo "\n";
}

#var_dump($boards);
?>
