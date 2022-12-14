<?php
/*
 * This file is part of kusaba.
 *
 * kusaba is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * kusaba is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * kusaba; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
/**
 * AJAX thread expansion handler
 *
 * Returns replies of threads which have been requested through AJAX
 *
 * @package kusaba
 */

require 'config.php';
/* No need to waste effort if expansion is disabled */
if (!KU_EXPAND) die();
require KU_ROOTDIR . 'inc/functions.php';
require KU_ROOTDIR . 'inc/classes/board-post.class.php';

$board_name = $tc_db->GetOne("SELECT `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $_GET['board']) . "'");
if ($board_name != '') {
	$board_class = new Board($board_name);
	if ($board_class->board_locale != '') {
		changeLocale($board_class->board_locale);
	}
} else {
	die('<font color="red">Invalid board.</font>');
}

if (isset($_GET['preview'])) {
	require KU_ROOTDIR . 'inc/classes/parse.class.php';
	$parse_class = new Parse();

	if (isset($_GET['board']) && isset($_GET['parentid']) && isset($_GET['message'])) {
		die('<b>' . _gettext('Post preview') . ':</b><br><div style="border: 1px dotted;padding: 8px;background-color: white;">' . $parse_class->ParsePost($_GET['message'], $board_class->board_dir, $board_class->board_type, $_GET['parentid']) . '</div>');
	}

	die('Error');
}

$spy = false;

$query = 'SELECT * FROM `'.KU_DBPREFIX.'posts_'.$board_class->board_dir.'` WHERE `IS_DELETED` = 0 AND `parentid` = '.mysqli_real_escape_string($tc_db->link, $_GET['threadid']);
if (KU_POSTSPY) {
	if (isset($_GET['pastid'])) {
		if ($_GET['pastid'] > 0) {
			$spy = true;
			$query .= ' AND `id` > ' . mysqli_real_escape_string($tc_db->link, $_GET['pastid']);
		}
	}
}
$query .= ' ORDER BY `id` ASC';

$results = $tc_db->GetAll($query);

$output = '';

if ($spy) {
	$page = false;
} else {
	$page = true;
}

foreach($results AS $line_reply) {
	$output .= $board_class->BuildPost($page, $board_class->board_dir, $board_class->board_type, $line_reply);
	$newlastid = $line_reply['id'];
}

if ($spy) {
	if (!isset($newlastid)) {
		die();
	}

	echo $newlastid . '|';
}

echo $output;

?>
