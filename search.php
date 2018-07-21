<?php
error_reporting(E_ALL);

$RESULTS = 10;

/* fuck you php. fuck you. Now I need to have duplicate settings 'cause config.php destroys _GET params*/


if (!headers_sent()) {
	header('Content-Type: text/html; charset=utf-8');
}

require_once 'lib/smarty/Smarty.class.php';
$smarty = new Smarty();

$smarty->template_dir = "smarty/templates";
$smarty->compile_dir = "smarty/templates_c";
$smarty->cache_dir = "smarty/templates_c";
$smarty->config_dir = 'smarty/configs';
$smarty->assign('ku_webpath', "http://410chan.ru");

mb_internal_encoding("UTF-8");
setlocale(LC_ALL, 'en_US.UTF-8');

function search_db_connect() {
	$link = mysqli_connect("localhost", "410chan", "roamerierscaves", "410search");
	if(mysqli_connect_error()) die(mysqli_connect_error());
	$result = mysqli_query($link, "SET NAMES utf8");
	if(!$result)
	{
			die(mysqli_error($link));
	}
	mysqli_set_charset($link, "UTF8");
    return $link;
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

function search_db_getboards($link) {
	$sql = "select board from message_cache group by board order by board";
	$boards = array();
	$result = mysqli_query($link, $sql);
	if(!$result)
	{
			die(mysqli_error($link));
	}
	$row = false;
	if(mysqli_affected_rows($link) > 0)
	{
		while($row = mysqli_fetch_assoc($result)) {
			array_push($boards, $row['board']);
		}
	}
	mysqli_free_result($result);
//	db_cleanup_link($link);
	return $boards;
}
function db_search_results($link, $q, $boards, $page, $show) {
	$sqlboardpart = "";
	if(isset($boards) && is_array($boards)) {
		$sqlboardpart = "and board in (";
		$c = 0;
		foreach(array_keys($boards) as $board) {
			if($c > 0) {
				$sqlboardpart .= ", ";
			}
			$sqlboardpart .= sprintf("'%s'", mysqli_real_escape_string($link, $board));
			$c ++;
		}
		$sqlboardpart .= ")";
	}
	$sqlmatch = sprintf("match(message) against ('%s' in boolean mode)", mysqli_real_escape_string($link, mb_convert_encoding($q, "UTF-8")));
	$sqlfmt = "select board, board_id, board_parent_id, posted_date, message, %s as score,archived from message_cache where %s %s order by score desc, posted_date desc";
	$sql = sprintf($sqlfmt, $sqlmatch, $sqlmatch, $sqlboardpart);
	// TODO
	echo "<!-- ", $sql, " -->\n";
	$search = array();
	$result = mysqli_query($link, $sql);
	// var_dump($result);
	if(mysqli_errno($link))
	{
			die(mysqli_error($link));
	}
	$row = false;
	$rescount = mysqli_affected_rows($link);
	if($rescount > 0)
	{
		$count = 0;
		$showfrom = $page * $show;
		$showto = $page * $show + $show;
		while($row = mysqli_fetch_assoc($result)) {
			$row['__number'] = $count;
			if($count >= $showfrom && $count < $showto) {
				array_push($search, $row);
			}
			$count ++;
		}
	}
	mysqli_free_result($result);
	db_cleanup_link($link);
	return array($search, ceil($rescount / $show), $rescount);
}

function _log($message) {
	return;
	$fp = fopen(".htsearch_log.txt", "a");
	fwrite($fp, sprintf("%s\t%s\t%s\n", date("r"), $_SERVER['REMOTE_ADDR'], $message));
	fclose($fp);
}

exit;

$link = search_db_connect();

$boards = search_db_getboards($link);
$smarty->assign('boards', $boards);


if(isset($_GET['q']) && mb_strlen($_GET['q']) > 0) {
	$q = $_GET['q'];
	if(isset($_GET['p']) && mb_strlen($_GET['p']) > 0) {
		$page = intval($_GET['p']);
	}
	else {
		$page = 0;
	}
	$inboards = null;
	if(isset($_GET['inboards'])) {
		$inboards = array();
		foreach($_GET['inboards'] as $selboard) {
			$inboards[$selboard] = 1;
		}
	}
	$smarty->assign('inboards', $inboards);
	$strippedq = stripslashes($q);
	$smarty->assign('title', "Search: $strippedq");
	_log($q);
	$smarty->assign('query', $strippedq);
	$smarty->display('search.tpl');
	list($r, $pages, $qty) =  db_search_results($link, $q, $inboards, $page, $RESULTS);
	// var_dump($r);
	$smarty->assign('results', $r);
	$smarty->assign('qty', $qty);
	$smarty->assign('div', $qty % 10);
	$smarty->display('search-results.tpl');
	$smarty->assign('page', $page);
	$smarty->assign('pages', $pages);
	$smarty->display('search-footer.tpl');

}
else {
	$smarty->assign('title', "Search");
	$smarty->display('search.tpl');
	$smarty->display('search-footer.tpl');
}
db_cleanup_link($link);
?>
