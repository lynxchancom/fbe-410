<?php

require_once 'config.php';

/* search module */

function search_db_connect($link=null) {
	/*
	if(isset($link) && mysqli_ping($link)) {
		mysqli_select_db($link, KU_DBSEARCHDATABASE);
	}
	 */
	$ilink = mysqli_connect(KU_DBSEARCHHOST, KU_DBSEARCHUSERNAME, KU_DBSEARCHPASSWORD,
		KU_DBSEARCHDATABASE);
	if(mysqli_connect_error()) die(mysqli_connect_error());
	mysqli_set_charset($ilink, "UTF8");
    return $ilink;
}

function db_cleanup_link($link)
{
        /*
         * Заметка: если использовать mysqli_use_result вместо store, то
         * не будет выведена ошибка, если таковая произошла в следующем запросе
         * в mysqli_multi_query.
         */
        do
        {
                if(($result = mysqli_store_result($link)) != false)
                        mysqli_free_result($result);
        }
        while(mysqli_next_result($link));
/*        if(mysqli_errno($link))
	throw new CommonException(mysqli_error($link));
*/
}

function db_datetime($time) {
	$tm = localtime($time);
	return sprintf("%04d-%02d-%02d %02d:%02d:%02d", $tm[5] + 1900, $tm[4] + 1, $tm[3], $tm[2], $tm[1], $tm[0]);
}

function create_search_record($post_id, $thread_id = 0, $board, $message, $time) {
	if(array_key_exists($board, unserialize(KU_SEARCHEXCLUDEBOARDS))) {
		return;
	}
	$link = search_db_connect();
	$sql = sprintf("insert into message_cache (board, board_id, board_parent_id, posted_date, message) values ('%s', %d, %d, '%s', '%s')",
		$board, $post_id, $thread_id, db_datetime($time), $message);
	mysqli_query($link, $sql);
	db_cleanup_link($link);
	mysqli_close($link);
}
function delete_search_record($post_id, $board) {
	if(array_key_exists($board, unserialize(KU_SEARCHEXCLUDEBOARDS))) {
		return;
	}
	$link = search_db_connect();
	$sql = sprintf("delete from message_cache where board = '%s' and board_id = %d",
		$board, $post_id);
	mysqli_query($link, $sql);
	db_cleanup_link($link);
	mysqli_close($link);
}
function archive_search_record($link, $post_id, $board) {
//	echo "<pre>"; debug_print_backtrace(); echo "</pre>";
	if(array_key_exists($board, unserialize(KU_SEARCHEXCLUDEBOARDS))) {
		return;
	}
	$link = search_db_connect($link);
	$sql = sprintf("update message_cache set archived = 1 where board = '%s' and board_id = %d",
		$board, $post_id);
	mysqli_query($link, $sql);
	db_cleanup_link($link);
	mysqli_close($link);
}
?>
