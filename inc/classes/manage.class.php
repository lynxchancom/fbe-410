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
 * +------------------------------------------------------------------------------+
 * Manage Class
 * +------------------------------------------------------------------------------+
 * Manage functions, along with the pages available
 * +------------------------------------------------------------------------------+
 */
class Manage {

	/* Show the header of the manage page */
	function Header() {
		global $tc_db, $smarty, $tpl_page;
		
		if (is_file(KU_ROOTDIR . 'inc/pages/modheader.html')) {
			$tpl_includeheader = file_get_contents(KU_ROOTDIR . 'inc/pages/modheader.html');
		} else {
			$tpl_includeheader = '';
		}
		
		$smarty->assign('includeheader', $tpl_includeheader);
	}
	
	/* Show the footer of the manage page */
	function Footer() {
		global $tc_db, $smarty, $tpl_page;
		
		$smarty->assign('page', $tpl_page);
		
		$board_class = new Board('');
		$smarty->assign('footer', $board_class->Footer(true));
		
		$smarty->display('manage.tpl');
	}
	
	/* Validate the current session */
	function ValidateSession($is_menu = false) {
		global $tc_db, $smarty, $tpl_page;
	
		if (isset($_SESSION['manageusername']) && isset($_SESSION['managepassword'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `username` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . mysqli_real_escape_string($tc_db->link, $_SESSION['manageusername']) . "' AND `password` = '" . mysqli_real_escape_string($tc_db->link, $_SESSION['managepassword']) . "' LIMIT 1");
			if (count($results) == 0) {
				session_destroy();
				exitWithErrorPage(_gettext('Invalid session.'), '<a href="manage_page.php">' . _gettext('Log in again.') . '</a>');
			}
			
			$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `lastactive` = " . time() . " WHERE `username` = '" . mysqli_real_escape_string($tc_db->link, $_SESSION['manageusername']) . "' LIMIT 1");		
			
			return true;
		} else {
			if (!$is_menu) {
				$this->LoginForm();
				die($tpl_page);
			} else {
				return false;
			}
		}
	}
	
	/* Show the login form and halt execution */
	function LoginForm() {
		global $tc_db, $smarty, $tpl_page;
		
		if (file_exists(KU_ROOTDIR . 'inc/pages/manage_login.html')) {
			$tpl_page .= file_get_contents(KU_ROOTDIR . 'inc/pages/manage_login.html');
		}
	}
	
	/* Check login names and create session if user/pass is correct */
	function CheckLogin() {
		global $tc_db, $smarty, $tpl_page, $action;
		
		$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "loginattempts` WHERE `timestamp` < '" . (time() - 1200) . "'");
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `ip` FROM `" . KU_DBPREFIX . "loginattempts` WHERE `ip` = '" . $_SERVER['REMOTE_ADDR'] . "' LIMIT 6");
		if (count($results) > 5) {
			exitWithErrorPage(_gettext('System lockout'), _gettext('Sorry, because of your numerous failed logins, you have been locked out from logging in for 20 minutes.  Please wait and then try again.'));
		} else {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `username` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . mysqli_real_escape_string($tc_db->link, $_POST['username']) . "' AND `password` = '" . md5($_POST['password']) . "' AND `type` != 3 AND `suspended` != 1 LIMIT 1");
			if (count($results) > 0) {
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "loginattempts` WHERE `ip` < '" . $_SERVER['REMOTE_ADDR'] . "'");
				$_SESSION['manageusername'] = $_POST['username'];
				$_SESSION['managepassword'] = md5($_POST['password']);
				$this->SetModerationCookies();
				$action = 'posting_rates';
				management_addlogentry(_gettext('Logged in (') . $_SERVER['REMOTE_ADDR'] . ')', 1);
				die('<script>top.location.href = \'' . KU_CGIPATH . '/manage.php\';</script>');
			} else {
				$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "loginattempts` ( `username` , `ip` , `timestamp` ) VALUES ( '" . mysqli_real_escape_string($tc_db->link, $_POST['username']) . "' , '" . $_SERVER['REMOTE_ADDR'] . "' , '" . time() . "' )");
				$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "modlog` ( `entry` , `user` , `category` , `timestamp` ) VALUES ('Failed login attempt (" . mysqli_real_escape_string($tc_db->link, $_POST['username']) . ")' , '" . $_SERVER['REMOTE_ADDR'] . "' , '0' , '". time() ."')");
				exitWithErrorPage(_gettext('Incorrect username/password.'));
			}
		}
	}
	
	/* Set mod cookies for boards */
	function SetModerationCookies() {
		global $tc_db, $smarty, $tpl_page;
		
		if (isset($_SESSION['manageusername'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `boards` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . mysqli_real_escape_string($tc_db->link, $_SESSION['manageusername']) . "' LIMIT 1");
			if ($this->CurrentUserIsAdministrator() || $results[0][0] == 'allboards') {
				$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards`");
				foreach ($resultsboard as $lineboard) {					
					setcookie("kumod", "yes", time() + 3600, KU_BOARDSFOLDER . $lineboard['name'] . "/", KU_DOMAIN);
					$_SESSION['modonly'] = 1;
				}
			} else {
				if ($results[0][0] != '') {
					foreach ($results as $line) {
						$array_boards = explode('|', $line['boards']);
					}
					foreach ($array_boards as $this_board_name) {								
						setcookie("kumod", "yes", time() + 3600, KU_BOARDSFOLDER . $this_board_name . "/", KU_DOMAIN);
						$_SESSION['modonly'] = 1;
					}
				}
			}
		}
	}
	/* Log current user out */
	function Logout() {
		global $tc_db, $smarty, $tpl_page;
		
		$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards`");
		foreach ($resultsboard as $lineboard) {
			setcookie('kumod', '', 0, KU_BOARDSFOLDER . $lineboard['name'] . '/', KU_DOMAIN);
		}
		
		session_destroy();
		unset($_SESSION['manageusername']);
		unset($_SESSION['managepassword']);
		die('<script>top.location.href = \'' . KU_CGIPATH . '/manage.php\';</script>');
	}
	
	/*
	 * +------------------------------------------------------------------------------+
	 * Manage pages
	 * +------------------------------------------------------------------------------+
	 */          

	/* Add, view, and delete sections */
	function editsections() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Edit sections')) . '</h2><br>';
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'addsection') {
				if (isset($_POST['name'])) {
					if ($_POST['name'] != '' && $_POST['abbreviation'] != '') {
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "sections` ( `name` , `abbreviation` , `order` , `hidden` ) VALUES ( '" . mysqli_real_escape_string($tc_db->link, $_POST['name']) . "' , '" . mysqli_real_escape_string($tc_db->link, $_POST['abbreviation']) . "' , '" . mysqli_real_escape_string($tc_db->link, $_POST['order']) . "' , '" . (isset($_POST['hidden']) ? '1' : '0') . "' )");
						require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
						$menu_class = new Menu();
						$menu_class->Generate();
						$tpl_page .= _gettext('Section added.');
					}
				} else {
					$tpl_page .= '<form action="?action=editsections&do=addsection" method="post">
					<label for="name">Name:</label><input type="text" name="name"><div class="desc">The name of the section</div><br>
					<label for="abbreviation">Abbreviation:</label><input type="text" name="abbreviation"><div class="desc">Abbreviation (less then 10 characters)</div><br>
					<label for="order">Order:</label><input type="text" name="order"><div class="desc">Order to show this section with others, in ascending order</div><br>
					<label for="hidden">Hidden:</label><input type="checkbox" name="hidden" ><div class="desc">If checked, this section will be collapsed by default when a user visits the site.</div><br>
					<input type="submit" value="Add">
					</form>';
				}
				$tpl_page .= '<br><hr>';
			}
			if ($_GET['do'] == 'editsection' && $_GET['sectionid'] > 0) {
				if (isset($_POST['name'])) {
					if ($_POST['name'] != '' && $_POST['abbreviation'] != '') {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "sections` SET `name` = '" . mysqli_real_escape_string($tc_db->link, $_POST['name']) . "' , `abbreviation` = '" . mysqli_real_escape_string($tc_db->link, $_POST['abbreviation']) . "' , `order` = '" . mysqli_real_escape_string($tc_db->link, $_POST['order']) . "' , `hidden` = '" . (isset($_POST['hidden']) ? '1' : '0') . "' WHERE `id` = '" . $_GET['sectionid'] . "'");
						require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
						$menu_class = new Menu();
						$menu_class->Generate();
						$tpl_page .= _gettext('Section updated.');
					}
				} else {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "sections` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['sectionid']) . "'");
					if (count($results) > 0) {
						foreach ($results as $line) {
							$tpl_page .= '<form action="?action=editsections&do=editsection&sectionid=' . $_GET['sectionid'] . '" method="post">
							<input type="hidden" name="id" value="' . $_GET['sectionid'] . '">
							
							<label for="name">Name:</label>
							<input type="text" name="name" value="' . $line['name'] . '">
							<div class="desc">The name of the section</div><br>
							
							<label for="abbreviation">Abbreviation:</label>
							<input type="text" name="abbreviation" value="' . $line['abbreviation'] . '">
							<div class="desc">Abbreviation (less then 10 characters)</div><br>
							
							<label for="order">Order:</label>
							<input type="text" name="order" value="' . $line['order'] . '">
							<div class="desc">Order to show this section with others, in ascending order</div><br>
							
							<label for="hidden">Hidden:</label>
							<input type="checkbox" name="hidden" ' . ($line['hidden'] == 0 ? '' : 'checked') . '>
							<div class="desc">If checked, this section will be collapsed by default when a user visits the site.</div><br>
							
							<input type="submit" value="Edit">
							
							</form>';
						}
					} else {
						$tpl_page .= _gettext('Unable to locate a section with that ID.');
					}
				}
				$tpl_page .= '<br><hr>';
			}
			if ($_GET['do'] == 'deletesection' && isset($_GET['sectionid'])) {
				if ($_GET['sectionid'] > 0) {
					$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "sections` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['sectionid']) . "'");
					require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
					$menu_class = new Menu();
					$menu_class->Generate();
					$tpl_page .= _gettext('Section deleted.') . '<br><hr>';
				}
			}
		}
		$tpl_page .= '<a href="?action=editsections&do=addsection">Add section</a><br><br>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "sections` ORDER BY `order` ASC");
		if (count($results) > 0) {
			$tpl_page .= '<table border="1" width="100%"><tr><th>'.('ID').'</th><th>'.('Order').'</th><th>Abbreviation</th><th>Name</th><th>Edit/Delete</th></tr>';
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['id'] . '</td><td>' . $line['order'] . '</td><td>' . $line['abbreviation'] . '</td><td>' . $line['name'] . '</td><td><a href="?action=editsections&do=editsection&sectionid=' . $line['id'] . '">Edit</a> <a href="?action=editsections&do=deletesection&sectionid=' . $line['id'] . '">Delete</a></td></tr>';
			}
			$tpl_page .= '</table>';
		} else {
			$tpl_page .= _gettext('There are currently no sections.');
		}
	}
	
	/* Add, view, and delete filetypes */
	function editfiletypes() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Edit filetypes')) . '</h2><br>';
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'addfiletype') {
				if (isset($_POST['filetype'])) {
					$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "filetypes` ( `filetype` , `mime` , `image` , `image_w` , `image_h` , `mediatype` , `force_thumb` ) 
						VALUES ( '" . mysqli_real_escape_string($tc_db->link, $_POST['filetype']) . "' , '" . mysqli_real_escape_string($tc_db->link, $_POST['mime']) . "' , '" . mysqli_real_escape_string($tc_db->link, $_POST['image']) . "' , '" . intval($_POST['image_w']) . "' , '" . intval($_POST['image_h']) . "' , '" . mysqli_real_escape_string($tc_db->link, $_POST['mediatype']) . "' , '" . (1-intval(in_array($_POST['mediatype'], ['video', 'image']))) . "' )");
					$tpl_page .= _gettext('Filetype added.');
				} else {
					$tpl_page .= '<form action="?action=editfiletypes&do=addfiletype" method="post">
					<label for="filetype">Filetype:</label>
					<input type="text" name="filetype">
					<div class="desc">The extension this will be applied to.  <b>Must be lowercase</b></div><br>
					
					<label for="mime">MIME type:</label>
					<input type="text" name="mime">
					<div class="desc">The MIME type which must be present with an image uploaded in this type.  Leave blank to disable.</div><br>
					
					<label for="image">Image:</label>
					<input type="text" name="image" value="generic.png">
					<div class="desc">The image which will be used, found in inc/filetypes.</div><br>
					
					<label for="image_w">Image width:</label>
					<input type="text" name="image_w" value="48">
					<div class="desc">The height of the image.  Needs to be set to prevent the page from jumping around while images load.</div><br>
					
					<label for="image_h">Image height:</label>
					<input type="text" name="image_h" value="48">
					<div class="desc">See above.</div><br>
 
 					<label for="mediatype">Mediatype:</label>
 					<select name="mediatype">
 						<option value="image" selected>Image</option>
 						<option value="video">Video</option>
 						<option value="misc">Other</option>
 					</select>
 					<div class="desc">See above.</div><br>
					
					<input type="submit" value="Add">
					
					</form>';
				}
				$tpl_page .= '<br><hr>';
			}
			if ($_GET['do'] == 'editfiletype' && $_GET['filetypeid'] > 0) {
				if (isset($_POST['filetype'])) {
					if ($_POST['filetype'] != '' && $_POST['image'] != '') {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "filetypes` 
						    SET `filetype` = '" . mysqli_real_escape_string($tc_db->link, $_POST['filetype']) . "' , 
                                `mime` = '" . mysqli_real_escape_string($tc_db->link, $_POST['mime']) . "' , 
                                `image` = '" . mysqli_real_escape_string($tc_db->link, $_POST['image']) . "' , 
                                `image_w` = '" . mysqli_real_escape_string($tc_db->link, $_POST['image_w']) . "' ,
                                `image_h` = '" . mysqli_real_escape_string($tc_db->link, $_POST['image_h']) . "' , 
                                mediatype = '" . mysqli_real_escape_string($tc_db->link, $_POST['mediatype']) ."' 
						    WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['filetypeid']) . "'");
						if (KU_APC) {
							apc_delete('filetype|' . $_POST['filetype']);
						}
						$tpl_page .= _gettext('Filetype updated.');
					}
				} else {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "filetypes` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['filetypeid']) . "'");
					if (count($results) > 0) {
						foreach ($results as $line) {
							$tpl_page .= '<form action="?action=editfiletypes&do=editfiletype&filetypeid=' . $_GET['filetypeid'] . '" method="post">
							
							<label for="filetype">Filetype:</label>
							<input type="text" name="filetype" value="' . $line['filetype'] . '">
							<div class="desc">The extension this will be applied to.  <b>Must be lowercase</b></div><br>
							
							<label for="mime">MIME type:</label>
							<input type="text" name="mime" value="' . $line['mime'] . '">
							<div class="desc">The MIME type which must be present with an image uploaded in this type.  Leave blank to disable.</div><br>
							
							<label for="image">Image:</label>
							<input type="text" name="image" value="' . $line['image'] . '">
							<div class="desc">The image which will be used, found in inc/filetypes.</div><br>
							
							<label for="image_w">Image width:</label>
							<input type="text" name="image_w" value="' . $line['image_w'] . '">
							<div class="desc">The height of the image.  Needs to be set to prevent the page from jumping around while images load.</div><br>
							
							<label for="image_h">Image height:</label>
							<input type="text" name="image_h" value="' . $line['image_h'] . '">
							<div class="desc">See above.</div><br>

 							<label for="mediatype">Mediatype:</label>
 							<select name="mediatype">
 								<option value="image"';
 							if ($line['mediatype'] == 'image'){
 								$tpl_page .= ' selected';
 							}
 							$tpl_page.='>Image</option>
 								<option value="video"';
 							if ($line['mediatype'] == 'video'){
 								$tpl_page .= ' selected';
 							}
 							$tpl_page.='>Video</option>
 								<option value="misc"';
 							if ($line['mediatype'] == 'misc'){
 								$tpl_page .= ' selected';
 							}
 							$tpl_page.='>Other</option>
 							</select>
 							<div class="desc">See above.</div><br>
							
							<input type="submit" value="Edit">
							
							</form>';
						}
					} else {
						$tpl_page .= _gettext('Unable to locate a filetype with that ID.');
					}
				}
				$tpl_page .= '<br><hr>';
			}
			if ($_GET['do'] == 'deletefiletype' && $_GET['filetypeid'] > 0) {
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "filetypes` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['filetypeid']) . "'");
				$tpl_page .= _gettext('Filetype deleted.');
				$tpl_page .= '<br><hr>';
			}
		}
		$tpl_page .= '<a href="?action=editfiletypes&do=addfiletype">Add filetype</a><br><br>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "filetypes` ORDER BY `filetype` ASC");
		if (count($results) > 0) {
			$tpl_page .= '<table border="1" width="100%"><tr><th>ID</th><th>Filetype</th><th>Image</th><th>Edit/Delete</th></tr>';
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['id'] . '</td><td>' . $line['filetype'] . '</td><td>' . $line['image'] . '</td><td><a href="?action=editfiletypes&do=editfiletype&filetypeid=' . $line['id'] . '">Edit</a> <a href="?action=editfiletypes&do=deletefiletype&filetypeid=' . $line['id'] . '">Delete</a></td></tr>';
			}
			$tpl_page .= '</table>';
		} else {
			$tpl_page .= _gettext('There are currently no filetypes.');
		}
	}
	
	/* Rebuild all boards */
	function rebuildhtml() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		$this->AdministratorsOnly();
		$tpl_page .= '<h2>' . ucwords(_gettext('Rebuild HTML files')) . '</h2><br>';
		if (isset($_POST['rebuild'])) {
			$time_start = time();
				$deletion_boards = array();
				$deletion_new_boards = array();
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
				if (isset($_POST['rebuildfromall'])) {
					$this->ModeratorsOnly();
					foreach ($results as $line) {
						$deletion_new_boards[] = $line['name'];
					}
				} else {
					foreach ($results as $line) {
						$deletion_boards[] = $line['name'];
					}
					$deletion_changed_boards = array();
					$deletion_new_boards = array();
					reset($_POST);
					//while (list($postkey, $postvalue) = each($_POST)) {
					foreach($_POST as $postkey => $postvalue) {
						if (substr($postkey, 0, 11) == 'rebuildfrom') {
							$deletion_changed_boards[] = substr($postkey, 11);
						}
					}
					reset($deletion_boards);
					//while (list(, $deletion_thisboard_name) = each($deletion_boards)) {
					foreach($deletion_boards as $delboardkey => $deletion_thisboard_name) {
						if (in_array($deletion_thisboard_name, $deletion_changed_boards)) {
							$deletion_new_boards[] = $deletion_thisboard_name;
						}
					}
					if ($deletion_new_boards == array()) {
						exitWithErrorPage(_gettext('Please select a board.'));
					}
				}
				$delete_boards = implode('|', $deletion_new_boards);
				foreach ($deletion_new_boards as $board) {
					if (!$this->CurrentUserIsModeratorOfBoard($board, $_SESSION['manageusername'])) {
						exitWithErrorPage('/' . $board . '/: ' . _gettext('You can only rebuild HTML from boards you moderate.'));
					}
				}
				$i = 0;
				foreach ($deletion_new_boards as $deletion_board) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $deletion_board) . "'");
					foreach ($results as $line) {
						$board_name = $line['name'];
					}
					$board_class = new Board($board_name);
					$board_class->RegenerateAll();
					$tpl_page .= sprintf(_gettext('Regenerated %s'), '/' . $board_name . '/') . '<br>';
					unset($board_class);
					flush();	
				}
			require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
			$menu_class = new Menu();
			$menu_class->Generate();
			$tpl_page .= 'Regenerated menu pages<br>';
			$tpl_page .= sprintf(_gettext('Rebuild complete.  Took <b>%d</b> seconds.'), time() - $time_start);
			management_addlogentry(_gettext('Rebuilt HTML'), 2);
			unset($board_class);
			}
		else {
			$tpl_page .= '<form action="?action=rebuildhtml" method="post">';
			$tpl_page .= ''._gettext('Select HTML to rebuild').':<br/><br/>
			<label for="rebuildfromall"><b>'._gettext('All boards').'</b></label>
			<input type="checkbox" name="rebuildfromall"><br>OR<br>' .
			$this->MakeBoardListCheckboxes('rebuildfrom', $this->BoardList($_SESSION['manageusername'])) .
			'<br>
			<input type="submit" name="rebuild" value="'._gettext('Rebuild boards').'">		
			</form><br/>';
		}
	}
	
	/* Show APC info */
	function apc() {
		global $tpl_page;
	
		if (KU_APC) {
			$apc_info_system = apc_cache_info();
			$apc_info_user = apc_cache_info('user');
			$tpl_page .= '<h2>APC</h2><h3>System (File cache)</h3><ul>';
			$tpl_page .= '<li>Start time: <b>' . date("y/m/d(D)H:i", $apc_info_system['start_time']) . '</b></li>';
			$tpl_page .= '<li>Hits: <b>' . $apc_info_system['num_hits'] . '</b></li>';
			$tpl_page .= '<li>Misses: <b>' . $apc_info_system['num_misses'] . '</b></li>';
			$tpl_page .= '<li>Entries: <b>' . $apc_info_system['num_entries'] . '</b></li>';
			$tpl_page .= '</ul><br><h3>User (kusaba)</h3><ul>';
			$tpl_page .= '<li>Start time: <b>' . date("y/m/d(D)H:i", $apc_info_user['start_time']) . '</b></li>';
			$tpl_page .= '<li>Hits: <b>' . $apc_info_user['num_hits'] . '</b></li>';
			$tpl_page .= '<li>Misses: <b>' . $apc_info_user['num_misses'] . '</b></li>';
			$tpl_page .= '<li>Entries: <b>' . $apc_info_user['num_entries'] . '</b></li>';
			$tpl_page .= '</ul><br><br><a href="?action=clearcache">Clear APC cache</a>';
		} else {
			$tpl_page .= 'APC isn\'t enabled!';
		}
	}
	
	/* Clear the APC cache */
	function clearcache() {
		global $tpl_page;
	
		if (KU_APC) {
			apc_clear_cache();
			apc_clear_cache('user');
			$tpl_page .= 'APC cache cleared.';
			management_addlogentry(_gettext('Cleared APC cache'), 0);
		} else {
			$tpl_page .= 'APC isn\'t enabled!';
		}
	}
	
	/* Display disk space used per board, and finally total in a large table */
	function spaceused() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Disk space used')) . '</h2><br>';
		$spaceused_res = 0;
		$spaceused_src = 0;
		$spaceused_thumb = 0;
		$spaceused_total = 0;
		$files_res = 0;
		$files_src = 0;
		$files_thumb = 0;
		$files_total = 0;
		$tpl_page .= '<table border="1" width="100%"><tr><th>Board</th><th>Area</th><th>Files</th><th>Space Used</th></tr>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		foreach ($results as $line) {
			list($spaceused_board_res, $files_board_res) = recursive_directory_size(KU_BOARDSDIR . $line['name'] . '/res');
			list($spaceused_board_src, $files_board_src) = recursive_directory_size(KU_BOARDSDIR . $line['name'] . '/src');
			list($spaceused_board_thumb, $files_board_thumb) = recursive_directory_size(KU_BOARDSDIR . $line['name'] . '/thumb');
			
			$spaceused_board_total = $spaceused_board_res + $spaceused_board_src + $spaceused_board_thumb;
			$files_board_total = $files_board_res + $files_board_src + $files_board_thumb;
			
			$spaceused_res += $spaceused_board_res;
			$files_res += $files_board_res;
			
			$spaceused_src += $spaceused_board_src;
			$files_src += $files_board_src;
			
			$spaceused_thumb += $spaceused_board_thumb;
			$files_thumb += $files_board_thumb;
			
			$spaceused_total += $spaceused_board_total;
			$files_total += $files_board_total;
			
			$tpl_page .= '<tr><td rowspan="4">/'.$line['name'].'/</td><td>res/</td><td>' . number_format($files_board_res) . '</td><td>' . ConvertBytes($spaceused_board_res) . '</td></tr>';
			$tpl_page .= '<tr><td>src/</td><td>' . number_format($files_board_src) . '</td><td>' . ConvertBytes($spaceused_board_src) . '</td></tr>';
			$tpl_page .= '<tr><td>thumb/</td><td>' . number_format($files_board_thumb) . '</td><td>' . ConvertBytes($spaceused_board_thumb) . '</td></tr>';
			$tpl_page .= '<tr><td><b>Total</b></td><td>' . number_format($files_board_total) . '</td><td>' . ConvertBytes($spaceused_board_total) . '</td></tr>';
		}
		$tpl_page .= '<tr><td rowspan="4"><b>All boards</b></td><td>res/</td><td>' . number_format($files_res) . '</td><td>' . ConvertBytes($spaceused_res) . '</td></tr>';
		$tpl_page .= '<tr><td>src/</td><td>' . number_format($files_src) . '</td><td>' . ConvertBytes($spaceused_src) . '</td></tr>';
		$tpl_page .= '<tr><td>thumb/</td><td>' . number_format($files_thumb) . '</td><td>' . ConvertBytes($spaceused_thumb) . '</td></tr>';
		$tpl_page .= '<tr><td><b>Total</b></td><td>' . number_format($files_total) . '</td><td>' . ConvertBytes($spaceused_total) . '</td></tr>';
		$tpl_page .= '</table>';
		management_addlogentry(_gettext('Viewed disk space used'), 0);
	}
	
	/* Display moderators and administrators actions which were logged */
	function modlog() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();		
		$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "modlog` WHERE `timestamp` < '" . (time() - KU_MODLOGDAYS * 86400) . "'");
		if(isset($_GET['reset']) && $_GET['reset'] == 1) {
			$this->RootOnly();
			$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "modlog` WHERE `entry` != 1");
			management_addlogentry(_gettext('Cleared the modlog'), 0);
		}
		$tpl_page .= "<h2>ModLog</h2><br/>";
		if (isset($_GET['all']) && $_GET['all'] == '1') {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "modlog` ORDER BY `timestamp` DESC");
		} else {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "modlog` ORDER BY `timestamp` DESC LIMIT 50");
		}
		$tpl_page .= '<table cellspacing="2" cellpadding="1" border="1" width="100%"><tr><th>Time</th><th>User</th><th width="100%">Action</th></tr>';
		$count = 0;
		foreach ($results as $line) {
			$count += 1;
			if ($count%2 == false) {
					$linecolour = "#fff";
			} else {
					$linecolour = "#dce3e8";
			}
			$tpl_page .= "<tr bgcolor=\"$linecolour\">";
			//$tpl_page .= "<td>" . date("y/m/d(D)H:i", $line['timestamp']) . "</td><td>" . $line['user'] . "</td><td>" . htmlentities($line['entry']) . "</td></tr>";
			$tpl_page .= "<td>" . date("y/m/d(D)H:i", $line['timestamp']) . "</td><td>" . $line['user'] . "</td><td>" . $line['entry'] . "</td></tr>";
		}
		$tpl_page .= '</table>';
		$tpl_page .= '<p align="right"><a href="?action=modlog&all=1">Modlog Archive</a> | <a href="manage_page.php?action=modlog&reset=1" onclick="return confirm(\'Are you sure you want to clear the modlog?\');">Clear Modlog</a></p>';
	}
	
	/* Allow SQL injection for administrators */
	function sql() {
		global $tc_db, $smarty, $tpl_page;
		$this->RootOnly();
		$tpl_page .= '<h2>' . _gettext('SQL query') . '</h2><br>';
		if (isset($_POST['query'])) {
			$tpl_page .= '<hr>';
			$result = $tc_db->Execute($_POST['query']);
			if ($result) {
				$tpl_page .= _gettext('Query executed successfully');
			} else {
				$tpl_page .= 'Error: ' . $tc_db->ErrorMsg();
			}
			$tpl_page .= '<hr>';
			management_addlogentry(_gettext('Inserted SQL'), 0);
		}
		$tpl_page .= '<form method="post" action="?action=sql">
		
		<textarea name="query" rows="20" cols="60"></textarea><br>
		
		<input type="submit" value="' . _gettext('Inject') . '">
		
		</form><br/>';
	}
	function menuedit() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$tpl_page .= '<h2>' . _gettext('Menu Editor') . '</h2><br>';
		if(isset($_GET['add']) && $_GET['add']) {
			$this->AdministratorsOnly();
			if(isset($_POST['additem'])) {
				if(!is_numeric($_POST['order'])) {
					exitWithErrorPage('Order must be a number');
				}
				if($tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "menu` (`header`,`body`,`order`) VALUES ('" . mysqli_real_escape_string($tc_db->link, $_POST['header']) . "' , '" . mysqli_real_escape_string($tc_db->link, $_POST['body']) ."' , '" . mysqli_real_escape_string($tc_db->link, $_POST['order']). "')")) {
					require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
					$menu_class = new Menu();
					$menu_class->Generate();
					$tpl_page .= 'Menu item added!<br/><br/>';
					management_addlogentry(_gettext('Added a menu item'), 9);
				}
				else {
					$tpl_page .= 'Failed to add menu item: ' . mysqli_error($tc_db->link);
				}
			}
			$this->AdministratorsOnly();
			$tpl_page .= '<form action="?action=menuedit&add=1" method="post">';
			$tpl_page .= '<b>Add menu item</b><br/>
			This menu item will be displayed below the boards. Format your item with HTML code.<br/>
			<br/>
			<label for="header">Header:</label><input type="text" name="header"/><br/><label for="body">Body:</label><textarea name="body" cols="60" rows="5"></textarea><br/><br/><label for="order">Order: <small>(must be a number from ascending order)</small></label><input type="order" name="order" maxlength="3"/><br/><input type="submit" name="additem" value="Add"/></form><br/>';
		}
		if(isset($_GET['delete']) && $_GET['delete']) {
			$this->AdministratorsOnly();
			if($_GET['delete'] != '') {
				if($tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "menu` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['delete']) . "'")) {
					$tpl_page .= 'Menu item deleted<br/><br/>';
					management_addlogentry(_gettext('Deleted a menu item'), 9);
				}
			}
		}
		if(isset($_GET['edit']) && $_GET['edit']) {
			$this->AdministratorsOnly();
			if(isset($_POST['editnews'])) {
				if($tc_db->Execute("UPDATE `" . KU_DBPREFIX . "menu` SET `body` = '". mysqli_real_escape_string($tc_db->link, $_POST['body'])."', `header` = '".mysqli_real_escape_string($tc_db->link, $_POST['header'])."', `order` = '".mysqli_real_escape_string($tc_db->link, $_POST['order'])."' WHERE `id` = '".$_GET['edit']."'")) {
						require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
						$menu_class = new Menu();
						$menu_class->Generate();
						$tpl_page .= 'Menu edited';						
						management_addlogentry(_gettext('Edited a menu item'), 9);
				}
				else {
					$tpl_page .= 'Failed to edit: ' . mysqli_error($tc_db->link);
				}
			}		
			if($_GET['edit'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "menu` WHERE `id` = '".mysqli_real_escape_string($tc_db->link, $_GET['edit'])."'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$tpl_page .= '<form action="?action=menuedit&edit='.$_GET['edit'].'" method="post"><label for="header">Header:</label><input type="text" name="header" value="'.$line['header'].'"/><br/><label for="body">Body:</label><textarea name="body" cols="60" rows="5">'.$line['body'].'</textarea><br/><label for="order">Order:</label><input type="text" name="order" value="'.$line['order'].'"/><br/>
							<input type="submit" name="editnews" value="Edit"/></form>';
					}
				}
				else {
					$tpl_page .= 'Invalid ID';
				}
			}
		}
			
		if(!isset($_GET['add']) && (!isset($_GET['edit']))) {
			$tpl_page .= 'To display a menu item above the boards, set the order to 0. If the menu fails to update, rebuild HTML files (any board) and clear cache.<br/><br/>';
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "menu` ORDER BY `id` DESC");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$tpl_page .= '<div style="border: 1px solid grey"><div style="background: #F0F0F0; border-bottom: 1px solid #BEBEBE; padding: 2px;"><b>'.$line['header'].'</b> ['.$line['order'].'] | <a href="manage_page.php?action=menuedit&edit='.$line['id'].'">Edit</a> | [<a href="manage_page.php?action=menuedit&delete='.$line['id'].'">x</a>]</div><br/>'.$line['body'].'</div><br/>';
				}
			}
			else {
				$tpl_page .= 'No menu items';
			}
			$tpl_page .= '<br/><div align="right">[ <a href="manage_page.php?action=menuedit&add=1">Add menu item</a> ]</div>';
		}
	}
	function announcement() {
		global $tc_db, $smarty, $tpl_page, $action;
		$this->ModeratorsOnly();
		$tpl_page .= '<h2>' . _gettext('Announcements') . '</h2><br>';
		if(isset($_GET['add']) && $_GET['add']) {
			$this->AdministratorsOnly();
			$tpl_page .= '<form action="?action=announcement" method="post">';
			$tpl_page .= '<b>Add item</b><br/>
			This message will be displayed to all staff members. Format your announcement with HTML code.<br/>
			<br/>
			<label for="name">Name:</label><input type="text" name="name" length="20" maxlength="20" value="'. $_SESSION['manageusername'] .'" readonly="readonly"/><br/><label for"displayname" style="font-size: 9px">Display name:</label> <input type="checkbox" name="displayname" checked="checked"/><br/><label for="subject">Subject:</label><input type="text" name="subject" length="50"/><br/><label for="news_item">News:</label><textarea name="news_item" rows="15" cols="50"></textarea><input type="submit" value="Add News"/></form><br/>';
		}
		if(isset($_GET['delete']) && $_GET['delete']) {
			$this->AdministratorsOnly();
			if($_GET['delete'] != '') {
				if($tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "announcements` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['delete']) . "'")) {
					$tpl_page .= 'News post deleted<br/><br/>';
					management_addlogentry(_gettext('Deleted an announcement entry'), 9);
				}
			}
		}
		if(isset($_POST['news_item'])) {
			$this->AdministratorsOnly();
			if($_POST['news_item'] == '' || $_POST['subject'] == '') {
				exitWithErrorPage(_gettext('Please fill in all fields.'));
			}
			if($_POST['name'] != $_SESSION['manageusername']) {
				die('Invalid name');
			}
			if(!isset($_POST['displayname'])) {
				$_POST['name'] = '';
			}
			if($tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "announcements` ( `name`, `subject` , `news` , `postedat` , `postedby`) VALUES ( '" . mysqli_real_escape_string($tc_db->link, $_POST['name']) . "' , '" . mysqli_real_escape_string($tc_db->link, $_POST['subject']) . "' , '" . mysqli_real_escape_string($tc_db->link, $_POST['news_item']) . "' , '" . time() . "' , '" . mysqli_real_escape_string($tc_db->link, $_SESSION['manageusername']) . "')")) {		
				management_addlogentry(_gettext('Added an announcement entry'), 9);
				$tpl_page .= (_gettext('News item successfully added.')) . '<br/><br/>';
			}
			else {
				exitWithErrorPage(_gettext('Item was not added ' . mysqli_error($tc_db->link)));
			}			
		}
		if(isset($_GET['commentdelete']) && $_GET['commentdelete']) {
			$this->AdministratorsOnly();
			$_GET['commentdelete'] = urldecode($_GET['commentdelete']);
			if(!is_numeric($_GET['id'])) {
				exitWithErrorPage(_gettext('Invalid ID'));
			}
			else {
				$results = $tc_db->GetAll ("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "announcements` WHERE `id` = '". mysqli_real_escape_string($tc_db->link, $_GET['id'])."'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$line['comments'] = str_replace($_GET['commentdelete'], "", $line['comments']);
					}
					if($tc_db->Execute("UPDATE `" . KU_DBPREFIX . "announcements` SET `comments` = '".mysqli_real_escape_string($tc_db->link, $line['comments'])."' WHERE `id` = '". mysqli_real_escape_string($tc_db->link, $_GET['id'])."'")) {
						$tpl_page .= 'Comment deleted';
					}
					else {
						$tpl_page .= 'Failed to delete: ' . mysqli_error($tc_db->link);
					}
				}
				else {
					$tpl_page .= 'Invalid ID';
				}
			}
		}
		if(isset($_GET['comment']) && $_GET['comment']) {
			if(isset($_POST['addcomment'])) {
				$_POST['addcomment'] = str_replace("|", "-", $_POST['addcomment']);
				$_POST['addcomment'] = str_replace(":", ";", $_POST['addcomment']);
				if($tc_db->Execute("UPDATE `" . KU_DBPREFIX . "announcements` SET `comments` = CONCAT( `comments`, '". mysqli_real_escape_string($tc_db->link, $_POST['addcomment']) . '|' . $_POST['commentid'] . '|' . $_SESSION['manageusername'] . '|' . time() . ':' . "') WHERE `id` = '". $_POST['commentid']."'")) {
					$tpl_page .= 'Comment added!<br/><br/>';
					management_addlogentry(_gettext('Added an announcement comment'), 9);
				}
				else {
					die(mysqli_error($tc_db->link));
				}
			}
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "announcements` WHERE `id` = '". mysqli_real_escape_string($tc_db->link, $_GET['comment'])."' ORDER BY `id` DESC");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$tpl_page .= '<div style="border: 1px solid #888"><div style="padding: 5px;background:#E0E0E0;"><b>'.$line['subject'].'</b> | '.date('F j, Y, g:i a', $line['postedat']).'';
					if($line['name'] != '') {
						$tpl_page .= ' by '.$line['name'].' [<a href="manage_page.php?action=announcement&delete='.$line['id'].'">x</a>]</div>';
					}
					else {
						$tpl_page .= ' [<a href="manage_page.php?action=announcement&delete='.$line['id'].'">x</a>]</div>';
					}
					$tpl_page .= '<div style="padding: 5px;">'.$line['news'].'</div></div><br/>';
					if($line['comments'] == '') {
						$tpl_page .= 'No comments<br/>';
					}
					else {
						$comment = explode(":", $line['comments']);
						foreach($comment as $comment2) {
							if($comment2 != '') {
								$comment3 = explode("|", $comment2);
								$tpl_page .= '<div style="border: 1px solid #999;"><div style="background-color: #F0F0F0; padding: 4px;"><strong>'.$comment3[2] . '</strong> ('.date('F j, Y, g:i a', $comment3[3]).') [<a href="manage_page.php?action=announcement&commentdelete='.urlencode($comment2).'&id='.$line['id'].'">x</a>]</div><div style="padding: 4px;">' . htmlspecialchars($comment3[0]).'</div></div><br/>';
							}
						}
					}
					$tpl_page .= '<br/><form action="?action=announcement&comment='.$line['id'].'" method="post"><input type="hidden" value="'.$_GET['comment'].'" name="commentid"/><textarea name="addcomment" rows="4" cols="50"></textarea><input type="submit" name="submitcomment" value="Add"/></form><br/>';
				}
			}
			else {
				$tpl_page .= 'Invalid comment ID';
			}
		}
		if(!isset($_GET['add']) && !isset($_GET['comment']) && !isset($_GET['commentdelete'])) {
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "announcements` ORDER BY `id` DESC");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$tpl_page .= '<div style="border: 1px solid #888"><div style="padding: 5px;background:#E0E0E0;"><b>'.$line['subject'].'</b> | '.date('F j, Y, g:i a', $line['postedat']).'';
					if($line['name'] != '') {
						$tpl_page .= ' by '.$line['name'].' [<a href="manage_page.php?action=announcement&delete='.$line['id'].'">x</a>]</div>';
					}
					else {
						$tpl_page .= ' [<a href="manage_page.php?action=announcement&delete='.$line['id'].'">x</a>]</div>';
					}
					$tpl_page .= '<div style="padding: 5px;">'.$line['news'].'</div></div>';
					$comment = explode(":", $line['comments']);
					$i = 0;
					foreach($comment as $comment2) {
						if($comment2 != '') {
							$i++;
						}
					}
					if($i == 0) {
						$tpl_page .=  '<div align="right" style="padding: 3px"><a href="manage_page.php?action?announcement&comment='.$line['id'].'" style="color: grey">No comments</a></div><br/><br/>';
					}
				    else {
						$tpl_page .=  '<div align="right" style="padding: 3px"><a href="manage_page.php?action?announcement&comment='.$line['id'].'">Comments ('.$i.')</a></div><br/><br/>';
					}				
				}
			} else {
				$tpl_page .= 'No news feed.';
			}
			if($this->CurrentUserIsAdministrator()) {
				$tpl_page .= '<p style="text-align: right">[<a href="manage_page.php?action=announcement&add=1">Add item</a>]</p>';
			}
		}
	}
	/* Add, edit, delete, and view news entries */
	function news() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		if (isset($_GET['edit'])) {
		$tpl_page .= '<h2>' . _gettext('Edit news post') . '</h2><br>';
			if (isset($_POST['news'])) {
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "news` SET `subject` = '" . mysqli_real_escape_string($tc_db->link, $_POST['subject']) . "', `message` = '" . mysqli_real_escape_string($tc_db->link, $_POST['news']) . "', `postedemail` = '" . mysqli_real_escape_string($tc_db->link, $_POST['email']) . "' WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['edit']) . "'");
				$tpl_page .= '<span>News post edited</span><br/><br/>';
				management_addlogentry(_gettext('Edited a news entry'), 9);
			}			
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "news` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['edit']) . "'");
			foreach ($results as $line) {
			$tpl_page .= '<form method="post" action="?action=news&edit=' . $_GET['edit'] . '">
			<label for="subject">' . _gettext('Subject') . ':</label>
			<input type="text" name="subject" value="' . $line['subject'] . '">
			<div class="desc">' . _gettext('Can not be left blank.') . '</div><br>
			
			<textarea name="news" rows="25" cols="80">' . $line['message'] . '</textarea><br>
			
			<label for="email">' . _gettext('E-mail') . ':</label>
			<input type="text" name="email" value="' . $line['postedemail'] . '">
			<div class="desc">' . _gettext('Can be left blank.') . '</div><br>
			<input type="submit" value="Edit">
			</form><br/>';
			}
		} elseif (isset($_GET['delete'])) {
			$results = $tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "news` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['delete']) . "'");
			$tpl_page .= '<h2>' . _gettext('Delete news post') . '</h2><br>';
			$tpl_page .= '<span>News post deleted</span>';
			management_addlogentry(_gettext('Deleted a news entry'), 9);
		} else {
			$tpl_page .= _gettext('<h2>Add News Post</h2>This message will be displayed as it is written, so make sure you add the proper HTML.') . '<br><br>';
			if (isset($_POST['news']) && isset($_POST['subject']) && isset($_POST['email'])) {
				if ($_POST['news'] != '') {
					$tpl_page .= '<hr>';
					if ($_POST['subject'] != '') {
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "news` ( `subject` , `message` , `postedat` , `postedby` , `postedemail` ) VALUES ( '" . mysqli_real_escape_string($tc_db->link, $_POST['subject']) . "' , '" . mysqli_real_escape_string($tc_db->link, $_POST['news']) . "' , '" . time() . "' , '" . mysqli_real_escape_string($tc_db->link, $_SESSION['manageusername']) . "' , '" . mysqli_real_escape_string($tc_db->link, $_POST['email']) . "' )");
						$tpl_page .= '<h3>' . _gettext('News entry successfully added.') . '</h3>';
						management_addlogentry(_gettext('Added a news entry'), 9);
					} else {
						$tpl_page .= _gettext('You must enter a subject.');
					}
					$tpl_page .= '<hr>';
				}
			}
			$tpl_page .= '<form method="post" action="?action=news">
			<label for="subject">' . _gettext('Subject') . ':</label>
			<input type="text" name="subject" value="">
			<div class="desc">' . _gettext('Can not be left blank.') . '</div><br>
			
			<textarea name="news" rows="25" cols="80"></textarea><br>
			
			<label for="email">' . _gettext('E-mail') . ':</label>
			<input type="text" name="email" value="">
			<div class="desc">' . _gettext('Can be left blank.') . '</div><br>
			
			<input type="submit" value="' . _gettext('Add') . '">
			</form><br/>';
			
			$tpl_page .= '<br><hr><h1>Edit/Delete News</h1>';
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "news` ORDER BY `id` DESC");
			if (count($results) > 0) {
				$tpl_page .= '<table border="1" width="100%"><tr><th>Date Added</th><th>Subject</th><th>Message</th><th>Edit/Delete</th></tr>';
				foreach ($results as $line) {
					$tpl_page .= '<tr><td>' . date('F j, Y, g:i a', $line['postedat']) . '</td><td>' . $line['subject'] . '</td><td>' . $line['message'] . '</td><td><a href="?action=news&edit=' . $line['id'] . '">Edit</a>/<a href="?action=news&delete=' . $line['id'] . '">Delete</a></td></tr>';
				}
				$tpl_page .= '</table>';
			} else {
				$tpl_page .= 'No news posts yet.';
			}
		}
	}
	
	function mainsubpages() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>' . (isset($_GET['edit']) ? _gettext( 'Edit subpage') : _gettext('Add subpage')). '</h2>
If no subpages are added, default hardcoded subpages are used (FAQ, Rules, English, ??????????, radio.unix).
<br><br>';

		if (isset($_POST['delete'])) {
			$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "main_subpages` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_POST['delete']) . "'");
			$tpl_page .= '<span>Subpage deleted</span><br/><br/>';
			management_addlogentry(_gettext('Deleted main subpage'), 9);
		} elseif (isset($_POST['edit'])) {
			$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "main_subpages`
				SET `name` = '" . mysqli_real_escape_string($tc_db->link, $_POST['name']) . "',
					`file` = '" . mysqli_real_escape_string($tc_db->link, $_POST['file']) . "',
					`hidden` = " . (isset($_POST['hidden']) ? 1 : 0) . "
				WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_POST['edit']) . "'");
			$tpl_page .= '<span>Subpage edited</span><br/><br/>';
			management_addlogentry(_gettext('Edited main subpage'), 9);
		} elseif (isset($_POST['up'])) {
			$index = $tc_db->GetOne("SELECT HIGH_PRIORITY `index` FROM `" . KU_DBPREFIX . "main_subpages` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_POST['up']) . "'");
			if ($index > 1) {
				$tc_db->Execute("START TRANSACTION");
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "main_subpages` SET `index` = `index` + 1 WHERE `index` = " . ($index - 1));
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "main_subpages` SET `index` = `index` - 1 WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_POST['up']) . "'");
				$tc_db->Execute("COMMIT");
				$tpl_page .= '<span>Subpage moved</span><br/><br/>';
				management_addlogentry(_gettext('Moved main subpage'), 9);
			}
		} elseif (isset($_POST['down'])) {
			$index = $tc_db->GetOne("SELECT HIGH_PRIORITY `index` FROM `" . KU_DBPREFIX . "main_subpages` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_POST['down']) . "'");
			$subpagesCount = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM main_subpages");
			if ($index < $subpagesCount) {
				$tc_db->Execute("START TRANSACTION");
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "main_subpages` SET `index` = `index` - 1 WHERE `index` = " . ($index + 1));
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "main_subpages` SET `index` = `index` + 1 WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_POST['down']) . "'");
				$tc_db->Execute("COMMIT");
				$tpl_page .= '<span>Subpage moved</span><br/><br/>';
				management_addlogentry(_gettext('Moved main subpage'), 9);
			}
		} elseif (isset($_POST['name'])) {
			$subpagesCount = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM main_subpages");
			$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "main_subpages`(`name`, `file`, `hidden`, `index`) VALUES ('"
				. mysqli_real_escape_string($tc_db->link, $_POST['name']) . "', '"
				. mysqli_real_escape_string($tc_db->link, $_POST['file']) . "', '"
				. (isset($_POST['hidden']) ? 1 : 0) . "', '"
				. ($subpagesCount + 1) . "')");
			$tpl_page .= '<span>Subpage added</span><br/><br/>';
			management_addlogentry(_gettext('Added main subpage'), 9);
		}

		$name = "";
		$file = "";
		$hidden = false;

		if (isset($_GET['edit'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "main_subpages` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['edit']) . "'");
			if (count($results)) {
				$name = $results[0]['name'];
				$file = $results[0]['file'];
				$hidden = $results[0]['hidden'];
			}
		}

		$tpl_page .= '<form method="post" action="manage_page.php">
			<input type="hidden" name="action" value="mainsubpages">';
		if (isset($_GET['edit'])) {
			$tpl_page .= '<input type="hidden" name="edit" value="'. $_GET['edit'] . '">';
		}
		$tpl_page .= '<label for="name">' . _gettext('Name') . ':</label>
			<input id="name" type="text" required="required" name="name" value="' . $name . '">
			<div class="desc">' . _gettext('Can not be left blank.') . '</div><br>
			
			<label for="file">' . _gettext('File') . ':</label>
			<input id="file" type="text" required="required" name="file" value="' . $file . '">
			<div class="desc">' . _gettext('Can not be left blank.') . '</div><br>
			<label for="hiden">' . _gettext('Hidden') . ':</label>
			<input id="hidden" type="checkbox" name="hidden" ' . ($hidden == '1' ? 'checked' : '') . '>
			<br>
			<input type="submit" value="'. (isset($_GET['edit']) ? 'Edit' : 'Add') . '">
			</form><br/>';

		$tpl_page .= '<br><hr><h1>Edit/Delete Subpages</h1>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "main_subpages` ORDER BY `index`, `id`");

		$tc_db->Execute("START TRANSACTION");
		for ($i=0; $i<count($results); $i++) {		//fix all indexes
			$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "main_subpages` SET `index` = " . ($i+1) . " WHERE `id` = '" . $results[$i]['id'] . "'");
		}
		$tc_db->Execute("COMMIT");

		if (count($results) > 0) {
			$tpl_page .= '<table border="1" width="100%"><tr><th>Name</th><th>File</th><th>Hidden</th><th>Edit/Delete</th><th>Move</th></tr>';
			for ($i=0; $i<count($results); $i++) {
				$line = $results[$i];
				$file_exists = file_exists( KU_BOARDSDIR . 'inc/pages/' . $line['file']);
				$tpl_page .= '<tr>
<td>' . $line['name'] . '</td>
<td>' . $line['file'] . ($file_exists ? '' : ' <span style="color:red">File doesn\'t exist!</span>') . '</td>
<td>' . ($line['hidden'] == '1' ? 'Yes' : 'No') . '</td>
<td>
<form method="get" action="manage_page.php"><input type="hidden" name="action" value="mainsubpages"><input type="hidden" name="edit" value="' . $line['id'] . '"><input value="Edit" type="submit" class="tableaction" ></form>
<form method="post" action="manage_page.php"><input type="hidden" name="action" value="mainsubpages"><input type="hidden" name="delete" value="' . $line['id'] . '"><input value="Delete" type="submit" class="tableaction"></form>
</td>
<td>';
				if ($i>0) {
					$tpl_page .= '<form method="post" action="manage_page.php"><input type="hidden" name="action" value="mainsubpages"><input type="hidden" name="up" value="' . $line['id'] . '"><input value="???" type="submit" class="tableaction"></form>';
				}

				if ($i<count($results)-1) {
					$tpl_page .= '<form method="post" action="manage_page.php"><input type="hidden" name="action" value="mainsubpages"><input type="hidden" name="down" value="' . $line['id'] . '"><input value="???" type="submit" class="tableaction"></form>';
				}

				$tpl_page .= '</td></tr>';
			}
			$tpl_page .= '</table>';
		} else {
			$tpl_page .= 'No subpages yet.';
		}
	}
	
	function blotter() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		if (!KU_BLOTTER) {
			exitWithErrorPage(_gettext('Blotter is disabled.'));
		}
		$tpl_page .= '<h1>' . _gettext('Blotter') . '</h1>';
		
		if (isset($_POST['message'])) {
			$save_important = (isset($_POST['important'])) ? '1' : '0';
			
			if (isset($_POST['edit'])) {
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "blotter` SET `message` = '" . mysqli_real_escape_string($tc_db->link, $_POST['message']) . "', `important` = '" . $save_important . "' WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_POST['edit']) . "'");
				$tpl_page .= '<h3>' . _gettext('Blotter entry updated.') . '</h3>';
			} else {
				$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "blotter` (`at`, `message`, `important`) VALUES ('" . time() . "', '" . mysqli_real_escape_string($tc_db->link, $_POST['message']) . "', '" . $save_important . "')");
				$tpl_page .= '<h3>' . _gettext('Blotter entry added.') . '</h3>';
			}
			clearBlotterCache();
		} elseif (isset($_GET['delete'])) {
			$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "blotter` WHERE `id` =  '" . mysqli_real_escape_string($tc_db->link, $_GET['delete']) . "'");
			clearBlotterCache();
			$tpl_page .= '<h3>' . _gettext('Blotter entry deleted.') . '</h3>';
		}
		
		$edit_id = '';
		$edit_message = '';
		$edit_important = '';
		if (isset($_GET['edit'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "blotter` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['edit']) . "' LIMIT 1");
			foreach ($results as $line) {
				$edit_id = $line['id'];
				$edit_message = $line['message'];
				$edit_important = $line['important'];
			}
		}
		
		$tpl_page .= '<form action="?action=blotter" method="post">';
		if ($edit_id != '') {
			$tpl_page .= '<input type="hidden" name="edit" value="' . $edit_id . '">';
		}
		$tpl_page .= '<label for="message">' . _gettext('Message') . ':</label>
		<input type="text" name="message" value="' . $edit_message . '" size="75"><br>
		
		<label for="important">' . _gettext('Important') . ':</label>
		<input type="checkbox" name="important"';
		if ($edit_important == 1) {
			$tpl_page .= ' checked';
		}
		$tpl_page .= '><br>
		
		<input type="submit" value="';
		if ($edit_id != '') {
			$tpl_page .= _gettext('Edit');
		} else {
			$tpl_page .= _gettext('Add new blotter entry');
		}
		$tpl_page .= '">';
		if ($edit_id != '') {
			$tpl_page .= '&nbsp;&nbsp;<a href="?action=blotter">' . _gettext('Cancel') . '</a>';
		}
		$tpl_page .= '<br>
		
		</form><br><br>';
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "blotter` ORDER BY `id` DESC");
		if (count($results) > 0) {
			$tpl_page .= '<table border="1" width="100%"><tr><th>' . _gettext('At') . '</th><th>' . _gettext('Message') . '</th><th>' . _gettext('Important') . '</th><th>&nbsp;</th></tr>';
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . date('m/d/y', $line['at']) . '</td><td>' . $line['message'] . '</td><td>';
				if ($line['important'] == 1) {
					$tpl_page .= _gettext('Yes');
				} else {
					$tpl_page .= _gettext('No');
				}
				$tpl_page .= '</td><td><a href="?action=blotter&edit=' . $line['id'] . '">Edit</a>/<a href="?action=blotter&delete=' . $line['id'] . '">Delete</a></td></tr>';
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('No blotter entries.') . '</td></tr>';
		}
		$tpl_page .= '</table>';
	}
	
	/* Edit a boards options */
	function boardopts() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Board options')) . '</h2><br>';
		if (isset($_SESSION['updateboard']) && isset($_POST['order']) && isset($_POST['maxpages']) && isset($_POST['maxage']) && isset($_POST['messagelength'])) {
			$_POST['updateboard'] = $_SESSION['updateboard'];
			if (!$this->CurrentUserIsModeratorOfBoard($_POST['updateboard'], $_SESSION['manageusername'])) {
				exitWithErrorPage(_gettext('You are not a moderator of this board.'));
			}
			$boardid = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $_POST['updateboard']) . "' LIMIT 1");
			if ($boardid != '') {
				if ($_POST['order'] >= 0 && $_POST['maxpages'] >= 0 && $_POST['markpage'] >= 0 && $_POST['maxage'] >= 0 && $_POST['messagelength'] >= 0 && ($_POST['defaultstyle'] == '' || in_array($_POST['defaultstyle'], explode(':', KU_STYLES)) || in_array($_POST['defaultstyle'], explode(':', KU_TXTSTYLES)))) {
					$filetypes = array();
					reset($_POST);
					foreach($_POST as $postkey => $postvalue) {
					//while (list($postkey, $postvalue) = each($_POST)) {
						if (substr($postkey, 0, 9) == 'filetype_') {
							$filetypes[] = substr($postkey, 9);
						}
					}
					$updateboard_enablecatalog = isset($_POST['enablecatalog']) ? '1' : '0';
					$updateboard_enablespoiler = isset($_POST['enablespoiler']) ? '1' : '0';
					$updateboard_enablenofile = isset($_POST['enablenofile']) ? '1' : '0';
 					$updateboard_enablesoundinvideo = isset($_POST['enablesoundinvideo']) ? '1' : '0';
					$updateboard_redirecttothread = isset($_POST['redirecttothread']) ? '1' : '0';
					$updateboard_enablereporting = isset($_POST['enablereporting']) ? '1' : '0';
					$updateboard_enablecaptcha = isset($_POST['enablecaptcha']) ? '1' : '0';
					$updateboard_enablefaptcha = isset($_POST['enablefaptcha']) ? '1' : '0';
					$updateboard_enableporn = isset($_POST['enableporn']) ? '1' : '0';
					$updateboard_forcedanon = isset($_POST['forcedanon']) ? '1' : '0';
					$updateboard_trial = isset($_POST['trial']) ? '1' : '0';
					$updateboard_popular = isset($_POST['popular']) ? '1' : '0';
					$updateboard_enablearchiving = isset($_POST['enablearchiving']) ? '1' : '0';
					$updateboard_showid = isset($_POST['showid']) ? '1' : '0';
					$updateboard_compactlist = isset($_POST['compactlist']) ? '1' : '0';
					$updateboard_locked = isset($_POST['locked']) ? '1' : '0';
					if (($_POST['type'] == '0' || $_POST['type'] == '1' || $_POST['type'] == '2' || $_POST['type'] == '3') && ($_POST['uploadtype'] == '0' || $_POST['uploadtype'] == '1' || $_POST['uploadtype'] == '2')) {
						if (!($_POST['uploadtype'] != '0' && $_POST['type'] == '3')) {
							// Why, God, WHY!?
							$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "boards` SET `type` = '" . mysqli_real_escape_string($tc_db->link, $_POST['type']) . "' , `uploadtype` = '" . mysqli_real_escape_string($tc_db->link, $_POST['uploadtype']) . "' , `order` = '" . mysqli_real_escape_string($tc_db->link, $_POST['order']) . "' , `section` = '" . mysqli_real_escape_string($tc_db->link, $_POST['section']) . "' , `desc` = '" . mysqli_real_escape_string($tc_db->link, $_POST['desc']) . "' , `locale` = '" . mysqli_real_escape_string($tc_db->link, $_POST['locale']) . "' , `showid` = '" . $updateboard_showid . "' , `compactlist` = '" . $updateboard_compactlist . "' , `locked` = '" . $updateboard_locked . "' , `maximagesize` = '" . mysqli_real_escape_string($tc_db->link, $_POST['maximagesize']) . "' , `messagelength` = '" . mysqli_real_escape_string($tc_db->link, $_POST['messagelength']) . "' , `maxpages` = '" . mysqli_real_escape_string($tc_db->link, $_POST['maxpages']) . "' , `maxage` = '" . mysqli_real_escape_string($tc_db->link, $_POST['maxage']) . "' , `markpage` = '" . mysqli_real_escape_string($tc_db->link, $_POST['markpage']) . "' , `maxreplies` = '" . mysqli_real_escape_string($tc_db->link, $_POST['maxreplies']) . "' , `image` = '" . mysqli_real_escape_string($tc_db->link, $_POST['image']) . "' , `includeheader` = '" . mysqli_real_escape_string($tc_db->link, $_POST['includeheader']) . "' , `redirecttothread` = '" . $updateboard_redirecttothread . "' , `anonymous` = '" . mysqli_real_escape_string($tc_db->link, $_POST['anonymous']) . "' , `forcedanon` = '" . $updateboard_forcedanon . "' , `trial` = '" . $updateboard_trial . "' , `popular` = '" . $updateboard_popular . "' , `defaultstyle` = '" . mysqli_real_escape_string($tc_db->link, $_POST['defaultstyle']) . "' , `enablereporting` = '" . $updateboard_enablereporting . "' , `enablecaptcha` = '" . $updateboard_enablecaptcha . "' , `enablefaptcha` = '" . $updateboard_enablefaptcha . "' , `enableporn` = '" . $updateboard_enableporn . "' , `enablenofile` = '" . $updateboard_enablenofile . "' , `enablearchiving` = '" . $updateboard_enablearchiving . "', `enablecatalog` = '" . $updateboard_enablecatalog . "' , `enablespoiler` = '" . $updateboard_enablespoiler . "' , `loadbalanceurl` = '" . mysqli_real_escape_string($tc_db->link, $_POST['loadbalanceurl']) . "' , `loadbalancepassword` = '" . mysqli_real_escape_string($tc_db->link, $_POST['loadbalancepassword']) . "', `enablesoundinvideo` = '" . $updateboard_enablesoundinvideo . "' WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $_POST['updateboard']) . "'");
							$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $boardid . "'");
							foreach ($filetypes as $filetype) {
								$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "board_filetypes` ( `boardid`, `typeid` ) VALUES ( '" . $boardid . "', '" . mysqli_real_escape_string($tc_db->link, $filetype) . "' )");
							}
							require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
							if(isset($_POST['run_cleanup'])) {
								if(!$this->delspecorphanreplies(mysqli_real_escape_string($tc_db->link, $_POST['updateboard']))) {
									exitWithErrorPage(_gettext('Could not delete orphan replies'));
								}
								if(!$this->delspecunusedimages(mysqli_real_escape_string($tc_db->link, $_POST['updateboard']))) {
									exitWithErrorPage(_gettext('Could not delete unused images'));
								}
								$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "posts_" . mysqli_real_escape_string($tc_db->link, $_POST['updateboard']) . "` WHERE `IS_DELETED` = 1 AND `deletedat` < " . (time() - 604800) . "");
								management_addlogentry(_gettext('Ran cleanup') . " - /" . $_POST['updateboard'] . "/", 4);
							}							
							$menu_class = new Menu();
							$menu_class->Generate();
							if (isset($_POST['submit_regenerate'])) {
								$board_class = new Board($_POST['updateboard']);
								$board_class->RegenerateAll();
							}
							$tpl_page .= _gettext('Update successful.');
							management_addlogentry(_gettext('Updated board configuration') . " - /" . $_POST['updateboard'] . "/", 4);
							unset($_SESSION['updateboard']);
						} else {
							$tpl_page .= _gettext('Sorry, embed may only be enabled on normal imageboards.');
						}
					} else {
						$tpl_page .= _gettext('Sorry, a generic error has occurred.');
					}
				} else {
					$tpl_page .= _gettext('Integer values must be entered correctly.');
				}
			} else {
				$tpl_page .= _gettext('Unable to locate a board named') . ' <b>' . $_POST['updateboard'] . '</b>.';
			}
		} elseif (isset($_POST['board'])) {
			if (!$this->CurrentUserIsModeratorOfBoard($_POST['board'], $_SESSION['manageusername'])) {
				exitWithErrorPage(_gettext('You are not a moderator of this board.'));
			}
			$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $_POST['board']) . "'");
			if (count($resultsboard) > 0) {
				foreach ($resultsboard as $lineboard) {
					$tpl_page .= '<div class="container">
					<form action="?action=boardopts" method="post">';
					/* Directory */
					$tpl_page .= '<label for="board">'._gettext('Directory').':</label>
					<input type="text" name="board" value="'.$_POST['board'].'" disabled>
					<div class="desc">'._gettext('The directory of the board.').'</div><br>';
				    $_SESSION['updateboard'] = $_POST['board'];
					
					/* Description */
					$tpl_page .= '<label for="desc">'._gettext('Description').':</label>
					<input type="text" name="desc" value="'.$lineboard['desc'].'">
					<div class="desc">'._gettext('The name of the board.').'</div><br>';
					
					/* Locale */
					$tpl_page .= '<label for="locale">Locale:</label>
					<input type="text" name="locale" value="'.$lineboard['locale'].'">
					<div class="desc">Locale to use on this board.  Leave blank to use the locale defined in config.php</div><br>';
					
					/* Board type */
					$tpl_page .= '<label for="type">'._gettext('Board type:').'</label>
					<select name="type">
					<option value="0"';
					if ($lineboard['type'] == '0') { $tpl_page .= ' selected'; }
					$tpl_page .= '>'._gettext('Normal imageboard').'</option>
					<option value="1"';
					if ($lineboard['type'] == '1') { $tpl_page .= ' selected'; }
					$tpl_page .= '>'._gettext('Text board').'</option><option value="2"';
					if ($lineboard['type'] == '2') { $tpl_page .= ' selected'; }
					$tpl_page .= '>'._gettext('Oekaki imageboard').'</option><option value="3"';
					if ($lineboard['type'] == '3') { $tpl_page .= ' selected'; }
					$tpl_page .= '>'._gettext('Upload imageboard').'</option>
					</select>
					<div class="desc">'._gettext('The type of posts which will be accepted on this board.  A normal imageboard will feature image and extended format posts, a text board will have no images, an Oekaki board will allow users to draw images and use them in their posts, and an Upload imageboard will be styled more towards file uploads.').' '._gettext('Default').': <b>Normal Imageboard</b></div><br>';
					
					/* Upload type */
					$tpl_page .= '<label for="uploadtype">'._gettext('Upload type:').'</label>
					<select name="uploadtype">
					<option value="0"';
					if ($lineboard['uploadtype'] == '0') {
						$tpl_page .= ' selected';
					}
					$tpl_page .= '>'._gettext('No embedding').'</option>
					<option value="1"';
					if ($lineboard['uploadtype'] == '1') {
						$tpl_page .= ' selected';
					}
					$tpl_page .= '>'._gettext('Images and embedding').'</option>
					<option value="2"';
					if ($lineboard['uploadtype'] == '2') {
						$tpl_page .= ' selected';
					}
					$tpl_page .= '>'._gettext('Embedding only').'</option>
					</select>
					<div class="desc">'._gettext('Whether or not to allow embedding of videos.').' '._gettext('Default').'.: <b>No Embedding</b></div><br>';
					
					/* Order */
					$tpl_page .= '<label for="order">'._gettext('Order').':</label>
					<input type="text" name="order" value="'.$lineboard['order'].'">
					<div class="desc">'._gettext('Order to show board in menu list, in ascending order.').' '._gettext('Default').': <b>0</b></div><br>';
					
					/* Section */
					$tpl_page .= '<label for="section">'._gettext('Section').':</label>
					<input type="text" name="section" value="'.$lineboard['section'].'">
					<div class="desc">'._gettext('The section the board is in.  This is used for displaying the list of boards on the top and bottom of pages.').'<br>If this is set to 0, <b>it will not be shown in the menu</b>.</div><br>';
					
					/* Load balancer URL */
					$tpl_page .= '<label for="loadbalanceurl">Load balance URL:</label>
					<input type="text" name="loadbalanceurl" value="'.$lineboard['loadbalanceurl'].'">
					<div class="desc">The full http:// URL to the load balance script for this board.  The script will handle file uploads, and creation of thumbnails.  Only one script per board can be used, and there must be a src and thumb dir in the same folder as the script.  Set to nothing to disable.</div><br>';
					
					/* Load balancer password */
					$tpl_page .= '<label for="loadbalancepassword">Load balance password:</label>
					<input type="text" name="loadbalancepassword" value="'.$lineboard['loadbalancepassword'].'">
					<div class="desc">The password which will be passed to the script above.  The script must have this same password entered at the top, in the configuration area.</div><br>';
					
					/* Allowed filetypes */
					$tpl_page .= '<label>'._gettext('Allowed filetypes').':</label>
					<div class="desc">'._gettext('What filetypes users are allowed to upload.').'</div><br>';
					$filetypes = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `filetype` FROM `" . KU_DBPREFIX . "filetypes` ORDER BY `filetype` ASC");
					foreach ($filetypes as $filetype) {
						$tpl_page .= '<label for="filetype_gif">' . strtoupper($filetype['filetype']) . '</label><input type="checkbox" name="filetype_' . $filetype['id'] . '"';
						$filetype_isenabled = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $lineboard['id'] . "' AND `typeid` = '" . $filetype['id'] . "' LIMIT 1");
						if ($filetype_isenabled == 1) {
							$tpl_page .= ' checked';
						}
						$tpl_page .= '><br>';
					}
	
					/* Maximum image size */
					$tpl_page .= '<label for="maximagesize">'._gettext('Maximum image size').':</label>
					<input type="text" name="maximagesize" value="'.$lineboard['maximagesize'].'">
					<div class="desc">'._gettext('Maximum size of uploaded images, in <b>bytes</b>.') . ' ' . _gettext('Default').': <b>1024000</b></div><br>';

					/* Maximum video length */
					$tpl_page .= '<label for="maxnideolength">'._gettext('Maximum video length').':</label>
					<input type="text" name="maxvideolength" value="'.$lineboard['maxvideolength'].'">
					<div class="desc">'._gettext('Maxmimum length of uploaded videos, in <b>seconds</b>.') . ' ' . _gettext('Default').': <b>0</b> for no limit</div><br>';
					
					/* Enable sound in video */
					$tpl_page .= '<label for="enablesoundinvideo">'._gettext('Enable sound in video').':</label>
					<input type="checkbox" name="enablesoundinvideo"';
					if ($lineboard['enablesoundinvideo'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('If set to yes, video files posted can have an audio track.') . ' ' . _gettext('Default').': <b>'._gettext('No').'</b></div><br>';
					
					/* Maximum message length */
					$tpl_page .= '<label for="messagelength">'._gettext('Maximum message length').':</label>
					<input type="text" name="messagelength" value="'.$lineboard['messagelength'].'">
					<div class="desc">'._gettext('Default').': <b>8192</b></div><br>';
					
					/* Maximum board pages */
					$tpl_page .= '<label for="maxpages">'._gettext('Maximum board pages').':</label>
					<input type="text" name="maxpages" value="'.$lineboard['maxpages'].'">
					<div class="desc">'._gettext('Default').': <b>10</b></div><br>';
	
					/* Maximum thread age */
					$tpl_page .= '<label for="maxage">'._gettext('Maximum thread age (Hours)').':</label>
					<input type="text" name="maxage" value="'.$lineboard['maxage'].'">
					<div class="desc">'._gettext('Default').': <b>0</b></div><br>';
					
					/* Mark page */
					$tpl_page .= '<label for="maxage">Mark page:</label>
					<input type="text" name="markpage" value="'.$lineboard['markpage'].'">
					<div class="desc">Threads which reach this page or further will be marked to be deleted in two hours. '._gettext('Default').': <b>9</b></div><br>';
					
					/* Maximum thread replies */
					$tpl_page .= '<label for="maxreplies">'._gettext('Maximum thread replies').':</label>
					<input type="text" name="maxreplies" value="'.$lineboard['maxreplies'].'">
					<div class="desc">'._gettext('The number of replies a thread can have before autosaging to the back of the board.') . ' ' . _gettext('Default').': <b>200</b></div><br>';
					
					/* Header image */
					$tpl_page .= '<label for="image">'._gettext('Header image').':</label>
					<input type="text" name="image" value="'.$lineboard['image'].'">
					<div class="desc">'._gettext('Overrides the header set in the config file.  Leave blank to use configured global header image.  Needs to be a full url including http://.  Set to none to show no header image.').'</div><br>';
	
					/* Include header */
					$tpl_page .= '<label for="includeheader">'._gettext('Include header').':</label>
					<textarea name="includeheader" rows="12" cols="80">'.$lineboard['includeheader'].'</textarea>
					<div class="desc">'._gettext('Raw HTML which will be inserted at the top of each page of the board.').'</div><br>';
					
					/* Anonymous */
					$tpl_page .= '<label for="anonymous">Anonymous:</label>
					<input type="text" name="anonymous" value="' . $lineboard['anonymous'] . '">
					<div class="desc">'._gettext('Name to display when a name is not attached to a post.') . ' ' . _gettext('Default').': <b>Anonymous</b></div><br>';
					
					/* Locked */
					$tpl_page .= '<label for="locked">'._gettext('Locked').':</label>
					<input type="checkbox" name="locked" ';
					if ($lineboard['locked'] == '1') {
						$tpl_page .= 'checked ';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Only moderators of the board and admins can make new posts/replies').'</div><br>';
					
					/* Show ID */
					$tpl_page .= '<label for="showid">Show ID:</label>
					<input type="checkbox" name="showid" ';
					if ($lineboard['showid'] == '1') {
						$tpl_page .= 'checked ';
					}
					$tpl_page .= '>
					<div class="desc">If enabled, each post will display the poster\'s ID, which is a representation of their IP address.</div><br>';
					
					/* Show ID */
					$tpl_page .= '<label for="compactlist">Compact list:</label>
					<input type="checkbox" name="compactlist" ';
					if ($lineboard['compactlist'] == '1') {
						$tpl_page .= 'checked ';
					}
					$tpl_page .= '>
					<div class="desc">' . _gettext('Text boards only.  If enabled, the list of threads displayed on the front page will be formatted differently to be compact.') . '</div><br>';
					
					/* Enable reporting */
					$tpl_page .= '<label for="enablereporting">'._gettext('Enable reporting:').'</label>
					<input type="checkbox" name="enablereporting"';
					if ($lineboard['enablereporting'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>' . "\n" .
					'<div class="desc">'._gettext('Reporting allows users to report posts, adding the post to the report list.').' '._gettext('Default').': <b>'._gettext('Yes').'</b></div><br>';
					
					/* Enable captcha */
					$tpl_page .= '<label for="enablecaptcha">'._gettext('Enable captcha:').'</label>
					<input type="checkbox" name="enablecaptcha"';
					if ($lineboard['enablecaptcha'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Enable/disable captcha system for this board.  If captcha is enabled, in order for a user to post, they must first correctly enter the text on an image.').' '._gettext('Default').': <b>'._gettext('No').'</b></div><br>';
					
					/* Enable faptcha */
					$tpl_page .= '<label for="enablefaptcha">'._gettext('Enable faptcha:').'</label>
					<input type="checkbox" name="enablefaptcha"';
					if ($lineboard['enablefaptcha'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Enable/disable faptcha system for this board.  If faptcha is enabled, in order for a user to post, they must first correctly identify a dick, ass, tits or vagoo.').' '._gettext('Default').': <b>'._gettext('No').'</b></div><br>';
					
					/* Enable porn board */
					$tpl_page .= '<label for="enableporn">'._gettext('Enable adult content:').'</label>
					<input type="checkbox" name="enableporn"';
					if ($lineboard['enableporn'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Enable RedTube embedding on this board.').' '._gettext('Default').': <b>'._gettext('No').'</b></div><br>';
					
					/* Enable archiving */
					$tpl_page .= '<label for="enablearchiving">Enable archiving:</label>
					<input type="checkbox" name="enablearchiving"';
					if ($lineboard['enablearchiving'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">Enable/disable thread archiving for this board (not available if load balancer is used).  If enabled, when a thread is pruned or deleted through this panel with the archive checkbox checked, the thread and its images will be moved into the arch directory, found in the same directory as the board.  To function properly, you must create and set proper permissions to /boardname/arch, /boardname/arch/res, /boardname/arch/src, and /boardname/arch/thumb'.' '._gettext('Default').': <b>No</b></div><br>';
					
					/* Enable catalog */
					$tpl_page .= '<label for="enablecatalog">Enable catalog:</label>
					<input type="checkbox" name="enablecatalog"';
					if ($lineboard['enablecatalog'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">If set to yes, a catalog.html file will be built with the other files, displaying the original picture of every thread in a box.  This will only work on normal/oekaki imageboards. ' . _gettext('Default').': <b>'._gettext('Yes').'</b></div><br>';
					
					/* Enable catalog */
					$tpl_page .= '<label for="enablespoiler">Enable spoiler images:</label>
					<input type="checkbox" name="enablespoiler"';
					if ($lineboard['enablespoiler'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">Allow to mark image as spoiler on upload. ' . _gettext('Default').': <b>'._gettext('Yes').'</b></div><br>';
					
					/* Enable "no file" posting */
					$tpl_page .= '<label for="enablenofile">'._gettext('Enable "no file" posting').':</label>
					<input type="checkbox" name="enablenofile"';
					if ($lineboard['enablenofile'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('If set to yes, new threads will not require an image to be posted.') . ' ' . _gettext('Default').': <b>'._gettext('No').'</b></div><br>';
	
					/* Redirect to thread */
					$tpl_page .= '<label for="redirecttothread">'._gettext('Redirect to thread').':</label>
					<input type="checkbox" name="redirecttothread"';
					if ($lineboard['redirecttothread'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('If set to yes, users will be redirected to the thread they replied to/posted after posting.  If set to no, users will be redirected to the first page of the board.') . ' ' . _gettext('Default').': <b>'.('No').'</b></div><br>';
					
					/* Forced anonymous */
					$tpl_page .= '<label for="forcedanon">'._gettext('Forced anonymous').':</label>
					<input type="checkbox" name="forcedanon"';
					if ($lineboard['forcedanon'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('If set to yes, users will not be allowed to enter a name, making everyone appear as Anonymous') . ' ' . _gettext('Default').': <b>'._gettext('No').'</b></div><br>';
	
					/* Trial */
					$tpl_page .= '<label for="trial">'._gettext('Trial').':</label>
					<input type="checkbox" name="trial"';
					if ($lineboard['trial'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('If set to yes, this board will appear in italics in the menu') . ' ' . _gettext('Default').': <b>'._gettext('No').'</b></div><br>';
					
					/* Popular */
					$tpl_page .= '<label for="popular">'._gettext('Popular').':</label>
					<input type="checkbox" name="popular"';
					if ($lineboard['popular'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('If set to yes, this board will appear in bold in the menu') . ' ' . _gettext('Default').': <b>'._gettext('No').'</b></div><br>';
					
					/* Default style */
					$tpl_page .= '<label for="defaultstyle">'._gettext('Default style:').'</label>
					<select name="defaultstyle">
					
					<option value=""';
					$tpl_page .= ($lineboard['defaultstyle'] == '') ? ' selected' : '';
					$tpl_page .= '>Use Default</option>';
					
					$styles = explode(':', KU_STYLES);
					foreach ($styles as $stylesheet) {
						$tpl_page .= '<option value="' . $stylesheet . '"';
						$tpl_page .= ($lineboard['defaultstyle'] == $stylesheet) ? ' selected' : '';
						$tpl_page .= '>' . ucfirst($stylesheet) . '</option>';
					}
					
					$stylestxt = explode(':', KU_TXTSTYLES);
					foreach ($stylestxt as $stylesheet) {
						$tpl_page .= '<option value="' . $stylesheet . '"';
						$tpl_page .= ($lineboard['defaultstyle'] == $stylesheet) ? ' selected' : '';
						$tpl_page .= '>[TXT] ' . ucfirst($stylesheet) . '</option>';
					}
					
					$tpl_page .= '</select>
					<div class="desc">'._gettext('The style which will be set when the user first visits the board.').' '._gettext('Default').': <b>Use Default</b></div><br>';
					$tpl_page .= '<label for="run_cleaup">'._gettext('Cleanup').':</label><input type="submit" value="Run cleanup" name="run_cleanup"/><br/>';
					/* Submit form */
					$tpl_page .= '<input type="submit" name="submit_regenerate" value="'._gettext('Update and regenerate board').'"><br><input type="submit" name="submit_noregenerate" value="'._gettext('Update without regenerating board').'">
					
					</form>
					</div><br>';
				}
			} else {
				$tpl_page .= _gettext('Unable to locate a board named') . ' <b>' . $_POST['board'] . '</b>.';
			}
		} else {
			$tpl_page .= '<form action="?action=boardopts" method="post">
			<label for="board">'._gettext('Board').':</label>' .
			$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .
			'<input type="submit" value="'._gettext('Go').'">
			</form>';
		}
	}
	
	/* Search for all posts by a selected IP address and delete them */
	function deletepostsbyip() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		if($this->CheckAccess() < 4) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . ucwords(_gettext('Delete all posts by IP')) . '</h2><br>';
		if (isset($_POST['ip'])) {
			if ($_POST['ip'] != '') {
			$_POST['ip'] = preg_replace("/[^0-9.]/", "", $_POST['ip']);
				$deletion_boards = array();
				$deletion_new_boards = array();
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
				if (isset($_POST['banfromall'])) {
					$this->ModeratorsOnly();
					foreach ($results as $line) {
						$deletion_new_boards[] = $line['name'];
					}
				} else {
					foreach ($results as $line) {
						$deletion_boards[] = $line['name'];
					}
					$deletion_changed_boards = array();
					$deletion_new_boards = array();
					while (list($postkey, $postvalue) = each($_POST)) {
						if (substr($postkey, 0, 10) == 'deletefrom') {
							$deletion_changed_boards[] = substr($postkey, 10);
						}
					}
					while (list(, $deletion_thisboard_name) = each($deletion_boards)) {
						if (in_array($deletion_thisboard_name, $deletion_changed_boards)) {
							$deletion_new_boards[] = $deletion_thisboard_name;
						}
					}
					if ($deletion_new_boards == array()) {
						exitWithErrorPage(_gettext('Please select a board.'));
					}
				}
				$delete_boards = implode('|', $deletion_new_boards);
				foreach ($deletion_new_boards as $board) {
					if (!$this->CurrentUserIsModeratorOfBoard($board, $_SESSION['manageusername'])) {
						exitWithErrorPage('/' . $board . '/: ' . _gettext('You can only delete posts from boards you moderate.'));
					}
				}
				$i = 0;
				foreach ($deletion_new_boards as $deletion_board) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $deletion_board) . "'");
					foreach ($results as $line) {
						$board_name = $line['name'];
					}
					$post_list = $tc_db->GetAll("SELECT `id` FROM `" . KU_DBPREFIX . "posts_" . $board_name . "` WHERE `IS_DELETED` = '0' AND `ipmd5` = '" . md5($_POST['ip']) . "'");
					foreach ($post_list as $post) {
						$i++;

						$post_class = new Post($post['id'], $board_name);
						$post_class->Delete();
					}
					$board_class = new Board($board_name);
					$board_class->RegenerateAll();
				}
				$tpl_page .= _gettext('All threads/posts by that IP in selected boards successfully deleted.') . '<br><b>' . $i . '</b> posts were removed.<br>';
				$tpl_page .= '<hr>';
				management_addlogentry(_gettext('Deleted posts by ip') . ' ' . $_POST['ip'], 7);
			}
		}
		$tpl_page .= '<form action="?action=deletepostsbyip" method="post">
		
		<label for="ip">'._gettext('IP').':</label>
		<input type="text" name="ip"';
		if (isset($_GET['ip'])) {
			$tpl_page .= ' value="' . $_GET['ip'] . '"';
		}
		$tpl_page .= '><br>
		'._gettext('Boards').':
		
		<label for="banfromall"><b>'._gettext('All boards').'</b></label>
		<input type="checkbox" name="banfromall"><br>OR<br>' .
		$this->MakeBoardListCheckboxes('deletefrom', $this->BoardList($_SESSION['manageusername'])) .
		'<br>
		
		<input type="submit" value="'._gettext('Delete posts').'">
		
		</form><br/>';
	}
	function findpostsbyip() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		if($this->CheckAccess() < 4) {
			exitWithErrorPage('You do not have permission to access this page');
		}		
		$tpl_page .= '<h2>' . ucwords(_gettext('Find all posts by IP')) . '</h2><br>';
		if (isset($_POST['ip'])) {
			if ($_POST['ip'] != '') {
			$_POST['ip'] = preg_replace("/[^0-9.]/", "", $_POST['ip']);
				$deletion_boards = array();
				$deletion_new_boards = array();
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
				if (isset($_POST['banfromall'])) {
					$this->ModeratorsOnly();
					foreach ($results as $line) {
						$deletion_new_boards[] = $line['name'];
					}
				} else {
					foreach ($results as $line) {
						$deletion_boards[] = $line['name'];
					}
					$deletion_changed_boards = array();
					$deletion_new_boards = array();
					while (list($postkey, $postvalue) = each($_POST)) {
						if (substr($postkey, 0, 10) == 'deletefrom') {
							$deletion_changed_boards[] = substr($postkey, 10);
						}
					}
					while (list(, $deletion_thisboard_name) = each($deletion_boards)) {
						if (in_array($deletion_thisboard_name, $deletion_changed_boards)) {
							$deletion_new_boards[] = $deletion_thisboard_name;
						}
					}
					if ($deletion_new_boards == array()) {
						exitWithErrorPage(_gettext('Please select a board.'));
					}
				}
				$delete_boards = implode('|', $deletion_new_boards);
				foreach ($deletion_new_boards as $board) {
					if (!$this->CurrentUserIsModeratorOfBoard($board, $_SESSION['manageusername'])) {
						exitWithErrorPage('/' . $board . '/: ' . _gettext('You can only find posts from boards you moderate.'));
					}
				}
				$i = 0;
				foreach ($deletion_new_boards as $deletion_board) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $deletion_board) . "'");
					foreach ($results as $line) {
						$board_name = $line['name'];
					}
					$post_list = $tc_db->GetAll("SELECT HIGH_PRIORITY *  FROM `" . KU_DBPREFIX . "posts_" . $board_name . "` WHERE `IS_DELETED` = '0' AND `ipmd5` = '" . md5($_POST['ip']) . "'");
					foreach ($post_list as $post) {
						$i++;
						if($post['name'] == '') {
							$post['name'] = 'Anonymous';
						}
						$tpl_page .= '<div style="background: #D6DAF0;border: 1px solid #6f819b;padding: 5px 10px 5px 10px; width:500px;height: auto;font-family:Arial"><span style="color:#117743;font-weight:bold;">';

						$tpl_page .= $post['name'] . '</span> posted at ' . formatDate($post['postedat']) . ' on ' . "\n";
						$tpl_page .= '<a style="color:#34345c;font-family:sans-serif;font-size:14px;text-decoration: underline" href="'. KU_BOARDSPATH .'/'. $board_name .'/res/';
						if($post['parentid'] == 0) {
							$tpl_page .=  $post['id'] . '.html';
						}
						else {							
							$tpl_page .=  $post['parentid'] . '.html#'. $post['id'];
						}
						$tpl_page .= '"><b>/'.$board_name.'/</b></a>';
						$tpl_page .= '<br/><a href="'. KU_BOARDSPATH . '/' . $board_name . '/src/'.$post['filename'].'.'.$post['filetype'].'"><img width="'.$post['thumb_w'].'" height="'.$post['thumb_h'].'" src="'. KU_BOARDSPATH . '/' . $board_name . '/thumb/'.$post['filename'].'s.'.$post['filetype'].'" alt="" border="0" style="float: left;padding-right:10px;padding-top:5px;"/></a>'."\n".'<span>' .$post['message'] . '</span><br style="clear: both"/></div><br/>' . "\n";
					}
					$board_class = new Board($board_name);
					$board_class->RegenerateAll();
				}
				$tpl_page .= _gettext('<b>' . $i . '</b> posts were found.<br>');
				$tpl_page .= '<hr>';
				management_addlogentry(_gettext('Searched posts by ip') . ' ' . $_POST['ip'], 7);
			}
		}
		$tpl_page .= '<form action="?action=findpostsbyip" method="post">
		
		<label for="ip">'._gettext('IP').':</label>
		<input type="text" name="ip"';
		if (isset($_GET['ip'])) {
			$tpl_page .= ' value="' . $_GET['ip'] . '"';
		}
		$tpl_page .= '><br>
		'._gettext('Boards').':
		
		<label for="banfromall"><b>'._gettext('All boards').'</b></label>
		<input type="checkbox" name="banfromall"><br>OR<br>' .
		$this->MakeBoardListCheckboxes('deletefrom', $this->BoardList($_SESSION['manageusername'])) .
		'<br>
		
		<input type="submit" value="'._gettext('Find posts').'">
		
		</form><br/>';
	}
	function unstickypost() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		$this->ModeratorsOnly();
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Manage stickies')) . '</h2><br>';
		if (isset($_GET['postid']) && isset($_GET['board'])) {
			if ($_GET['postid'] > 0 && $_GET['board'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $_GET['board']) . "'");
				if (count($results) > 0) {
					if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
						exitWithErrorPage(_gettext('You are not a moderator of this board.'));
					}
					foreach ($results as $line) {
						$sticky_board_name = $line['name'];
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $sticky_board_name . "` WHERE `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['postid']) . "'");
					if (count($results) > 0) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts_" . $sticky_board_name . "` SET `stickied` = '0' WHERE `parentid` = '0' AND `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['postid']) . "'");
						$board_class = new Board($sticky_board_name);
						$board_class->RegenerateAll();
						$tpl_page .= _gettext('Thread successfully un-stickied');
						management_addlogentry(_gettext('Unstickied thread') . ' #' . mysqli_real_escape_string($tc_db->link, $_GET['postid']) . ' - /' . mysqli_real_escape_string($tc_db->link, $_GET['board']) . '/', 5);
					} else {
						$tpl_page .= _gettext('Invalid thread ID.  This may have been caused by the thread recently being deleted.');
					}
				} else {
					$tpl_page .= _gettext('Invalid board directory.');
				}
				$tpl_page .= '<hr>';
			}
		}
		$tpl_page .= $this->stickyforms();
	}
	function manageboard() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		$this->ModeratorsOnly();
		if($this->CheckAccess() < 9) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . ucwords(_gettext('Manage boards')) . '</h2><br>';
		if(isset($_SESSION['updateboard']) && isset($_POST['updateboard'])) {
			$updateboard_enablecaptcha = isset($_POST['enablecaptcha']) ? '1' : '0';
			$updateboard_enablefaptcha = isset($_POST['enablefaptcha']) ? '1' : '0';
			$updateboard_locked = isset($_POST['locked']) ? '1' : '0';
			$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "boards` SET `locked` = '" . mysqli_real_escape_string($tc_db->link, $updateboard_locked) . "' , `enablefaptcha` = '" . mysqli_real_escape_string($tc_db->link, $updateboard_enablefaptcha) . "' , `enablecaptcha` = '" . mysqli_real_escape_string($tc_db->link, $updateboard_enablecaptcha) . "' WHERE `name` = '".$_SESSION['updateboard']."'");
			$board_class = new Board($_SESSION['updateboard']);
			$board_class->RegenerateAll();
			require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
			$menu_class = new Menu();
			$menu_class->Generate();
			$tpl_page .= _gettext('Update successful.');
			management_addlogentry(_gettext('Updated board configuration') . " - /" . $_SESSION['updateboard'] . "/", 4);
			unset($_SESSION['updateboard']);
		}
		else if(isset($_POST['board'])) {
			if (!$this->CurrentUserIsModeratorOfBoard($_POST['board'], $_SESSION['manageusername'])) {
				exitWithErrorPage(_gettext('You are not a moderator of this board.'));
			}		
		$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $_POST['board']) . "'");
			if (count($resultsboard) > 0) {
				foreach ($resultsboard as $lineboard) {
					$tpl_page .= '<div class="container">';
					$tpl_page .= '<form action="?action=manageboard&updateboard=1" method="post">';
					/* Directory */
					$tpl_page .= '<label for="board">'._gettext('Directory').':</label>
					<input type="text" name="board" value="'.$_POST['board'].'" disabled>
					<div class="desc">'._gettext('The directory of the board.').'</div><br>';
					$_SESSION['updateboard'] = $_POST['board'];
					/* Locked */
					$tpl_page .= '<label for="locked">'._gettext('Locked').':</label>
					<input type="checkbox" name="locked" ';
					if ($lineboard['locked'] == '1') {
						$tpl_page .= 'checked ';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Only moderators of the board and admins can make new posts/replies').'</div><br>';
					
					/* Enable captcha */
					$tpl_page .= '<label for="enablecaptcha">'._gettext('Enable captcha:').'</label>
					<input type="checkbox" name="enablecaptcha"';
					if ($lineboard['enablecaptcha'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Enable/disable captcha system for this board.  If captcha is enabled, in order for a user to post, they must first correctly enter the text on an image.').' '._gettext('Default').': <b>'._gettext('No').'</b></div><br>';
					
					/* Enable faptcha */
					$tpl_page .= '<label for="enablefaptcha">'._gettext('Enable faptcha:').'</label>
					<input type="checkbox" name="enablefaptcha"';
					if ($lineboard['enablefaptcha'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '>
					<div class="desc">'._gettext('Enable/disable faptcha system for this board.  If faptcha is enabled, in order for a user to post, they must first correctly identify a dick, ass, tits or vagoo.').' '._gettext('Default').': <b>'._gettext('No').'</b></div><br/><input type="submit" name="updateboard" value="Update"</div><br>';					
			}
		}
	}
		else {
				$tpl_page .= '<form action="?action=manageboard&updateboard=1" method="post">
				<label for="board">'._gettext('Board').':</label>' .
				$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .
				'<input type="submit" value="'._gettext('Go').'">
				</form>';
		}
	}
	function stickypost() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		$this->ModeratorsOnly();
		if($this->CheckAccess() < 5) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . ucwords(_gettext('Manage stickies')) . '</h2><br>';
		if (isset($_GET['postid']) && isset($_GET['board'])) {
			if ($_GET['postid'] > 0 && $_GET['board'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . $_GET['board'] . "'");
				if (count($results) > 0) {
					if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
						exitWithErrorPage(_gettext('You are not a moderator of this board.'));
					}
					foreach ($results as $line) {
						$sticky_board_name = $line['name'];
					}
					$result = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "posts_" . $sticky_board_name . "` WHERE `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['postid']) . "'");
					if ($result > 0) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts_" . $sticky_board_name . "` SET `stickied` = '1' WHERE `parentid` = '0' AND `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['postid']) . "'");
						$board_class = new Board($sticky_board_name);
						$board_class->RegenerateAll();
						$tpl_page .= _gettext('Thread successfully stickied.');
						management_addlogentry(_gettext('Stickied thread') . ' #' . mysqli_real_escape_string($tc_db->link, $_GET['postid']) . ' - /' . mysqli_real_escape_string($tc_db->link, $_GET['board']) . '/', 5);
					} else {
						$tpl_page .= _gettext('Invalid thread ID.  This may have been caused by the thread recently being deleted.');
					}
				} else {
					$tpl_page .= _gettext('Invalid board directory.');
				}
				$tpl_page .= '<hr>';
			}
		}
		$tpl_page .= $this->stickyforms();
	}
	
	/* Create forms for stickying a post */
	function stickyforms() {
		global $tc_db;
		
		$output = '<table width="100%" border="0">
		<tr><td width="50%"><h1>' . _gettext('Sticky') . '</h1></td><td width="50%"><h1>' . _gettext('Unsticky') . '</h1></td></tr>
		<tr><td><br>
				
		<form action="manage_page.php" method="get"><input type="hidden" name="action" value="stickypost">
		<label for="board">'._gettext('Board').':</label>' .
		$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .
		'<br>
		
		<label for="postid">'._gettext('Thread').':</label>
		<input type="text" name="postid"><br>
		
		<label for="submit">&nbsp;</label>
		<input name="submit" type="submit" value="'._gettext('Sticky').'">
		</form>
		</td><td>';
		$results_boards = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		foreach ($results_boards as $line_board) {
			$output .= '<h2>/' . $line_board['name'] . '/</h2>';
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "posts_" . $line_board['name'] . "` WHERE `IS_DELETED` = '0' AND `parentid` = '0' AND `stickied` = '1'");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$output .= '<a href="?action=unstickypost&board=' . $line_board['name'] . '&postid=' . $line['id'] . '">#' . $line['id'] . '</a><br>';
				}
			} else {
				$output .= 'No stickied threads.<br>';
			}
		}
		$output .= '</td></tr></table>';
		
		return $output;
	}
	
	function lockpost() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		$this->ModeratorsOnly();
		if($this->CheckAccess() < 5) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . ucwords(_gettext('Manage locked threads')) . '</h2><br>';
		if (isset($_GET['postid']) && isset($_GET['board'])) {
			if ($_GET['postid'] > 0 && $_GET['board'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $_GET['board']) . "'");
				if (count($results) > 0) {
					if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
						exitWithErrorPage(_gettext('You are not a moderator of this board.'));
					}
					foreach ($results as $line) {
						$lock_board_name = $line['name'];
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $lock_board_name . "` WHERE `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['postid']) . "'");
					if (count($results) > 0) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts_" . $lock_board_name . "` SET `locked` = '1' WHERE `parentid` = '0' AND `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['postid']) . "'");
						$board_class = new Board($lock_board_name);
						$board_class->RegenerateAll();
						$tpl_page .= _gettext('Thread successfully locked.');
						management_addlogentry(_gettext('Locked thread') . ' #' . mysqli_real_escape_string($tc_db->link, $_GET['postid']) . ' - /' . mysqli_real_escape_string($tc_db->link, $_GET['board']) . '/', 5);
					} else {
						$tpl_page .= _gettext('Invalid thread ID.  This may have been caused by the thread recently being deleted.');
					}
				} else {
					$tpl_page .= _gettext('Invalid board directory.');
				}
				$tpl_page .= '<hr>';
			}
		}
		$tpl_page .= $this->lockforms();
	}
	
	function unlockpost() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Manage locked threads')) . '</h2><br>';
		if ($_GET['postid'] > 0 && $_GET['board'] != '') {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $_GET['board']) . "'");
			if (count($results) > 0) {
				if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
					exitWithErrorPage(_gettext('You are not a moderator of this board.'));
				}
				foreach ($results as $line) {
					$lock_board_name = $line['name'];
				}
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $lock_board_name . "` WHERE `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['postid']) . "'");
				if (count($results) > 0) {
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts_" . $lock_board_name . "` SET `locked` = '0' WHERE `parentid` = '0' AND `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['postid']) . "'");
					$board_class = new Board($lock_board_name);
					$board_class->RegenerateAll();
					$tpl_page .= _gettext('Thread successfully unlocked.');
					management_addlogentry(_gettext('Unlocked thread') . ' #' . $_GET['postid'] . ' - /' . $_GET['board'] . '/', 5);
				} else {
					$tpl_page .= _gettext('Invalid thread ID.  This may have been caused by the thread recently being deleted.');
				}
			} else {
				$tpl_page .= _gettext('Invalid board directory.');
			}
			$tpl_page .= '<hr>';
		}
		$tpl_page .= $this->lockforms();
	}
	
	function lockforms() {
		global $tc_db;
		
		$output = '<table width="100%" border="0">
		<tr><td width="50%"><h1>' . _gettext('Lock') . '</h1></td><td width="50%"><h1>' . _gettext('Unlock') . '</h1></td></tr>
		<tr><td><br>
				
		<form action="manage_page.php" method="get"><input type="hidden" name="action" value="lockpost">
		<label for="board">'._gettext('Board').':</label>' .
		$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .
		'<br>
		
		<label for="postid">'._gettext('Thread').':</label>
		<input type="text" name="postid"><br>
		
		<label for="submit">&nbsp;</label>
		<input name="submit" type="submit" value="'._gettext('Lock').'">
		</form>
		</td><td>';
		$results_boards = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		foreach ($results_boards as $line_board) {
			$output .= '<h2>/' . $line_board['name'] . '/</h2>';
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "posts_" . $line_board['name'] . "` WHERE `IS_DELETED` = '0' AND `parentid` = '0' AND `locked` = '1'");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$output .= '<a href="?action=unlockpost&board=' . $line_board['name'] . '&postid=' . $line['id'] . '">#' . $line['id'] . '</a><br>';
				}
			} else {
				$output .= 'No locked threads.<br>';
			}
		}
		$output .= '</td></tr></table>';
		
		return $output;
	}
	
	/* Run delorphanreplies() verbosely, followed by delunusedimages() verbosely */
	function cleanup() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . _gettext('Cleanup') . '</h2><br>';
		if(isset($_POST['run'])) {
			if(!isset($_POST['opt']) && !isset($_POST['delold']) && !isset($_POST['delunused']) && !isset($_POST['delnon']))  {
				exitwithErrorPage('You must select at least one clean up option');
			}
			if(isset($_POST['delnon'])) {
				$tpl_page .= _gettext('Deleting non-deleted replies which belong to deleted threads...').'<hr>';
				$this->delorphanreplies(true);
			}
			if(isset($_POST['delunused'])) {
				$tpl_page .= _gettext('Deleting unused images...').'<hr>';
				$this->delunusedimages(true);
			}
			if(isset($_POST['delold'])) {
				$tpl_page .= _gettext('Removing posts deleted more than one week ago from the database...').'<hr>';
				$results = $tc_db->GetAll("SELECT `name`, `type` FROM `" . KU_DBPREFIX . "boards`");
				foreach ($results AS $line) {
					if ($line['type'] != 1) {
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "posts_" . $line['name'] . "` WHERE `IS_DELETED` = 1 AND `deletedat` < " . (time() - 604800) . "");
					}
				}
			}
			if(isset($_POST['opt'])) {
				$tpl_page .= _gettext('Optimizing all tables in database...').'<hr>';
				$results = $tc_db->GetAll("SHOW TABLES");
				foreach ($results AS $line) {
					$tc_db->Execute("OPTIMIZE TABLE `" . $line[0] . "`");
				}
			}
			$tpl_page .= _gettext('Cleanup finished.<br/><br/>');
			management_addlogentry(_gettext('Ran cleanup'), 2);
		}
		if(!isset($_POST['run'])) {
		$tpl_page .= '<form action="manage_page.php?action=cleanup" method="post">
			<label for="delnon">Delete non-deleted replies which belong to deleted threads.</label><input type="checkbox" name="delnon"><br/>	
			<label for="delunused">Delete unused images.</label><input type="checkbox" name="delunused"><br/>	
			<label for="delold">Remove posts deleted more than one week ago from the database.</label><input type="checkbox" name="delold"><br/>	
			<label for="opt">Optimize all tables in database.</label><input type="checkbox" name="opt"><br/>	
			<input type="submit" name="run" value="Run cleanup"/>
			</form>';
		}
	}
	/* Run migrations */
	function update() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();

		$tables_to_update_sql = "SELECT t.`TABLE_NAME`  
FROM `" . KU_DBPREFIX . "boards` b
JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = '" . KU_DBDATABASE . "' 
	AND t.TABLE_NAME = CONCAT('" . KU_DBPREFIX . "posts_', b.name)
LEFT JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = '" . KU_DBDATABASE . "' 
	AND c.TABLE_NAME = CONCAT('" . KU_DBPREFIX . "posts_', b.name)
	AND c.COLUMN_NAME = 'initial_board'
WHERE c.COLUMN_NAME IS NULL
ORDER BY t.`TABLE_NAME` ASC";

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$tables = $tc_db->GetAll($tables_to_update_sql);
			$tables = array_map(function($item) { return $item["TABLE_NAME"]; }, $tables);

			foreach ($tables as $table) {
				$tc_db->Execute("ALTER TABLE `$table`
ADD COLUMN `initial_board` VARCHAR(75) NULL DEFAULT NULL AFTER `reviewed`");

				echo "Table $table was updated.<br>";
			}

			echo "Finished.<br>";
		}

		$tables = $tc_db->GetAll($tables_to_update_sql);
		$tables = array_map(function($item) { return $item["TABLE_NAME"]; }, $tables);

		$tpl_page .= '<h2>' . ucwords(_gettext('Update Database')) . '</h2><br>';

		if (count($tables)) {
			$tpl_page .= 'Tables to update: ' . implode(', ', $tables) . '<br><br>';
		} else {
			$tpl_page .= 'All tables are already updated<br><br>';
		}

		$tpl_page .= '<form action="manage_page.php?action=update" method="post">
		
		<label for="directory">Add initial_board field to all posts tables</label>
		
		<input type="submit" value="'._gettext('Update').'">
		
		</form>';
	}
	/* Addition, modification, deletion, and viewing of bans */
	function bans() {
		global $tc_db, $smarty, $tpl_page, $bans_class;
		$this->ModeratorsOnly();
		if($this->CheckAccess() < 6) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . _gettext('Bans') . '</h2><br>';
		$ban_ip = '';
		if (isset($_POST['ip']) && isset($_POST['seconds'])) {
			if ($_POST['ip'] != '' ) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `ipmd5` = '" . md5($_POST['ip']) . "'");
				if (count($results) == 0) {
					if ($_POST['seconds'] >= 0) {
						if(isset($_POST['quicksubmit'])) {
							if ($_POST['quickbanboard'] != '' && $_POST['quickbanthreadid'] != '') {
								if(isset($_POST['proxy'])) {
									$reason = 'Proxy';
								}
								if(isset($_POST['spam'])) {
									$reason = 'Spam';
								}
								if(isset($_POST['cp'])) {
									$reason = 'Child porn';
								}
								if ($bans_class->BanUser($_POST['ip'], $_SESSION['manageusername'], 1, 0, '', $reason, 0, 0, 0, $reason)) {
									if (KU_BANMSG != '' && isset($_POST['quickbanpostid'])) {
										$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `parentid`, `message` FROM `".KU_DBPREFIX."posts_".mysqli_real_escape_string($tc_db->link, $_POST['quickbanboard'])."` WHERE `id` = ".mysqli_real_escape_string($tc_db->link, $_POST['quickbanpostid'])." LIMIT 1");
										foreach($results AS $line) {
											if(isset($_POST['addbanmsg'])) {
												$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts_".mysqli_real_escape_string($tc_db->link, $_POST['quickbanboard'])."` SET `message` = '".mysqli_real_escape_string($tc_db->link, $line['message'] . KU_BANMSG)."' WHERE `id` = ".mysqli_real_escape_string($tc_db->link, $_POST['quickbanpostid'])." LIMIT 1");
											}
											clearPostCache($_POST['quickbanpostid'], $_POST['quickbanboard']);
											$board_class = new Board($_POST['quickbanboard']);
											if ($line['parentid']==0) {
												$board_class->RegenerateThread($_POST['quickbanpostid']);
											} else {
												$board_class->RegenerateThread($line['parentid']);
											}
											if(isset($_POST['quickdelmsg2'])) {
												$post_class = new Post(mysqli_real_escape_string($tc_db->link, $_POST['quickbanpostid']), mysqli_real_escape_string($tc_db->link, $_POST['quickbanboard']));
												$post_class->Delete();
												management_addlogentry(_gettext('Deleted post') . ' #<a href="?action=viewdeletedthread&board='.$board_class->board_dir.'&thread=' . $_POST['quickbanpostid'] . '">' . $_POST['quickbanpostid']  . '</a> - ' . '/' . $board_class->board_dir . '/', 7);
											}
										}
										// $board_class->RegenerateAll();
										$board_class->RegeneratePages();
									}
									$tpl_page .= _gettext('Ban successfully placed.');
									$logentry = _gettext('Banned') . ' ' . $_POST['ip'] . ' without expiration - Reason: ' . $reason . ' - Banned from: All boards';
									management_addlogentry($logentry, 8);
									$tpl_page .= '<br><br><meta http-equiv="refresh" content="1;url=' . KU_BOARDSPATH . '/' . $_POST['quickbanboard'] . '/';
									if ($_POST['quickbanthreadid'] != '0') {
										$tpl_page .= 'res/' . $_POST['quickbanthreadid'] . '.html';
									}
									$tpl_page .= '"><a href="' . KU_BOARDSPATH . '/' . $_POST['quickbanboard'] . '/';
									if ($_POST['quickbanthreadid'] != '0') {
										$tpl_page .= 'res/' . $_POST['quickbanthreadid'] . '.html';
									}
									$tpl_page .= '">' . _gettext('Redirecting') . '</a>...';
									} else {
									exitWithErrorPage(_gettext('Sorry, a generic error has occurred.'));
								}
							}
						}
						else {
						$banning_boards = array();
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
						foreach ($results as $line) {
							$banning_boards = array_merge($banning_boards, array($line['name']));
						}
						$banning_changed_boards = array();
						$banning_new_boards = array();
						while (list($postkey, $postvalue) = each($_POST)) {
							if (substr($postkey, 0, 10) == "bannedfrom") {
								$banning_changed_boards = array_merge($banning_changed_boards, array(substr($postkey, 10)));
							}
						}
						while (list(, $banning_thisboard_name) = each($banning_boards)) {
							if (in_array($banning_thisboard_name, $banning_changed_boards)) {
								$banning_new_boards = array_merge($banning_new_boards, array($banning_thisboard_name));
							}
						}
						if ($banning_new_boards == array() && $_POST['banfromall'] != 'on') {
							exitWithErrorPage(_gettext('Please select a board.'));
						}
						$ban_globalban = (isset($_POST['banfromall'])) ? '1' : '0';
						if ($_POST['allowread'] == '1' || $_POST['allowread'] == '0') {
							$ban_allowread = $_POST['allowread'];
						} else {
							$ban_allowread = '1';
						}
						if($ban_allowread == 0) {
							if($this->CheckAccess() < 8) {
								exitWithErrorPage('You do not have permission to site ban');
							}
						}
						if(isset($_POST['banfromall'])) {
							if($this->CheckAccess() < 7) {
								exitWithErrorPage('You do not have permission to global ban');
							}
						}
						if ($ban_globalban == '0') {
							$ban_boards = implode('|', $banning_new_boards);
							foreach (explode('|', $ban_boards) as $board) {
								if (!$this->CurrentUserIsModeratorOfBoard($board, $_SESSION['manageusername'])) {
									exitWithErrorPage(_gettext('You can only make board specific bans to boards which you moderate.'));
								}
							}
						} else {
							$ban_boards = '';
						}
						if ($_POST['seconds'] == '0') {
							/* Permanent ban */
							$ban_duration = '0';
						} else {
							/* Timed ban */
							$ban_duration = mysqli_real_escape_string($tc_db->link, $_POST['seconds']);
						}
						if ($_POST['type'] == '0') {
							/* Normal IP address ban */
							$ban_type = '0';
							$_POST['ip'] = preg_replace("/[^0-9.]/", "", $_POST['ip']);
						} else {
							if($this->CheckAccess() < 9) {
								exitWithErrorPage('You do not have permission to access this page.');
							}
							/* IP range ban */
							$ban_type = '1';
						}
						if(empty($_POST['note'])) {
							exitWithErrorPage('Enter a Moderator note');
						}			
						if (KU_APPEAL != '') {
							$ban_appealat = $_POST['appealdays'] * 86400;
							if ($ban_appealat > 0) {
								$ban_appealat += time();
							}
						} else {
							$ban_appealat = 0;
						}
						if ($bans_class->BanUser(mysqli_real_escape_string($tc_db->link, $_POST['ip']), $_SESSION['manageusername'], $ban_globalban, $ban_duration, $ban_boards, mysqli_real_escape_string($tc_db->link, $_POST['reason']),  mysqli_real_escape_string($tc_db->link, $ban_appealat), $ban_type, $ban_allowread, mysqli_real_escape_string($tc_db->link, $_POST['note']))) {
							if (KU_BANMSG != '' && isset($_POST['quickbanpostid'])) {
								$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `parentid`, `message` FROM `".KU_DBPREFIX."posts_".mysqli_real_escape_string($tc_db->link, $_POST['quickbanboard'])."` WHERE `id` = ".mysqli_real_escape_string($tc_db->link, $_POST['quickbanpostid'])." LIMIT 1");
								foreach($results AS $line) {
									if(isset($_POST['addbanmsg'])) {
										$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts_".mysqli_real_escape_string($tc_db->link, $_POST['quickbanboard'])."` SET `message` = '".mysqli_real_escape_string($tc_db->link, $line['message'] . KU_BANMSG)."' WHERE `id` = ".mysqli_real_escape_string($tc_db->link, $_POST['quickbanpostid'])." LIMIT 1");
									}
									clearPostCache($_POST['quickbanpostid'], $_POST['quickbanboard']);
									$board_class = new Board($_POST['quickbanboard']);
									if ($line['parentid']==0) {
										$board_class->RegenerateThread($_POST['quickbanpostid']);
									} else {
										$board_class->RegenerateThread($line['parentid']);
									}
									if(isset($_POST['quickdelmsg'])) {
										$post_class = new Post(mysqli_real_escape_string($tc_db->link, $_POST['quickbanpostid']), mysqli_real_escape_string($tc_db->link, $_POST['quickbanboard']));
										$post_class->Delete();
										management_addlogentry(_gettext('Deleted post') . ' #<a href="?action=viewdeletedthread&board='.$board_class->board_dir.'&thread=' . $_POST['quickbanpostid'] . '">' . $_POST['quickbanpostid']  . '</a> - ' . '/' . $board_class->board_dir . '/', 7);
									}
									$board_class->RegenerateAll();
								}
							}
							$tpl_page .= _gettext('Ban successfully placed.');
						} else {
							exitWithErrorPage(_gettext('Sorry, a generic error has occurred.'));
						}
						$logentry = _gettext('Banned') . ' ' . $_POST['ip'];
						if ($_POST['seconds'] == '0') {
							$logentry .= ' without expiration';
						} else {
							$logentry .= ' until ' . date('F j, Y, g:i a', time() + $_POST['seconds']);
						}
						$logentry .= ' - ' . _gettext('Reason') . ': ' . $_POST['reason'] . ' - ' . _gettext('Banned from') . ': ';
						if ($ban_globalban == '1') {
							$logentry .= _gettext('All boards') . ' ';
						} else {
							$logentry .= '/' . implode('/, /', explode('|', $ban_boards)) . '/ ';
						}
						management_addlogentry($logentry, 8);
						
						if (isset($_POST['banhashtime'])) {
							if ($_POST['banhashtime'] !== '' && $_POST['hash'] !== '' && $_POST['banhashtime'] >= 0) {
								$results = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `".KU_DBPREFIX."bannedhashes` WHERE `md5` = '".mysqli_real_escape_string($tc_db->link, $_POST['hash'])."' LIMIT 1");
								if ($results == 0) {
									$tc_db->Execute("INSERT INTO `".KU_DBPREFIX."bannedhashes` ( `md5` , `bantime` , `description` ) VALUES ( '".mysqli_real_escape_string($tc_db->link, $_POST['hash'])."' , '".mysqli_real_escape_string($tc_db->link, $_POST['banhashtime'])."' , '".mysqli_real_escape_string($tc_db->link, $_POST['banhashdesc'])."' )");
									management_addlogentry('Banned md5 hash ' . $_POST['hash'] . ' with a description of ' . $_POST['banhashdesc'], 8);
								}
							}
						}
						if (isset($_POST['quickbanboard']) && $_POST['quickbanboard'] != '' && $_POST['quickbanthreadid'] != '') {
							$tpl_page .= '<br><br><meta http-equiv="refresh" content="1;url=' . KU_BOARDSPATH . '/' . $_POST['quickbanboard'] . '/';
							if ($_POST['quickbanthreadid'] != '0') {
								$tpl_page .= 'res/' . $_POST['quickbanthreadid'] . '.html';
							}
							$tpl_page .= '"><a href="' . KU_BOARDSPATH . '/' . $_POST['quickbanboard'] . '/';
							if ($_POST['quickbanthreadid'] != '0') {
								$tpl_page .= 'res/' . $_POST['quickbanthreadid'] . '.html';
							}
							$tpl_page .= '">' . _gettext('Redirecting') . '</a>...';
						}
					}
					} else {
						$tpl_page .= _gettext('Please enter a positive amount of seconds, or zero for a permanent ban.');
					}
				} else {
					$tpl_page .= _gettext('That IP has already been banned.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['delban'])) {
			if ($_GET['delban'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['delban']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$unban_ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
					}
					$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "banlist` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['delban']) . "'");
					$bans_class->UpdateHtaccess();
					$tpl_page .= _gettext('Ban successfully removed.');
					management_addlogentry(_gettext('Unbanned') . ' ' . $unban_ip, 8);
					if (isset($_GET['sm'])) {
						sendStaffMail('Ban appeal at ' . KU_NAME . ' for ' . $unban_ip, wordwrap('The following action has taken place on this appeal:' . "\n" .
							'Ban removed.', 70));
					}
				} else {
					$tpl_page .= _gettext('Invalid ban ID');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['delhashid'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "bannedhashes` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['delhashid']) . "'");
			if (count($results) > 0) {
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "bannedhashes` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['delhashid']) . "'");
				$tpl_page .= 'Hash removed from ban list.<hr>';
			}
		} elseif (isset($_GET['denyappeal'])) {
			if ($_GET['denyappeal'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['denyappeal']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$unban_ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
					}
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "banlist` SET `appealat` = -2 WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['denyappeal']) . "'");
					$bans_class->UpdateHtaccess();
					$tpl_page .= _gettext('Appeal successfully denied.');
					management_addlogentry(_gettext('Denied the ban appeal for') . ' ' . $unban_ip, 8);
					if (isset($_GET['sm'])) {
						sendStaffMail('Ban appeal at ' . KU_NAME . ' for ' . $unban_ip, wordwrap('The following action has taken place on this appeal:' . "\n" .
							'Appeal denied.', 70));
					}
				} else {
					$tpl_page .= _gettext('Invalid ban ID');
				}
				$tpl_page .= '<hr>';
			}
		}
		if (isset($_GET['banboard']) && isset($_GET['banpost'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $_GET['banboard']) . "'");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$ban_board_name = $line['name'];
				}
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $ban_board_name . "` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['banpost']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$ban_ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
						$ban_hash = $line['filemd5'];
						$ban_parentid = $line['parentid'];
					}
				} else {
					$tpl_page .= _gettext('A post with that ID does not exist.') . '<hr>';
				}
			}
		}
		flush();
		
		$tpl_page .= '<form action="manage_page.php?action=bans" method="post" name="banform">';
		
		$isquickban = false;
		if ($ban_ip != '') {
			$tpl_page .= '<input type="hidden" name="quickbanboard" value="' . $_GET['banboard'] . '"><input type="hidden" name="quickbanthreadid" value="' . $ban_parentid . '"><input type="hidden" name="quickbanpostid" value="' . $_GET['banpost'] . '">';
			$isquickban = true;
		} elseif (isset($_GET['ip'])) {
			$ban_ip = $_GET['ip'];
		}
		if ($isquickban) {	
			$tpl_page .= '<fieldset><legend>Quick ban</legend><form action="?manage_page.php?action=bans&quickban=1"><label for="proxy">Proxy</label><input type="checkbox" name="proxy"/><label for="cp">CP</label><input type="checkbox" name="cp"/><label for="spam">Spam</label><input type="checkbox" name="spam"/><br/>';
			$tpl_page .='<label for="quickdelmsg">Delete post:</label>
			<input type="checkbox" name="quickdelmsg2" checked="checked"/>
			<div class="desc">If checked, the post will be deleted as well.</div><br/><input type="submit" value="Go" name="quicksubmit"/></fieldset>';
		}
		$tpl_page .= '<fieldset>
		<legend>IP address lookup</legend>';
		if (isset($_POST['post_id']) && isset($_POST['board'])) {
			if(!empty($_POST['post_id']) && !empty($_POST['board'])) {	
				$board = $_POST['board'];
				$post_id = $_POST['post_id'];	
				$result = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `".KU_DBPREFIX."posts_". mysqli_real_escape_string($tc_db->link, $board) ."` WHERE id = '" . $post_id . "'");		
				if (count($result) == 1) {
					foreach ($result as $line) {				
						$ip_mod = md5_decrypt($line['ip'], KU_RANDOMSEED);
					}
					$tpl_page .= 'IP address: <b>'. $ip_mod . '</b><br/><br/>' . "\n";
				}
				else {
					$tpl_page .= "Invalid post ID, try again.<br/><br/>" . "\n";
				}
			}
		}
		$tpl_page .= '<label for="board">'._gettext('Board').':</label>
		'.$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .'
		<br/>
		<label for="post_id">'._gettext('Post ID').':</label>
		<input type="text" name="post_id" value="">
		<br/>
		<input type="submit" value="'._gettext('Get IP').'">
		</fieldset>
		<fieldset>
		<legend>IP address and ban type</legend>
		<label for="ip">'._gettext('IP').':</label>
		<input type="text" name="ip" value="'.$ban_ip.'">';
		if ($ban_ip != '') { $tpl_page .= '&nbsp;&nbsp;<a href="?action=deletepostsbyip&ip=' . $ban_ip . '" target="_blank">' . _gettext('Delete all posts by this IP') . '</a>'; }
		$tpl_page .= '<br>
		<label for="allowread">Allow read:</label>
		<select name="allowread"><option value="1">Yes</option><option value="0">No</option></select>
		<div class="desc">Whether or not the user(s) affected by this ban will be allowed to read the boards.<br/><b>Warning</b>: Selecting No will prevent any reading of any page on the level of the boards on the server &amp; a global site ban.</div><br>
		
		<label for="type">Type:</label>
		<select name="type"><option value="0">Single IP</option><option value="1">IP Range</option></select>
		<div class="desc">The type of the ban.  A single IP can be banned by providing the full address, or an IP range can be banned by providing the range you wish to ban. If you set a range ban, the format should be *.*.*.* e.g. 72.234.*.*</div><br>';
		
		if ($isquickban && KU_BANMSG != '') {
			$tpl_page .= '<label for="addbanmsg">Add ban message:</label>
			<input type="checkbox" name="addbanmsg" checked>
			<div class="desc">If checked, the configured ban message will be added to the end of the post.</div><br>';
			$tpl_page .='<label for="quickdelmsg">Delete post:</label>
			<input type="checkbox" name="quickdelmsg"/>
			<div class="desc">If checked, the post will be deleted as well.</div><br>';
		}
		
		$tpl_page .= '</fieldset>
		<fieldset>
		<legend>' . _gettext('Ban from') . '</legend>
		<label for="banfromall"><b>'._gettext('All boards').'</b></label>
		<input type="checkbox" name="banfromall"><br><hr><br>' .
		$this->MakeBoardListCheckboxes('bannedfrom', $this->BoardList($_SESSION['manageusername'])) .
		'</fieldset>';
		
		if (isset($ban_hash)) {
			$tpl_page .= '<fieldset>
			<legend>Ban file</legend>
			<input type="hidden" name="hash" value="' . $ban_hash . '">
			
			<label for="banhashtime">Ban file hash for:</label>
			<input type="text" name="banhashtime">
			<div class="desc">The amount of time to ban the hash of the image which was posted under this ID.  Leave blank to not ban the image, 0 for a infinite global ban, or any number of seconds for that duration of a global ban.</div><br>
			</fieldset>';
		}
		
		$tpl_page .= '<fieldset>
			<legend>Ban duration, reason, and appeal information</legend>
<script>
function setvalue(id, newvalue) {
	var container = document.getElementById(id);
	container.value = newvalue;
}
function reason(why) {
	var reasonobj = document.getElementById("reason");
	var modnotobj = document.getElementById("modnote");
	reasonobj.value = why;
	modnotobj.value = why;
}
</script>
		<a name="seconds"></a><label for="seconds">'._gettext('Seconds').':</label>
		<input type="text" name="seconds" id="seconds">
		<div class="desc">'._gettext('Presets').':&nbsp;<a href="#seconds" onclick="setvalue(\'seconds\', 3600);">1hr</a>&nbsp;<a href="#seconds" onclick="setvalue(\'seconds\', 86400);">1d</a>&nbsp;<a href="#seconds" onclick="setvalue(\'seconds\', 604800);">1w</a>&nbsp;<a href="#seconds" onclick="setvalue(\'seconds\', 1209600);">2w</a>&nbsp;<a href="#seconds" onclick="setvalue(\'seconds\', 2592000);">30d</a>&nbsp;<a href="#seconds" onclick="setvalue(\'seconds\', 31536000);">1yr</a>&nbsp;<a href="#seconds" onclick="setvalue(\'seconds\', 0);">never</a></div><br>
		<a name="reason"></a><label for="reason">'._gettext('Reason').':</label>
		<input type="text" name="reason" id="reason">
		<div class="desc">'._gettext('Presets').':
<a href="#reason" onclick="reason(\'Wipe (#1)\');">Wipe</a>
<a href="#reason" onclick="reason(\'Child Pornography (#2)\');">CP</a>
<a href="#reason" onclick="reason(\'Shock content (#3)\');">Shock</a>
<a href="#reason" onclick="reason(\'Call to raids (#4)\');">Raid</a>
<a href="#reason" onclick="reason(\'Trolling (#5)\');">Troll</a>
<a href="#reason" onclick="reason(\'Interchan flame (#6)\');">Chanflame</a>
<a href="#reason" onclick="reason(\'Spam (#7)\');">Spam</a>
<a href="#reason" onclick="reason(\'Proxy\');">Proxy</a></div><br>';
		$tpl_page .= '<label for="reason">'._gettext('Moderator Note').':</label>
		<input type="text" name="note" id="modnote"><div class="desc">Note to moderators on why the user was banned</div><br/>';
		if (KU_APPEAL != '') {
			$tpl_page .= '<label for="appealdays">Appeal (days):</label>
			<input type="text" name="appealdays" value="5" id="appealdays">
			<div class="desc">'._gettext('Presets').':&nbsp;<a href="#" onclick="setvalue(\'appealdays\', 0);">No Appeal</a>&nbsp;<a href="#" onclick="setvalue(\'appealdays\', 5);">5 days</a>&nbsp;<a href="#" onclick="setvalue(\'appealdays\', 10);">10 days</a>&nbsp;<a href="#" onclick="setvalue(\'appealdays\', 30);">30 days</a></div><br>';
		}
		
		$tpl_page .= '</fieldset>
		<input type="submit" value="'._gettext('Add ban').'">
		
		</form>
		<hr><br>';
		
		if (isset($_GET['allbans'])) {
			$results1 = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '1' ORDER BY `id` DESC");
			$results0 = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '0' ORDER BY `id` DESC");
		} else if (isset($_GET['getbans'])) {
		$getbans = mysqli_real_escape_string($tc_db->link, $_GET['getbans']);
                $results1 = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '1' ORDER BY `id` DESC LIMIT $getbans");
                $results0 = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '0' ORDER BY `id` DESC LIMIT $getbans");
		} else {
		$results1 = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '1' ORDER BY `id` DESC LIMIT 5");
		$results0 = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '0' ORDER BY `id` DESC LIMIT 5");
		}
		//type 1
		$tpl_page .= '<b>IP Range bans:</b><br>';
		
		$tpl_page .= '<table border="1" width="100%"><tr><th>IP Range</th><th>Boards</th><th>Reason</th><th>Moderator Note</th><th>Date Added</th><th>Expires</th><th>Added By</th><th>&nbsp;</th></tr>';
		
		foreach ($results1 as $line) {
			$count += 1;
			if ($count%2 == false) {
                                $linecolour = "#9988EE";
                        } else {
                                $linecolour = "#D6DAF0";
			}

			$tpl_page .= "<tr>";
			$tpl_page .= '<td><a href="?action=bans&ip=' . md5_decrypt($line['ip'], KU_RANDOMSEED) . '">' . md5_decrypt($line['ip'], KU_RANDOMSEED) . '</a></td><td>';
			if ($line['globalban'] == '1') {
				$tpl_page .= '<b>All boards</b>';
			} else {
				if ($line['boards'] != '') {
					$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>&nbsp;';
				}
			}
			$tpl_page .= '</td><td>';
			if ($line['reason'] != '') {
				$tpl_page .= htmlentities(stripslashes($line['reason']));
			} else {
				$tpl_page .= '&nbsp;';
			}
			$tpl_page .= '</td><td>';
			if ($line['note'] != '') {
				$tpl_page .= htmlentities(stripslashes($line['note']));
			} else {
				$tpl_page .= '&nbsp;';
			}
			$tpl_page .= '</td><td>' . date("F j, Y, g:i a", $line['at']) . '</td><td>';
			if ($line['until'] == '0') {
				$tpl_page .= '<b>Does not expire</b>';
			} else {
				$tpl_page .= date("F j, Y, g:i a", $line['until']);
			}
			$tpl_page .= '</td><td>' . $line['by'] . '</td><td>[<a href="manage_page.php?action=bans&delban=' . $line['id'] . '">x</a>]</td></tr>';
		}
		$tpl_page .= '</table>';
		
		//type 0
		$tpl_page .= '<br><b>Single IP bans:</b><br>';
		$tpl_page .= '<table border="1" width="100%"><tr><th>IP Address</th><th>Boards</th><th>Reason</th><th>Moderator Note</th><th>Date Added</th><th>Expires</th><th>Added By</th><th>&nbsp;</th></tr>';
		$count = 0;
		foreach ($results0 as $line) {
			$count += 1;
                        if ($count%2 == false) {
                                $linecolour = "#9988EE";
                        } else {
                                $linecolour = "#D6DAF0";
                        }

			$tpl_page .= "<tr>";
			$tpl_page .= '<td><a href="?action=bans&ip=' . md5_decrypt($line['ip'], KU_RANDOMSEED) . '">' . md5_decrypt($line['ip'], KU_RANDOMSEED) . '</a></td><td>';

			if ($line['globalban'] == '1') {
				$tpl_page .= '<b>All boards</b>';
			} else {
				if ($line['boards'] != '') {
					$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>&nbsp;';
				}
			}
			$tpl_page .= '</td><td>';
			if ($line['reason'] != '') {
				$tpl_page .= htmlentities(stripslashes($line['reason']));
			} else {
				$tpl_page .= '&nbsp;';
			}
			$tpl_page .= '</td><td>';
			if ($line['note'] != '') {
				$tpl_page .= htmlentities(stripslashes($line['note']));
			} else {
				$tpl_page .= '&nbsp;';
			}
			$tpl_page .= '</td><td>' . date("F j, Y, g:i a", $line['at']) . '</td><td>';
			if ($line['until'] == '0') {
				$tpl_page .= '<b>Does not expire</b>';
			} else {
				$tpl_page .= date("F j, Y, g:i a", $line['until']);
			}
			$tpl_page .= '</td><td>' . $line['by'] . '</td><td>[<a href="manage_page.php?action=bans&delban=' . $line['id'] . '">x</a>]</td></tr>';
		}
		$tpl_page .= '</table>';

		if (isset($_GET['hashbans'])) {
		$tpl_page .= '<br><br><b>File hash bans:</b><br><table border="1" width="100%"><tr><th>Hash</th><th>Description</th><th>Ban time</th><th>&nbsp;</th></tr>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `".KU_DBPREFIX."bannedhashes`");
		if (count($results) == 0) {
			$tpl_page .= '<tr><td colspan="4">None</td></tr>';
		} else {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['md5'] . '</td><td>' . $line['description'] . '</td><td>';
				if ($line['bantime'] == 0) {
					$tpl_page .= '<b>' . _gettext('Does not expire') . '</b>';
				} else {
					$tpl_page .= $line['bantime'] . ' seconds';
				}
				$tpl_page .= '</td><td>[<a href="?action=bans&delhashid=' . $line['id'] . '">x</a>]</td></tr>';
			}
		}
		$tpl_page .= '</table>';
		} else {
			$tpl_page .= 'Last <a href="?action=bans&getbans=10">10</a>, <a href="?action=bans&getbans=20">20</a>, <a href="?action=bans&getbans=30">30</a> Bans | <a href="?action=bans&allbans=1">All Bans</a> | <a href="?action=bans&hashbans=1">Hash Bans</a>';
		}
	}

	function warnings() {
		//Method GET is used when accessing manage page from thread/board page and when clicking on Last 10/20/30/All/Viewed/Not Viewed links under the table.
		//Method POST for all other cases: Get IP/Issue warning/Delete all Viewed/Delete buttons.

		global $tc_db, $tpl_page;

		$pageSmarty = getSmarty();
		$message = '';

		$this->ModeratorsOnly();
		if($this->CheckAccess() < 6) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$warning_ip = isset($_POST['ip']) && $_POST['ip'] ? $_POST['ip'] : null;
		$warning_board = isset($_GET['warningboard']) && $_GET['warningboard'] ? $_GET['warningboard'] : null;
		if (!$warning_board) {
			$warning_board = isset($_POST['warningboard']) && $_POST['warningboard'] ? $_POST['warningboard'] : null;
		}
		$warning_post = isset($_GET['warningpost']) && $_GET['warningpost'] ? $_GET['warningpost'] : null;
		if (!$warning_post) {
			$warning_post = isset($_POST['warningpost']) && $_POST['warningpost'] ? $_POST['warningpost'] : null;
		}
		$text = isset($_POST['text']) && $_POST['text'] ? $_POST['text'] : null;
		$note = isset($_POST['note']) && $_POST['note'] ? $_POST['note'] : '';
		$is_global = isset($_POST['global']) ? 1 : 0;

		$boards = [];
		if (isset($_POST)) {
			foreach ($_POST as $key => $value) {
				$matches = [];
				if (preg_match("/^board(.+)$/", $key, $matches)) {
					$boards[] = $matches[1];
				}
			}
		}

		if (isset($_POST['delete_all_viewed'])) {
			if($this->CheckAccess() < 7) {
				exitWithErrorPage(_gettext('You do not have permission to delete warnings on all boards'));
			}

			$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "warnings` WHERE `viewed` = 1");
			management_addlogentry(_gettext('Removed all viewed warnings'), 12);

			$message = _gettext('Warnings successfully removed');
		} elseif (isset($_POST['delwarning'])) {
			$warning_to_delete = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "warnings` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_POST['delwarning']) . "'");

			if (count($warning_to_delete) > 0) {
				if (!$warning_to_delete[0]['global']) {
					$warning_to_delete_boards = $warning_to_delete[0]['boards'] ? explode('|', $warning_to_delete[0]['boards']) : [];
				} else {
					$all_boards = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
					$warning_to_delete_boards = array_map(function ($line) { return $line['name']; }, $all_boards);
				}

				if ($this->CheckAccess() < 7) {
					$allowed_boards = $this->BoardList($_SESSION['manageusername']);
					$not_allowed_boards = array_diff($warning_to_delete_boards, $allowed_boards);

					if (count($not_allowed_boards)) {
						exitWithErrorPage(_gettext("You do not have permission to delete a warning on these boards") . ": " . implode(', ', $not_allowed_boards));
					}
				}

				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "warnings` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_POST['delwarning']) . "'");
				$message = _gettext('Warning successfully removed.');
				$warning_to_delete_ip = md5_decrypt($warning_to_delete[0]['ip'], KU_RANDOMSEED);
				$removedwarningfor = $warning_to_delete[0]['viewed'] ? _gettext('Removed viewed warning for') : _gettext('Removed warning for');
				management_addlogentry($removedwarningfor . ' ' . $warning_to_delete_ip . " " . _gettext('on boards') . ": " . ' /' . implode('/, /', $warning_to_delete_boards) . '/ ', 12);
			} else {
				$message = _gettext('Invalid warning ID');
			}
		} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['getip'])) {
			if($is_global && $this->CheckAccess() < 7) {
				exitWithErrorPage(_gettext('You do not have permission to issue a global warning'));
			}

			$allowed_boards = $this->BoardList($_SESSION['manageusername']);
			$not_allowed_boards = array_diff($boards, $allowed_boards);

			if (count($not_allowed_boards)) {
				exitWithErrorPage(_gettext("You do not have permission to issue a warning on these boards") . ": " . implode(', ', $not_allowed_boards));
			}

			$already_has_global_warning = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "warnings` WHERE `ipmd5` = '" . md5($_POST['ip']) . "' AND `global` = 1 AND viewed = 0");

			$existing_warnings = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "warnings` WHERE `ipmd5` = '" . md5($_POST['ip']) . "' AND viewed = 0");

			$existing_boards = array_map(function ($line) {
				return explode("|", $line['boards']);
			}, $existing_warnings);
			$existing_boards = call_user_func_array('array_merge', $existing_boards);

			if ($is_global) {
				$conflicted_boards = $existing_boards;
			} else {
				$conflicted_boards = array_intersect($boards, $existing_boards);
			}

			$ip = preg_replace("/[^0-9.]/", "", $_POST['ip']);

			if (!$ip) {
				$message = _gettext('Please enter IP address');
			} elseif (!$text) {
				$message = _gettext('Please enter warning text');
			} elseif (!count($boards) && !$is_global) {
				$message = _gettext('Please select a board');
			} elseif ($already_has_global_warning) {
				$message = _gettext('There is already global warning for this IP');
			} elseif (count($conflicted_boards)) {
				$message = _gettext('There is already warning for this IP on these boards') . ': ' . implode(', ', $conflicted_boards);
			} else {
				$parse_class = new Parse();

				$striptext = stripslashes($text);

				$board = $warning_board ?? $boards[0] ?? $allowed_boards[0];

				$formattedText = $parse_class->ParsePost($striptext, $board, 0, null);

				$tc_db->Execute("INSERT INTO `".KU_DBPREFIX."warnings` ( `ip` , `ipmd5` , `by` , `at` , `text`, `note`, `boards`, `global`) 
					VALUES ( '".md5_encrypt($ip, KU_RANDOMSEED)."' , '"
					.md5($ip)."' , '"
					.mysqli_real_escape_string($tc_db->link, $_SESSION['manageusername'])."' , '"
					.time()."' , '"
					.$formattedText."', '"
					.mysqli_real_escape_string($tc_db->link, $note)."', '"
					.implode("|", $boards)."', '"
					.$is_global."' )");

				$logentry = _gettext('Created warning for') . ' ' . $_POST['ip'];
				$logentry .= ' - ' . _gettext('Text') . ': ' . $_POST['text'] . ' - ';
				if ($is_global) {
					$logentry .= _gettext('on all boards') . ' ';
				} else {
					$logentry .=  _gettext("on boards") .  ': /' . implode('/, /', $boards) . '/ ';
				}
				management_addlogentry($logentry, 12);

				//after creating warning form field values are no longer needed
				$warning_board = null;
				$warning_post = null;
				$text = null;
				$note = null;
				$is_global = null;
				$warning_ip = null;
				$boards = [];

				$message = _gettext('Warning successfully issued.');
			}
		}

		if (isset($_GET['warningquickuser']) || isset($_POST['getip'])) {
			if (!$warning_board) {
				$message = _gettext('Please select a board');
			} elseif (!$warning_post) {
				$message = _gettext('Please enter post ID');
			} else {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $warning_board) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$board_name = $line['name'];
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $board_name . "` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $warning_post) . "'");
					if (count($results) > 0) {
						foreach ($results as $line) {
							$warning_ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
						}
					} else {
						$message = _gettext('A post with that ID does not exist.');
					}
				}
			}
		}
		flush();

		$pageSmarty->assign('boardListDropdown', $this->MakeBoardListDropdown('warningboard', $this->BoardList($_SESSION['manageusername']), $warning_board ?? null));
		$pageSmarty->assign('warningPost', htmlentities($warning_post));
		$pageSmarty->assign('warningIp', htmlentities($warning_ip));
		$pageSmarty->assign('globalChecked', $is_global ? 'checked' : '');
		$pageSmarty->assign('boardListCheckboxes', $this->MakeBoardListCheckboxes('board', $this->BoardList($_SESSION['manageusername']), $boards));
		$pageSmarty->assign('text', htmlentities($text));
		$pageSmarty->assign('note', htmlentities($note));
		$pageSmarty->assign('showDeleteAllViewed', $this->CheckAccess() >= 7);

		$viewedCondition = '';

		if (ISSET($_GET['viewed'])) {
			$viewedCondition = $_GET['viewed'] ? "WHERE `viewed` = 1" : "WHERE `viewed` = 0";
		}

		$limit = ISSET($_GET['getwarnings']) ? "LIMIT " . $_GET['getwarnings'] : '';

		$warnings = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "warnings` $viewedCondition ORDER BY `id` DESC $limit");

		$warningsViewModel = array_map(function($warning) {
			return [
				'ip' => md5_decrypt($warning['ip'], KU_RANDOMSEED),
				'global' => $warning['global'],
				'boards' => explode('|', $warning['boards']),
				'text' => $warning['text'],
				'note' => htmlentities($warning['note']),
				'at' => date("F j, Y, g:i a", $warning['at']),
				'viewed' => $warning['viewed'] == 1 ? _gettext('Yes') : _gettext('No'),
				'by' => $warning['by'],
				'id' => $warning['id']
			];
		}, $warnings);

		$pageSmarty->assign('warnings', $warningsViewModel);

		$pageSmarty->assign('message', $message);

		$tpl_page .= $pageSmarty->fetch('manage/warnings.tpl');
	}

	/* Delete a post, or multiple posts */
	function delposts($multidel=false) {
		global $tc_db, $smarty, $tpl_page, $board_class;
		if($this->CheckAccess() < 3) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . ucwords(_gettext('Delete thread/post')) . '</h2><br>';
		$isquickdel = false;
		if (isset($_POST['boarddir']) || isset($_GET['boarddir'])) {
			if (isset($_GET['boarddir'])) {
				$isquickdel = true;
				$_POST['boarddir'] = $_GET['boarddir'];
				if (isset($_GET['delthreadid'])) {
					$_POST['delthreadid'] = $_GET['delthreadid'];
				}
				if (isset($_GET['delpostid'])) {
					$_POST['delpostid'] = $_GET['delpostid'];
				}
			}
			$missing_directories = [];
			if (isset($_POST['archive'])) {
				// These checks doesn't guarantee that archiving will go well. Directories still can have invalid
				// permissions, that will prevent files from being copied. And because of '@' usage this will happen
				// silently, without noticing somebody. Also thread archiving may happen not only due to admin actions
				// but also during any posting from Board::TrimToPageLimit.
				$directories_to_check = ['/arch', '/arch/res', '/arch/src', '/arch/thumb'];
				$missing_directories = array_values(array_filter($directories_to_check, function($directory) {
					return !file_exists( KU_BOARDSDIR . $_POST['boarddir'] . $directory);
				}));
				$missing_directories = array_map(function($directory) {
					return '/' . $_POST['boarddir'] . $directory;
				}, $missing_directories);
			}
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $_POST['boarddir']) . "'");
			if (count($missing_directories) == 0 && count($results) > 0) {
				if (!$this->CurrentUserIsModeratorOfBoard($_POST['boarddir'], $_SESSION['manageusername'])) {
					exitWithErrorPage(_gettext('You are not a moderator of this board.'));
				}
				foreach ($results as $line) {
					$board_id = $line['id'];
					$board_dir = $line['name'];
				}
				if (isset($_POST['delthreadid'])) {
					if (mb_strlen($_POST['delthreadid']) > 0) {
						$threadids = preg_split('/\s*,\s*/', $_POST['delthreadid']);
					}
					foreach($threadids as $threadid) {
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $board_dir . "` WHERE `IS_DELETED` = '0' AND  `id` = '" . mysqli_real_escape_string($tc_db->link, $threadid) . "' AND `parentid` = '0'");
						if (count($results) > 0) {
							foreach ($results as $line) {
								$delthread_id = $line['id'];
							}
							$post_class = new Post($delthread_id, $board_dir);
							if (isset($_POST['archive'])) {
								$tpl_page .= "Archived thread $delthread_id in $board_dir.<br>\n";
								management_addlogentry(_gettext('Archived thread') . ' #<a href="?action=viewdeletedthread&board='.$_POST['boarddir'].'&thread=' . $delthread_id . '">' . $delthread_id  . '</a> - ' . '/' . $_POST['boarddir'] . '/', 7);
								$numposts_deleted = $post_class->Delete(true);
							} else {
								$numposts_deleted = $post_class->Delete();
								$tpl_page .= "Deleted thread $delthread_id in $board_dir.<br>\n";
								management_addlogentry(_gettext('Deleted thread') . ' #<a href="?action=viewdeletedthread&board='.$_POST['boarddir'].'&thread=' . $delthread_id . '">' . $delthread_id  . '</a> - ' . '/' . $_POST['boarddir'] . '/', 7);
							}
						} else {
							$tpl_page .= _gettext('Invalid thread ID '.$threadid.'.  This may have been caused by the thread recently being deleted.');
						}
					}
					$board_class = new Board($board_dir);
//					$board_class->RegenerateAll();
					$board_class->RegeneratePages();
					if (isset($_GET['postid']) && $_GET['postid'] != '') {
						$tpl_page .= '<br><br><meta http-equiv="refresh" content="1;url=' . KU_CGIPATH .  '/manage_page.php?action=bans&banboard=' . $_GET['boarddir'] . '&banpost=' . $_GET['postid'] . '"><a href="' . KU_CGIPATH . '/manage_page.php?action=bans&banboard=' . $_GET['boarddir'] . '&banpost=' . $_GET['postid'] . '">' . _gettext('Redirecting') . '</a> to ban page...';
					} elseif ($isquickdel) {
						$tpl_page .= '<br><br><meta http-equiv="refresh" content="1;url=' . KU_BOARDSPATH . '/' . $_GET['boarddir'] . '/"><a href="' . KU_BOARDSPATH . '/' . $_GET['boarddir'] . '/">' . _gettext('Redirecting') . '</a> back to board...';
					}
				} elseif (isset($_POST['delpostid'])) {
					if ($_POST['delpostid'] > 0) {
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $board_dir . "` WHERE `IS_DELETED` = '0' AND  `id` = '" . mysqli_real_escape_string($tc_db->link, $_POST['delpostid']) . "'");
						if (count($results) > 0) {
//							echo "<pre>"; var_dump($_POST); echo "</pre>";
							foreach ($results as $line) {
								$delpost_id = $line['id'];
								$delpost_parentid = $line['parentid'];
							}
							$post_class = new Post($delpost_id, $board_dir);
							$post_class->Delete();
							$board_class = new Board($board_dir);
							if($delpost_parentid > 0) $board_class->RegenerateThread($delpost_parentid);
							$board_class->RegeneratePages();
							$tpl_page .= _gettext('Post '.$delpost_id.' successfully deleted.');
							management_addlogentry(_gettext('Deleted post') . ' #<a href="?action=viewdeletedthread&board=' . $_POST['boarddir'] . '&thread=' . $delpost_parentid . '#' . $delpost_id . '">' . $delpost_id . '</a> - /' . $board_dir . '/', 7);
							if (isset($_GET['postid']) && $_GET['postid'] != '') {
								$tpl_page .= '<br><br><meta http-equiv="refresh" content="1;url=' . KU_CGIPATH . '/manage_page.php?action=bans&banboard=' . $_GET['boarddir'] . '&banpost=' . $_GET['postid'] . '"><a href="' . KU_CGIPATH . '/manage_page.php?action=bans&banboard=' . $_GET['boarddir'] . '&banpost=' . $_GET['postid'] . '">' . _gettext('Redirecting') . '</a> to ban page...';
							} elseif ($isquickdel) {
								$tpl_page .= '<br><br><meta http-equiv="refresh" content="1;url=' . KU_BOARDSPATH . '/' . $_GET['boarddir'] . '/res/' . $delpost_parentid . '.html"><a href="' . KU_BOARDSPATH . '/' . $_GET['boarddir'] . '/res/' . $delpost_parentid . '.html">' . _gettext('Redirecting') . '</a> back to thread...';
							}
						} else {
							$tpl_page .= _gettext('Invalid thread ID '.$delpost_id.'.  This may have been caused by the thread recently being deleted.');
						}
					}
				}
			} elseif (count($missing_directories) > 0) {
				$tpl_page .= 'Before archiving please add directories: ' . implode(', ', $missing_directories) . '.';
			} else {
				$tpl_page .= _gettext('Invalid board directory.');
			}
			$tpl_page .= '<hr>';
		}
		if (!$multidel) {
			$tpl_page .= '<form action="manage_page.php?action=delposts" method="post">
			<label for="boarddir">'._gettext('Board').':</label>' .
			$this->MakeBoardListDropdown('boarddir', $this->BoardList($_SESSION['manageusername'])) .
			'<br>
			
			<label for="delthreadid">'._gettext('Thread').':</label>
			<input type="text" name="delthreadid"><br>
			<label for="archive">Archive:</label>
			<input type="checkbox" name="archive"><br>
			
			<input type="submit" value="'._gettext('Delete thread').'">
			
			</form>
			<br><hr>
			
			<form action="manage_page.php?action=delposts" method="post">
			<label for="boarddir">'._gettext('Board').':</label>' .
			$this->MakeBoardListDropdown('boarddir', $this->BoardList($_SESSION['manageusername'])) .
			'<br>
			
			<label for="delpostid">'._gettext('Post').':</label>
			<input type="text" name="delpostid"><br>
			
			
			<input type="submit" value="'._gettext('Delete post').'">
		
			</form>';
		}
	}
	
	function proxyban() {
		global $tpl_page;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Ban proxy list')) . '</h2><br>';
		if (isset($_FILES['imagefile'])) {
			$bans_class = new Bans;
			$ips = 0;
			$successful = 0;
			$proxies = file($_FILES['imagefile']['tmp_name']);
			foreach($proxies as $proxy) {
				if (preg_match('/.[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+.*/', $proxy)) {
					//$proxy = trim($proxy, " \t\n\r\0\x0B");
					$proxy = trim($proxy);
					$ips++;
					if ($bans_class->BanUser(preg_replace('/:.*/', '', $proxy), 'SERVER', 1, 0, '', 'IP from proxylist automatically banned', 0, 0, 0, 'Proxy')) {
						$successful++;
					}
				}
			}
			management_addlogentry(sprintf(_gettext('Banned %d IP addresses using an IP address list.'), $successful), 8);
			$tpl_page .= $successful . ' of ' . $ips . ' IP addresses banned.';
		} else {
			$tpl_page .= '<form id="postform" action="' . KU_CGIPATH . '/manage_page.php?action=proxyban" method="post" enctype="multipart/form-data">'._gettext('Proxy list').'<input type="file" name="imagefile" size="35" accesskey="f"><br>
			<input type="submit" value="Submit">
			<br>The proxy list is assumed to be in plaintext *.*.*.*:port or *.*.*.* format, one IP per line.<br><br>';
		}
	}
	
	/* Called from a board's page using the multidel button */
	function multidel() {
		global $tc_db, $smarty, $tpl_page, $bans_class;
		
		$multidel = TRUE;
		$_POST['seconds'] = 0;
		$multiban_query = 'WHERE `id` = "0 " ';
		foreach($_POST AS $TOAST) {
			if (preg_match("/POST*/",$TOAST)){
				$_POST['boarddir'] = $_POST['board'];
				$_POST['delpostid'] = preg_replace('/POST/','',$TOAST);
				$this->delposts($multidel);
				if (($_POST['multiban'])) { $multiban_query .= "OR `id` = '".mysqli_real_escape_string($tc_db->link, $_POST['delpostid'])."'"; }
			}
		}
		
		if (isset($_POST['multiban'])) {
			$this->ModeratorsOnly();
			$ban_globalban = '1';
			$_POST['seconds'] = '0';
			$ban_boards = '';
			
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `ip` FROM `".KU_DBPREFIX . "posts_".mysqli_real_escape_string($tc_db->link, $_POST['board'])."` ".$multiban_query);
			if (count($results) > 0) {
				foreach ($results as $line) {
					$ban_ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
					$bans_class->BanUser($ban_ip, mysqli_real_escape_string($tc_db->link, $_SESSION['manageusername']), $ban_globalban, 0, $ban_boards, mysqli_real_escape_string($tc_db->link, $_POST['reason']), 0, 0, 1);
					$logentry = _gettext('Banned') . ' ' . $ban_ip . ' until ';
					if ($_POST['seconds'] == '0') {
						$logentry .= '<b>' . _gettext('Does not expire') . '</b>';
					} else {
						$logentry .= date('F j, Y, g:i a', time() + $_POST['seconds']);
					}
					$logentry .= ' - ' . _gettext('Reason') . ': ' . $_POST['reason'] . ' - ' . _gettext('Banned from') . ': ';
					if ($ban_globalban == '1') {
						$logentry .= _gettext('All boards') . ' ';
					} else {
						$logentry .= '/' . implode('/, /', explode('|', $ban_boards)) . '/ ';
					}
					management_addlogentry($logentry, 8);
				}
			} else {
				$tpl_page .= _gettext('A post with that ID does not exist.') . '<hr>';
			}
		}
	}
	
	/* Replace words in posts with something else */
	function wordfilter() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . _gettext('Wordfilter') . '</h2><br>';
		if (isset($_POST['word'])) {
			if ($_POST['word'] != '' && $_POST['replacedby'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter` WHERE `word` = '" . mysqli_real_escape_string($tc_db->link, $_POST['word']) . "'");
				if (count($results) == 0) {
					$wordfilter_boards = array();
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
					if (isset($_POST['filterfromall'])) {
						foreach ($results as $line) {
							$wordfilter_new_boards[] = $line['name'];
						}
					}
					else {
						foreach ($results as $line) {
							$wordfilter_boards = array_merge($wordfilter_boards, array($line['name']));
						}
						$wordfilter_changed_boards = array();
						$wordfilter_new_boards = array();
						while (list($postkey, $postvalue) = each($_POST)) {
							if (substr($postkey, 0, 10) == 'wordfilter') {
								$wordfilter_changed_boards = array_merge($wordfilter_changed_boards, array(substr($postkey, 10)));
							}
						}
						while (list(, $wordfilter_thisboard_name) = each($wordfilter_boards)) {
							if (in_array($wordfilter_thisboard_name, $wordfilter_changed_boards)) {
								$wordfilter_new_boards = array_merge($wordfilter_new_boards, array($wordfilter_thisboard_name));
							}
						}
					}
					$is_regex = (isset($_POST['regex'])) ? '1' : '0';
					
					$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "wordfilter` ( `word` , `replacedby` , `boards` , `time` , `regex` ) VALUES ( '" . mysqli_real_escape_string($tc_db->link, $_POST['word']) . "' , '" . mysqli_real_escape_string($tc_db->link, $_POST['replacedby']) . "' , '" . mysqli_real_escape_string($tc_db->link, implode('|', $wordfilter_new_boards)) . "' , '" . time() . "' , '" . $is_regex . "' )");
					
					$tpl_page .= _gettext('Word successfully added.');
					management_addlogentry("Added word to wordfilter: " . $_POST['word'] . " - Changes to: " . $_POST['replacedby'] . " - Boards: /" . implode('/, /', explode('|', implode('|', $wordfilter_new_boards))) . "/", 11);
				} else {
					$tpl_page .= _gettext('That word already exists.');
				}
			} else {
				$tpl_page .= _gettext('Please fill in all required fields.');
			}
			$tpl_page .= '<hr>';
		} elseif (isset($_GET['delword'])) {
			if ($_GET['delword'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['delword']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$del_word = $line['word'];
					}
					$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['delword']) . "'");
					$tpl_page .= _gettext('Word successfully removed.');
					management_addlogentry(_gettext('Removed word from wordfilter') . ': ' . $del_word, 11);
				} else {
					$tpl_page .= _gettext('That ID does not exist.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['editword'])) {
			if ($_GET['editword'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['editword']) . "'");
				if (count($results) > 0) {
					if (!isset($_POST['replacedby'])) {
						foreach ($results as $line) {
							$tpl_page .= '<form action="manage_page.php?action=wordfilter&editword='.$_GET['editword'].'" method="post">
							
							<label for="word">'._gettext('Word').':</label>
							<input type="text" name="word" value="'.$line['word'].'" disabled><br>
							
							<label for="replacedby">'._gettext('Is replaced by').':</label>
							<input type="text" name="replacedby" value="'.$line['replacedby'].'"><br>
							
							<label for="regex">'._gettext('Regular expression').':</label>
							<input type="checkbox" name="regex"';
							if ($line['regex'] == '1') {
								$tpl_page .= ' checked';
							}
							$tpl_page .= '><br>
				
							<label>'._gettext('Boards').':</label><br>';
							
							$array_boards = array();
							$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
							foreach ($resultsboard as $lineboard) {
								$array_boards = array_merge($array_boards, array($lineboard['name']));
							}
							foreach ($array_boards as $this_board_name) {
								$tpl_page .= '<label for="wordfilter' . $this_board_name . '">' . $this_board_name . '</label><input type="checkbox" name="wordfilter' . $this_board_name . '" ';
								if (in_array($this_board_name, explode("|", $line['boards'])) && explode("|", $line['boards']) != '') {
									$tpl_page .= 'checked ';
								}
								$tpl_page .= '><br>';
							}
							$tpl_page .= '<br>
							
							<input type="submit" value="'._gettext('Edit word').'">
							
							</form>';
						}
					} else {
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['editword']) . "'");
						if (count($results) > 0) {
							foreach ($results as $line) {
								$wordfilter_word = $line['word'];
							}
							$wordfilter_boards = array();
							$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
							foreach ($results as $line) {
								$wordfilter_boards = array_merge($wordfilter_boards, array($line['name']));
							}
							$wordfilter_changed_boards = array();
							$wordfilter_new_boards = array();
							while (list($postkey, $postvalue) = each($_POST)) {
								if (substr($postkey, 0, 10) == "wordfilter") {
									$wordfilter_changed_boards = array_merge($wordfilter_changed_boards, array(substr($postkey, 10)));
								}
							}
							while (list(, $wordfilter_thisboard_name) = each($wordfilter_boards)) {
								if (in_array($wordfilter_thisboard_name, $wordfilter_changed_boards)) {
									$wordfilter_new_boards = array_merge($wordfilter_new_boards, array($wordfilter_thisboard_name));
								}
							}
							$is_regex = (isset($_POST['regex'])) ? '1' : '0';
							
							if($tc_db->Execute("UPDATE `" . KU_DBPREFIX . "wordfilter` SET `replacedby` = '" . mysqli_real_escape_string($tc_db->link, $_POST['replacedby']) . "' , `boards` = '" . mysqli_real_escape_string($tc_db->link, implode('|', $wordfilter_new_boards)) . "' , `regex` = '" . $is_regex . "' WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['editword']) . "'")) {							
								$tpl_page .= _gettext('Word successfully updated.');
							}
							else {
								$tpl_page .= _gettext('Word failed to update') . mysqli_error($tc_db->link);
							}
							management_addlogentry(_gettext('Updated word on wordfilter') . ': ' . $wordfilter_word, 11);
						} else {
							$tpl_page .= _gettext('Unable to locate that word.');
						}
					}
				} else {
					$tpl_page .= _gettext('That ID does not exist.');
				}
				$tpl_page .= '<hr>';
			}
		} else {
			$tpl_page .= '<form action="manage_page.php?action=wordfilter" method="post">
			
			<label for="word">'._gettext('Word').'.:</label>
			<input type="text" name="word"><br>
		
			<label for="replacedby">'._gettext('Is replaced by').':</label>
			<input type="text" name="replacedby"><br>
			
			<label for="regex">'._gettext('Regular expression').':</label>
			<input type="checkbox" name="regex"><br>';
	
			$array_boards = array();
			$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
			$tpl_page .= '		<label for="filterfromall"><b>'._gettext('All boards').'</b></label>
			<input type="checkbox" name="filterfromall"><br>OR<br>';
			foreach ($resultsboard as $lineboard) {
				$array_boards = array_merge($array_boards, array($lineboard['name']));
			}
			$tpl_page .= $this->MakeBoardListCheckboxes('wordfilter', $array_boards) .
			'<br>
			
			<input type="submit" value="'._gettext('Add word').'">
			
			</form>
			<hr>';
		}
		$tpl_page .= '<br>';
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter`");
		if ($results > 0) {
			$tpl_page .= '<table border="1" width="100%"><tr><th>' . _gettext('Word') . '</th><th>' . _gettext('Replacement') . '</th><th>' . _gettext('Boards') . '</th><th>&nbsp;</th></tr>' . "\n";
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['word'] . '</td><td>' . $line['replacedby'] . '</td><td>';
				if (explode('|', $line['boards']) != '') {
					$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>&nbsp;';
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="manage_page.php?action=wordfilter&editword=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="manage_page.php?action=wordfilter&delword=' . $line['id'] . '">del</a>]</td></tr>' . "\n";
			}
			$tpl_page .= '</table>';
		}
	}
	
	function addboard() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Add board')) . '</h2><br>';
		if (isset($_POST['directory'])) {
			$_POST['directory'] = cleanBoardName($_POST['directory']);
			if ($_POST['directory'] != '' && $_POST['desc'] != '') {
				if (strtolower($_POST['directory']) != 'allboards') {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $_POST['directory']) . "'");
					if (count($results) == 0) {
						if (mkdir(KU_BOARDSDIR . $_POST['directory'], 0777) && mkdir(KU_BOARDSDIR . $_POST['directory'] . '/res', 0777) && mkdir(KU_BOARDSDIR . $_POST['directory'] . '/src', 0777) && mkdir(KU_BOARDSDIR . $_POST['directory'] . '/thumb', 0777)) {
							file_put_contents(KU_BOARDSDIR . $_POST['directory'] . '/.htaccess', 'DirectoryIndex board.html');
							$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "boards` ( `name` , `desc` , `createdon` , `includeheader` ) VALUES ( '" . mysqli_real_escape_string($tc_db->link, $_POST['directory']) . "' , '" . mysqli_real_escape_string($tc_db->link, $_POST['desc']) . "' , '" . time() . "' , '' )");
							$boardid = $tc_db->Insert_Id();
							if ($_POST['firstpostid'] < 1) {
								$_POST['firstpostid'] = 1;
							}
							$tc_db->Execute("CREATE TABLE `" . KU_DBPREFIX . "posts_" . mysqli_real_escape_string($tc_db->link, $_POST['directory']) . "` (
							  `id` int(10) NOT NULL auto_increment,
							  `parentid` int(10) NOT NULL default '0',
							  `name` varchar(255) NOT NULL,
							  `tripcode` varchar(30) NOT NULL,
							  `email` varchar(255) NOT NULL,
							  `subject` varchar(255) NOT NULL,
							  `message` text NOT NULL,
							  `filename` varchar(50) NOT NULL,
							  `filename_original` varchar(50) NOT NULL,
							  `filetype` varchar(20) NOT NULL,
							  `filemd5` char(32) NOT NULL,
							  `image_w` smallint(5) NOT NULL default '0',
							  `image_h` smallint(5) NOT NULL default '0',
							  `spoiler` tinyint(1) NOT NULL default '0',
							  `filesize` int(10) NOT NULL default '0',
							  `filesize_formatted` varchar(255) NOT NULL,
							  `thumb_w` smallint(5) NOT NULL default '0',
							  `thumb_h` smallint(5) NOT NULL default '0',
							  `password` varchar(255) NOT NULL,
							  `postedat` int(20) NOT NULL,
							  `lastbumped` int(20) NOT NULL default '0',
							  `ip` varchar(75) NOT NULL,
							  `ipmd5` char(32) NOT NULL,
							  `tag` varchar(5) NOT NULL,
							  `stickied` tinyint(1) NOT NULL default '0',
							  `locked` tinyint(1) NOT NULL default '0',
							  `posterauthority` tinyint(1) NOT NULL default '0',
							  `reviewed` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT '0',
							  `initial_board` VARCHAR(75) NULL DEFAULT NULL,
							  `deletedat` int(20) NOT NULL default '0',
							  `IS_DELETED` tinyint(1) NOT NULL default '0',
							  UNIQUE KEY `id` (`id`),
							  KEY `parentid` (`parentid`),
							  KEY `lastbumped` (`lastbumped`),
							  KEY `filemd5` (`filemd5`),
							  KEY `stickied` (`stickied`)
							) ENGINE=InnoDB AUTO_INCREMENT=" . mysqli_real_escape_string($tc_db->link, $_POST['firstpostid']) . ";");
							$filetypes = $tc_db->GetAll("SELECT " . KU_DBPREFIX . "filetypes.id FROM " . KU_DBPREFIX . "filetypes WHERE " . KU_DBPREFIX . "filetypes.filetype = 'JPG' OR " . KU_DBPREFIX . "filetypes.filetype = 'GIF' OR " . KU_DBPREFIX . "filetypes.filetype = 'PNG';");
							foreach ($filetypes AS $filetype) {
								$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "board_filetypes` ( `boardid` , `typeid` ) VALUES ( " . $boardid . " , " . $filetype['id'] . " );");
							}
							/* Sleep for five seconds, to ensure the table was created before attempting to initialize a board class with it */
							sleep(5);
							$board_class = new Board(mysqli_real_escape_string($tc_db->link, $_POST['directory']));
							$board_class->RegenerateAll();
							$tpl_page .= _gettext('Board successfully added.') . '<br><br><a href="' . KU_BOARDSPATH . '/' . $_POST['directory'] . '/">/' . $_POST['directory'] . '/</a>!';
							$tpl_page .= 'Memory: ' . memory_get_usage();
							management_addlogentry(_gettext('Added board') . ': /' . $_POST['directory'] . '/', 3);
						} else {
							$tpl_page .= '<br>' . _gettext('Unable to create directories.');
						}
					} else {
						$tpl_page .= _gettext('A board with that name already exists.');
					}
				} else {
					$tpl_page .= _gettext('That name is for internal use.  Please pick another.');
				}
			} else {
				$tpl_page .= _gettext('Please fill in all required fields.');
			}
		}
		$tpl_page .= '<form action="manage_page.php?action=addboard" method="post">
	
		<label for="directory">' . _gettext('Directory') . ':</label>
		<input type="text" name="directory">
		<div class="desc">' . _gettext('The directory of the board.') . '  <b>' . _gettext('Only put in the letter(s) of the board directory, no slashes!') . '</b></div><br>
		
		<label for="desc">' . _gettext('Description') . ':</label>
		<input type="text" name="desc"><div class="desc">' . _gettext('The name of the board.') . '</div><br>
		
		<label for="firstpostid">' . _gettext('First Post ID') . ':</label>
		<input type="text" name="firstpostid" value="1">
		<div class="desc">' . _gettext('The first post of this board will recieve this ID.') . '</div><br>
		
		<input type="submit" value="Add Board">
		
		</form>';
	}
	
	function delboard() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Delete board')) . '</h2><br>';
		if (isset($_POST['directory'])) {
			if ($_POST['directory'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = '" . mysqli_real_escape_string($tc_db->link, $_POST['directory']) . "'");
				foreach ($results as $line) {
					$board_id = $line['id'];
					$board_dir = $line['name'];
				}
				if (count($results) > 0) {
					if (isset($_POST['confirmation'])) {
						if (removeBoard($board_dir)) {
							$tc_db->Execute("DROP TABLE `" . KU_DBPREFIX . "posts_" . $board_dir . "`");
							$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "boards` WHERE `id` = '" . $board_id . "'");
							$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $board_id . "'");
							require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
							$menu_class = new Menu();
							$menu_class->Generate();
							$tpl_page .= _gettext('Board successfully deleted.');
							management_addlogentry(_gettext('Deleted board').': /' . $_POST['directory'] . '/', 3);
						} else {
							/* Error */
							$tpl_page .= _gettext('Unable to delete board.');
						}
					} else {
						$tpl_page .= sprintf(_gettext('Are you absolutely sure you want to delete %s?'),'/' . $board_dir . '/') .
						'<br>
						<form action="manage_page.php?action=delboard" method="post">
						<input type="hidden" name="directory" value="' . $_POST['directory'] . '">
						<input type="hidden" name="confirmation" value="yes">
						
						<input type="submit" value="'._gettext('Continue').'">
						
						</form>';
					}
				} else {
					$tpl_page .= _gettext('A board with that name does not exist.');
				}
			}
			$tpl_page .= '<hr>';
		}
		$tpl_page .= '<form action="manage_page.php?action=delboard" method="post">
		
		<label for="directory">'._gettext('Directory').':</label>' .
		$this->MakeBoardListDropdown('directory', $this->BoardList($_SESSION['manageusername'])) .
		'<br>
		
		<input type="submit" value="'._gettext('Delete board').'">
		
		</form>';
	}
	
	function changepwd() {
		global $tc_db, $smarty, $tpl_page;
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Change account password')) . '</h2><br>';
		if (isset($_POST['oldpwd']) && isset($_POST['newpwd']) && isset($_POST['newpwd2'])) {
			if ($_POST['oldpwd'] != '' && $_POST['newpwd'] != '' && $_POST['newpwd2'] != '') {
				if ($_POST['newpwd'] == $_POST['newpwd2']) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . mysqli_real_escape_string($tc_db->link, $_SESSION['manageusername']) . "'");
					foreach ($results as $line) {
						$staff_passwordenc = $line['password'];
					}
					if (md5($_POST['oldpwd']) == $staff_passwordenc) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `password` = '" . md5($_POST['newpwd']) . "' WHERE `username` = '" . mysqli_real_escape_string($tc_db->link, $_SESSION['manageusername']) . "'");
						$_SESSION['managepassword'] = md5($_POST['newpwd']);
						$tpl_page .= _gettext('Password successfully changed.');
					} else {
						$tpl_page .= _gettext('The old password you provided did not match the current one.');
					}
				} else {
					$tpl_page .= _gettext('The second password did not match the first.');
				}
			} else {
				$tpl_page .= _gettext('Please fill in all required fields.');
			}
			$tpl_page .= '<hr>';
		}
		$tpl_page .= '<form action="manage_page.php?action=changepwd" method="post">
		
		<label for="oldpwd">' . _gettext('Old password') . ':</label>
		<input type="password" name="oldpwd"><br>
	
		<label for="newpwd">' . _gettext('New password') . ':</label>
		<input type="password" name="newpwd"><br>
		
		<label for="newpwd2">' . _gettext('New password again') . ':</label>
		<input type="password" name="newpwd2"><br>
		
		<input type="submit" value="' ._gettext('Change account password') . '">
		
		</form>';
	}
	
	function staff() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$tpl_page .= '<h2>' . _gettext('Staff') . '</h2><br>';
		if (isset($_POST['staffusername']) && isset($_POST['staffpassword'])) {
			if ($_POST['staffusername'] != '' && ($_POST['staffpassword'] != '' || $_POST['type'] == '3')) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . mysqli_real_escape_string($tc_db->link, $_POST['staffusername']) . "'");
				if (count($results) == 0) {
					if ($_POST['type'] == '0' || $_POST['type'] == '1' || $_POST['type'] == '2' || $_POST['type'] == '3') {
						if(!is_numeric($_POST['access'])) {
							exitWithErrorPage('Invalid access level');
						}
						$length = strlen($_POST['staffpassword']);
						if($length < 7) {
							exitWithErrorPage('Password must be 7 characters or more');
						}
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "staff` ( `username` , `password` , `type` , `addedon` , `access`) VALUES ( '" . mysqli_real_escape_string($tc_db->link, $_POST['staffusername']) . "' , '" . md5($_POST['staffpassword']) . "' , '" . $_POST['type'] . "' , '" . time() . "' , '" . $_POST['access'] . "' )");
					} else {
						exitWithErrorPage('Invalid type.');
					}
					$tpl_page .= _gettext('Staff member successfully added.');
					if ($_POST['type'] != 3) {
						$logentry = _gettext('Added staff member') . ' - ';
						if ($_POST['type'] == '1') {
							$logentry .= _gettext('Administrator');
						} elseif ($_POST['type'] == '2') {
							$logentry .= _gettext('Moderator');
						} elseif ($_POST['type'] == '0') {
							$logentry .= _gettext('Janitor');
						} else {
							$logentry .= 'VIP';
						}
						$logentry .= ": " . $_POST['staffusername'];
					} else {
						$logentry = 'Added a VIP code';
					}
					management_addlogentry($logentry, 6);
				} else {
					$tpl_page .= _gettext('A staff member with that ID already exists.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['del'])) {
			if ($_GET['del'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['del']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$staff_username = $line['username'];
						$staff_type = $line['type'];
					}
					if($line['username'] == KU_ROOT) {
						exitWithErrorPage('Permission denied');
					}
					$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['del']) . "'");
					$tpl_page .= _gettext('Staff successfully deleted');
					if ($staff_type != 3) {
						management_addlogentry(_gettext('Deleted staff member') . ': ' . $staff_username, 6);
					} else {
						management_addlogentry(_gettext('Deleted a VIP code'), 6);
					}
				} else {
					$tpl_page .= _gettext('Invalid staff ID.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['suspend'])) {
			if ($_GET['suspend'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['suspend']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$staff_username = $line['username'];
						$staff_type = $line['type'];
					}
					if($line['username'] == KU_ROOT) {
						exitWithErrorPage('Permission denied');
					}
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `suspended` = 1 WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['suspend']) . "'");
					$tpl_page .= _gettext('Staff successfully suspended');
					if ($staff_type != 3) {
						management_addlogentry(_gettext('Suspended staff member') . ': ' . $staff_username, 6);
					} else {
						management_addlogentry(_gettext('Suspended a VIP code'), 6);
					}
				} else {
					$tpl_page .= _gettext('Invalid staff ID.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['unsuspend'])) {
			if ($_GET['unsuspend'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['unsuspend']) . "'");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$staff_username = $line['username'];
						$staff_type = $line['type'];
					}
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `suspended` = 0 WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['unsuspend']) . "'");
					$tpl_page .= _gettext('Staff successfully unsuspended');
					if ($staff_type != 3) {
						management_addlogentry(_gettext('Unsuspended staff member') . ': ' . $staff_username, 6);
					} else {
						management_addlogentry(_gettext('Unsuspended a VIP code'), 6);
					}
				} else {
					$tpl_page .= _gettext('Invalid staff ID.');
				}
				$tpl_page .= '<hr>';
			}
		} elseif (isset($_GET['edit'])) {
			if ($_GET['edit'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['edit']) . "'");
				if (count($results) > 0) {
					if (isset($_POST['submitting'])) {
						if(isset($_POST['access'])) {
							if(!is_numeric($_POST['access'])) {
								exitWithErrorPage('Invalid access code');
							}
							else {
								$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `access` = '" . $_POST['access'] . "' WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['edit']) . "'");
								$n_results = $tc_db->GetAll("SELECT HIGH_PRIORITY `username` FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['edit']) . "' LIMIT 1");
								foreach($n_results as $line) {
								if($line['username'] == KU_ROOT) {
									exitWithErrorPage('Permission denied');
								}								
									management_addlogentry(_gettext('Updated access level to ') . $_POST['access'] . ' for ' . $line['username'], 6);
								}
							}
						}
						foreach ($results as $line) {
							$staff_username = $line['username'];
							$staff_type = $line['type'];
						}
						if($line['username'] == KU_ROOT) {
							exitWithErrorPage('Permission denied');
						}
						$staff_boards = array();
						if (isset($_POST['moderatesallboards'])) {
							$staff_new_boards = array('allboards');
						} else {
							$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
							foreach ($results as $line) {
								$staff_boards = array_merge($staff_boards, array($line['name']));
							}
							$staff_changed_boards = array();
							$staff_new_boards = array();
							while (list($postkey, $postvalue) = each($_POST)) {
								if (substr($postkey, 0, 8) == "moderate") {
									$staff_changed_boards = array_merge($staff_changed_boards, array(substr($postkey, 8)));
								}
							}
							while (list(, $staff_thisboard_name) = each($staff_boards)) {
								if (in_array($staff_thisboard_name, $staff_changed_boards)) {
									$staff_new_boards = array_merge($staff_new_boards, array($staff_thisboard_name));
								}
							}
						}
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `boards` = '" . mysqli_real_escape_string($tc_db->link, implode('|', $staff_new_boards)) . "' WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['edit']) . "'");
						$tpl_page .= _gettext('Staff successfully updated') . '<hr>';
						if ($_POST['type'] != '3') {
							$logentry = _gettext('Updated staff member') . ' - ';
							if ($_POST['type'] == '1') {
								$logentry .= _gettext('Administrator');
							} elseif ($_POST['type'] == '2') {
								$logentry .= _gettext('Moderator');
							} elseif ($_POST['type'] == '0') {
								$logentry .= _gettext('Janitor');
							} else {
								exitWithErrorPage('Something went wrong.');
							}
							$logentry .= ': ' . $staff_username;
							if ($_POST['type'] != '1') {
								$logentry .= ' - ' . _gettext('Moderates') . ': ';
								if (isset($_POST['moderatesallboards'])) {
									$logentry .= strtolower(_gettext('All boards'));
								} else {
									$logentry .= '/' . implode('/, /', $staff_new_boards) . '/';
								}
							}
						} else {
							$logentry = _gettext('Edited a VIP code');
						}
						management_addlogentry($logentry, 6);
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . $_GET['edit'] . "'");
					foreach ($results as $line) {
						$staff_username = $line['username'];
						$staff_type = $line['type'];
						$staff_boards = explode('|', $line['boards']);
					}
					$tpl_page .= '<form action="manage_page.php?action=staff&edit=' . $_GET['edit'] . '" method="post">
					
					<label for="staffname">' . _gettext('Username') . ':</label>
					<input type="text" name="staffname" value="' . $staff_username . '" disabled><br>
					
					<label for="type">' . _gettext('Type') . ':</label>
					<select name="type">
					<option value="1"';
					if ($staff_type == '1') {
						$tpl_page .= 'selected';
					}
					$tpl_page .= '>' . _gettext('Administrator') . '</option>
					<option value="2"';
					if ($staff_type == '2') {
						$tpl_page .= 'selected';
					}
					$tpl_page .= '>' . _gettext('Moderator') . '</option>
					<option value="0"';
					if ($staff_type == '0') {
						$tpl_page .= 'selected';
					}
					$tpl_page .= '>' . _gettext('Janitor') . '</option>
					<option value="3"';
					if ($staff_type == '3') {
						$tpl_page .= 'selected';
					}
					$tpl_page .= '>VIP</option>
					</select><br><br>';
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `access` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $staff_username . "'LIMIT 1");
						foreach ($results as $line) {
							$tpl_page .= '<label for="moderatesallboards">'._gettext('Access level [<a href="manage_page.php?action=staff&accesshelp=1">?</a>]').':</label> <input type="text" maxlength="4" name="access" value="'.$line['access'].'"/><br/>';
						}
					$tpl_page .= _gettext('Moderates') . '<br>' .
					'<label for="moderatesallboards"><b>'._gettext('All boards').'</b></label>' .
					'<input type="checkbox" name="moderatesallboards"';
					if ($staff_boards == array('allboards')) {
						$tpl_page .= ' checked';
					}
					$tpl_page .= '><br>' . _gettext('or') . '<br>';
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
					foreach ($results as $line) {
						$tpl_page .= '<label for="moderate' . $line['name'] . '">' . $line['name'] . '</label><input type="checkbox" name="moderate' . $line['name'] . '" ';
						if (in_array($line['name'], $staff_boards)) {
							$tpl_page .= 'checked ';
						}
						$tpl_page .= '><br>';
					}
					$tpl_page .= '<input type="submit" value="' . _gettext('Modify staff member') . '" name="submitting">
					
					</form>
					<br>';

				} else {
					$tpl_page .= _gettext('A staff member with that id does not appear to exist.');
				}
				$tpl_page .= '<hr>';
			}
		}
		$value = null;
		if(isset($line) and isset($line['access'])) {
			$value = $line['access'];
		}
		else {
			$value = "";
		}
		if(isset($_GET['accesshelp']) && $_GET['accesshelp'] == 1) {
			$tpl_page .= '<span>Access levels are a new feature to limit the functions which Moderators can access. For example, if you set
			an access level of 5; the moderator can access all functions below and including Manage stickes/lock threads (which corresponds to level 5).</span><ol><li>Recently uploaded images</li><li>View Reports</li><li>Delete thread/post</li><li>Delete/find all posts by IP</li><li>Manage stickes/lock threads</li>
			<li>View/Add/Remove bans and warnings</li><li>All boards bans and warnings</li><li>Site ban</li><li>Manage Boards (Super Moderator)</li></ol><br/>';
		}
		$tpl_page .= '<form action="manage_page.php?action=staff" method="post">
		
		<label for="username">' . _gettext('Username') . ':</label>
		<input type="text" name="staffusername"><br>
	
		<label for="password">' . _gettext('Password') . ':</label>
		<input type="text" name="staffpassword"><br>
		
		<label for="type">' . _gettext('Type') . ':</label>
		<select name="type">
		<option value="1">' . _gettext('Administrator') . '</option>
		<option value="2" selected>' . _gettext('Moderator') . '</option>
		<option value="0">' . _gettext('Janitor') . '</option>
		<option value="3">VIP</option>
		</select><br>
		<label for="access">'._gettext('Access level [<a href="manage_page.php?action=staff&accesshelp=1">?</a>]').':</label> <input type="text" maxlength="4" name="access" value="'.$value.'"/><br/>
		<input type="submit" value="' .  _gettext('Add staff member') . '">
		
		</form>
		<hr><br/>';
		
		$tpl_page .= '<br><br/>';
		
		$tpl_page .= '<table border="1" width="100%"><tr><th>' . _gettext('Username') . '</th><th>' . _gettext('Added on') . '</th><th>' . _gettext('Last active') . '</th><th>' . _gettext('Moderating boards') . '</th><th>&nbsp;</th></tr>' . "\n";
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Administrators') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '1' ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'] . '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>&nbsp;</td><td>[<a href="?action=staff&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]';
				$tpl_page .= '[<a href="?action=staff&del=' . $line['id'] . '">x</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Super Moderators') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '2' AND `suspended` != 1 AND `access` >= 9 ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'];
				$tpl_page .= '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="?action=staff&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=staff&suspend=' . $line['id'] . '">' . _gettext('Suspend') . '</a>] [<a href="?action=staff&del=' . $line['id'] . '">x</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Moderators') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '2' AND `suspended` != 1 AND `access` < 9 ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'];
				$access = $tc_db->GetAll("SELECT HIGH_PRIORITY `access` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '". $line['username'] ."' AND `access` >= 9");
				foreach($access as $ac) {
					$tpl_page .= ' [<a style="color: red; text-decoration: none" href="manage_page.php?action=staff&supermod=1">*</a>]';
				}
				$tpl_page .= '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="?action=staff&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=staff&suspend=' . $line['id'] . '">' . _gettext('Suspend') . '</a>] [<a href="?action=staff&del=' . $line['id'] . '">x</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Janitors') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '0' AND `suspended` != 1 ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'] . '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="?action=staff&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=staff&suspend=' . $line['id'] . '">' . _gettext('Suspend') . '</a>] [<a href="?action=staff&del=' . $line['id'] . '">x</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="5">' . _gettext('None') . '</td></tr>' . "\n";
		}
				$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>' . _gettext('Suspended') . '</b></font></td></tr>' . "\n";
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `suspended` = 1 ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'] . '</td><td>' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>';
				if ($line['lastactive'] == 0) {
					$tpl_page .= _gettext('Never');
				} elseif ((time() - $line['lastactive']) > 300) {
					$tpl_page .= timeDiff($line['lastactive'], false);
				} else {
					$tpl_page .= _gettext('Online now');
				}
				$tpl_page .= '</td><td>';
				if ($line['boards'] != '') {
					if ($line['boards'] == 'allboards') {
						$tpl_page .= 'All boards';
					} else {
						$tpl_page .= '<b>/' . implode('/</b>, <b>/', explode('|', $line['boards'])) . '/</b>';
					}
				} else {
					$tpl_page .= _gettext('No boards');
				}
				$tpl_page .= '</td><td>[<a href="?action=staff&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=staff&unsuspend=' . $line['id'] . '">' . _gettext('Unsuspend') . '</a>] [<a href="?action=staff&del=' . $line['id'] . '">x</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="4">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><b>VIP</b></font></td></tr>' . "\n";
		$tpl_page .= '<tr><th>' . _gettext('Posting password') . '</th><th colspan="3">' . _gettext('Added on') . '</th><th>&nbsp;</th>' . "\n";;
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '3' ORDER BY `username` ASC");
		if (count($results) > 0) {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>' . $line['username'] . '</td><td colspan="2">' . date("y/m/d(D)H:i", $line['addedon']) . '</td><td>[<a href="?action=staff&edit=' . $line['id'] . '">' . _gettext('Edit') . '</a>]&nbsp;[<a href="?action=staff&del=' . $line['id'] . '">x</a>]</td></tr>' . "\n";
			}
		} else {
			$tpl_page .= '<tr><td colspan="5">' . _gettext('None') . '</td></tr>' . "\n";
		}
		$tpl_page .= '</table>';
	}
	// Credits to Eman for this code 
	function viewdeletedthread() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
	
		$tpl_page .= '<h2>' . ucwords(_gettext('View deleted thread')) . '</h2><br>This will only work for imageboards.<br/><br/>';
		if(! isset($_GET['board'])) $_GET['board'] = null;
		if(! isset($_GET['thread'])) $_GET['thread'] = null;
		$board = mysqli_real_escape_string($tc_db->link, $_GET['board']);
		$thread = mysqli_real_escape_string($tc_db->link, $_GET['thread']);		
		if (!$thread) {
			$thread = "0";
		}	
		if (!$board) {				
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` WHERE 1 ORDER BY `name` ASC");
			$tpl_page .= "<form method=\"get\" action=\"\"><input type=\"hidden\" name=\"action\" value=\"viewdeletedthread\">Select Board: <select name=\"board\">";		
			foreach ($results as $line) {
				$name = $line['name'];
				$tpl_page .= "<option value=\"$name\">/$name/</option>";		
			}			
		$tpl_page .= "</select>&nbsp;<br/><input type=submit value=\"Go\">";		
		} 
		else {		
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_$board` WHERE `id` = '$thread' OR `parentid` = '$thread' AND `IS_DELETED` = 1 ORDER BY `id` ASC LIMIT 200");		
			if(count($results) <= 0) {
				$tpl_page .= 'No results found.';
			}
			$tpl_page .= "
			<form method=\"get\" action=\"\">
			<input type=\"hidden\" name=\"action\" value=\"viewdeletedthread\">
			<input type=\"hidden\" name=\"board\" value=\"$board\">";
			if ($thread == "0" ) {
				$tpl_page .= "<b>Latest deleted threads on /$board/</b><br/>";
			} 
			else {
				$tpl_page .= "<b>Thread $thread on /$board/</b><br/>";
			}
			$tpl_page .= '<br/>';
			foreach ($results as $line) {
				$id = $line['id'];
				$filename = $line['filename'];
				$filename_original = $line['filename_original'];
				$filetype = $line['filetype'];
				$filesize_formatted = $line['filesize_formatted'];
				$image_w = $line['image_w'];
				$image_h = $line['image_h'];
				$message = htmlspecialchars($line['message']);
				$name = htmlspecialchars($line['name']);
				$tripcode = $line['tripcode'];
				$postedat = formatDate($line['postedat']);
				$subject = htmlspecialchars($line['subject']);
				$posterauthority = $line['posterauthority'];	
				$tpl_page .= '<div style="background: #D6DAF0; padding: 5px 10px 5px 10px;border: 1px solid #6f819b; width: 520px;height: auto;font-family:Arial">';
				if ($thread == "0") {
					$view = "<a href=\"?action=viewdeletedthread&board=$board&thread=$id\">[View]</a>";
				} else {
					$view = "";
				}
				if ($name == "") {
					$name = "Anonymous";
				} else {
					$name = "<font color=blue>$name</font>";
				}			
				if ($tripcode != "") {
					$tripcode = "<font color=green>!$tripcode</font>";
				}			
				if ($subject != "") {
					$subject = "<font color=red>$subject</font> - ";
				}			
				if ($posterauthority == "1") {
					$posterauthority = "<font color=purple><b>?????? Admin ??????</b></font>";
				} elseif ($posterauthority == "2") {
					$posterauthority = "<font color=red><b>## Mod ##</b></font>";
				} elseif ($posterauthority == "4") {
					$posterauthority = "<font color=red><b>## Super Mod ## </b></font>";
				}
				else {
					$posterauthority = "";
				}						
				$tpl_page .= '<span style="color:#117743;font-weight:bold">'. " $subject $name $tripcode $posterauthority | $postedat No. $id $view<br>";
				if ($filename != "") {
					$tpl_page .= "
							File: <a href=\"". KU_WEBPATH ."/$board/src/$filename.$filetype\" target=_new>$filename.$filetype</a> -( $filesize_formatted, {$image_w}x{$image_h}, $filename_original.$filetype )
					";
				}
				$tpl_page .= "<tr>";
				
				if ($filename != "") {
					$tpl_page .= "
						</span><br/><center><a href=\"". KU_WEBPATH ."/$board/src/$filename.$filetype\" target=_new><img style=\"float:left;margin-right: 5px\" src=\"". KU_WEBPATH ."/$board/thumb/{$filename}s.$filetype\" border=0></a></center>
					";
				}
				$message = stripslashes($message);
				$tpl_page .= "$message" . '<br style="clear: all"/></div><br/>';
			}
		}
	}
	function spam() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
	
		$spamact = (isset($_GET['spamact'])) ? $_GET['spamact'] : '';
		$tpl_page .= '<h2>Spam Filter - '.$spamact.'</h2><br>';
		if ($spamact == "") {
			$tpl_page .= '<fieldset><legend>Spamfilter Address to Add</legend><br>
			<label for="ip">URL to Add:</label><form method="get" action="">
			<input type="text" size="40" maxlength="120" name="url">
			<input type="hidden" name="action" value="spam">
			<input type="hidden" name="spamact" value="add">
			</fieldset><input type="submit" value="Add URL"></form><br>';
			
			$tpl_page .= '<table cellspacing="2" cellpadding="1" border="1"><tr><th>ID</th><th>&nbsp;</th><th>URL</th></tr>';
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "spamfilter` ORDER BY `id` ASC");
			$count = 0;
			foreach ($results as $line) {
                        $count += 1;
                        if ($count%2 == false) {
                                $linecolour = "#fff";
                        } else {
                                $linecolour = "#dce3e8";
                        }
				$tpl_page .= "<tr bgcolor=$linecolour><td>" . $line['id'] . "</td>
				<td><a href='?action=spam&spamact=delete&id=" . $line['id'] . "&url=" . $line['url'] . "'>[x]</a></td>
				<td width=100%>" . $line['url'] . "</td></tr>";
			}
			$tpl_page .= '</table>';
		} 
		
		elseif ($spamact == "delete") {
			$id = $_GET['id'];
			$url = $_GET['url'];
                        $url = mysqli_real_escape_string($tc_db->link, $url);
                        $id = mysqli_real_escape_string($tc_db->link, $id);
			$tpl_page .= "DELETE $id";
			$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "spamfilter` WHERE `id` = '$id' LIMIT 1");
			
			$wut = $url;
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "spamfilter` ORDER BY `id` ASC");
			foreach ($results as $line) {
				$spamlines .= $line['url']."\n";
			}
			$tpl_page .= "<pre>$spamlines</pre>";
			$spamfile = fopen("spam.txt", 'w') or die("can't open file");
			fwrite($spamfile, $spamlines);
			fclose($spamfile);		
			management_addlogentry(_gettext('Deleted "'. $wut .'" from Spamfilter'), 0);	
		}
		
		elseif ($spamact == "add") {
			$url = $_GET['url'];
			$url = mysqli_real_escape_string($tc_db->link, $url);
			$tpl_page .= "ADD $url";
			$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "spamfilter` ( `url` ) VALUES ('$url')");
			
			$wut = $url;
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "spamfilter` ORDER BY `id` ASC");
			foreach ($results as $line) {
				$spamlines .= $line['url']."\n";
			}
			$tpl_page .= "<pre>$spamlines</pre>";
			$spamfile = fopen("spam.txt", 'w') or die("can't open file");
			fwrite($spamfile, $spamlines);
			fclose($spamfile);		
			management_addlogentry(_gettext('Added "'. $wut .'" to Spamfilter'), 0);	
		}
	}
	// Move thread
	function movethread() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>' . ucwords(_gettext('Move thread')) . "</h2><br><b>Warning: Do not move threads across image board types, i.e. an imageboard thread to a text board thread.</b><br><br>";
		if (isset($_POST['id']) && isset($_POST['board_from']) && isset($_POST['board_to'])) {
			if(empty($_POST['board_from'])  || empty($_POST['board_to'])) {
				exitWithErrorPage('Please select a board');
			}

			$board_from = mysqli_real_escape_string($tc_db->link, $_POST['board_from']);
			$board_from_object = new Board($board_from);
			$board_to =  mysqli_real_escape_string($tc_db->link, $_POST['board_to']);
			$board_to_object = new Board($board_to);

echo "stage 1<br>";
			if(!is_numeric($_POST['id'])) {
				exitWithErrorPage('Invalid thread ID');
			}

			$old_thread_id = mysqli_real_escape_string($tc_db->link, $_POST['id']);
			$op_post_results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `filename`, `filetype`, `initial_board` FROM " . KU_DBPREFIX . "posts_" . $board_from . " WHERE `id` = '" . $old_thread_id . "' AND `parentid` = 0 AND `IS_DELETED` = 0 LIMIT 1");
			if(count($op_post_results) <= 0) {
				exitWithErrorPage('Invalid thread ID');
			}
			$op_post = $op_post_results[0];
			$posts_to_move = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `filename`, `filetype`, `initial_board` FROM " . KU_DBPREFIX . "posts_" . $board_from . " WHERE `parentid` = '" . $old_thread_id . "' AND `IS_DELETED` = 0 ORDER BY `id` ASC");
			array_unshift($posts_to_move, $op_post);

			$posts_with_invalid_file_type = array_values(array_filter($posts_to_move, function($item) use ($board_to_object) {
				return is_numeric($item['filename'])
					&& !in_array($item['filetype'], $board_to_object->allowed_file_types['video'])
					&& !in_array($item['filetype'], $board_to_object->allowed_file_types['image'])
					&& !in_array($item['filetype'], $board_to_object->allowed_file_types['misc']);
			}));

			if (count($posts_with_invalid_file_type)) {
				$error_message = 'Some files are not allowed on board /' . $board_to_object->board_dir . '/!<br>';
				foreach ($posts_with_invalid_file_type as $post) {
					$error_message .= "File of type " . $post['filetype'] . " in post " . $post['id'] . " is not allowed on board /" . $board_to_object->board_dir . "/!<br>";
				}
				exitWithErrorPage($error_message);
			}

			$files_to_move = [];
			$existing_files = [];
			$missing_files = [];
			$probably_already_moved = [];

			foreach ($posts_to_move as $line) {
				if (is_numeric($line['filename'])) {
					$is_misc_file = in_array($line['filetype'], $board_to_object->allowed_file_types['misc']);
					$is_video_file = in_array($line['filetype'], $board_to_object->allowed_file_types['video']);

					if ($is_misc_file) {
						$files_to_move[$line['id']] = [[
							'from' => KU_ROOTDIR . $board_from . '/src/' . $line['filename'] . '.' . $line['filetype'],
							'to' =>  KU_ROOTDIR . $board_to . '/src/' . $line['filename'] . '.' . $line['filetype']
						]];
					} else {
						$thumb_file_type = $is_video_file ? 'jpg' : $line['filetype'];

						$files_to_move[$line['id']] = [[
							'from' => KU_ROOTDIR . $board_from . '/src/' . $line['filename'] . '.' . $line['filetype'],
							'to' =>  KU_ROOTDIR . $board_to . '/src/' . $line['filename'] . '.' . $line['filetype']
						], [
							'from' => KU_ROOTDIR . $board_from . '/thumb/' . $line['filename'] . 's.' . $thumb_file_type,
							'to' => KU_ROOTDIR . $board_to . '/thumb/' . $line['filename'] . 's.' . $thumb_file_type
						], [
							'from' => KU_ROOTDIR . $board_from . '/thumb/' . $line['filename'] . 'c.' . $thumb_file_type,
							'to' => KU_ROOTDIR . $board_to . '/thumb/' . $line['filename'] . 'c.' . $thumb_file_type
						]];
					}
					foreach ($files_to_move[$line['id']] as $file_to_move) {
						if (!file_exists($file_to_move['from']) && file_exists($file_to_move['to'])) {
							$probably_already_moved[$line['id']][] = $file_to_move;
						} else if (!file_exists($file_to_move['from'])) {
							$missing_files[$line['id']][] = $file_to_move;
						} else if (file_exists($file_to_move['to'])) {
							$existing_files[$line['id']][] = $file_to_move;
						}
					}
				}
			}


			if (count($probably_already_moved) || count($missing_files) || count($existing_files)) {
				$error_message = "Can't move files!<br>";
				if (count($probably_already_moved)) {
					$error_message .= "Some files were probably already moved. Move them back manually before proceeding.<br>";
					foreach ($probably_already_moved as $postId => $post_files_to_move) {
						$error_message .= 'In post ' . $postId . ':<br>';
						foreach ($post_files_to_move as $file_to_move) {
							$error_message .= $file_to_move['from'] . ' does not exist, but ' . $file_to_move['to'] . ' already exists<br>';
						}
					}
				}
				if (count($missing_files)) {
					$error_message .= "Some files are missing.<br>";
					foreach ($missing_files as $postId => $post_files_to_move) {
						$error_message .= 'In post ' . $postId .  ':<br>';
						foreach ($post_files_to_move as $file_to_move) {
							$error_message .= $file_to_move['from'] . '<br>';
						}
					}
				}
				if (count($existing_files)) {
					$error_message .= "Some files already exist. You can try to run cleanup to remove unused files, but this will also remove already moved files if present.<br>";
					foreach ($existing_files as $postId => $post_files_to_move) {
						$error_message .= 'In post ' . $postId . ':<br>';
						foreach ($post_files_to_move as $file_to_move) {
							$error_message .= $file_to_move['to'] . '<br>';
						}
					}
				}
				exitWithErrorPage($error_message);
			}

			$moved_posts = [];
			$moved_files = [];

			$temp_id = 0;

			$new_thread_id = null;

echo "stage 2<br>";
			$tc_db->Execute("START TRANSACTION");

			try {
				foreach ($posts_to_move as $line) {
					if (isset($files_to_move[$line['id']])) {
						foreach ($files_to_move[$line['id']] as $file_to_move) {
							if (!rename($file_to_move['from'], $file_to_move['to'])) {
								throw new Exception("Error moving files");
							} else {
								$moved_files[] = $file_to_move;
							}
						}
					}

					$tc_db->Execute("UPDATE " . KU_DBPREFIX . "posts_" . $board_from . " SET `id` = " . $temp_id . " WHERE `id` = " . $line['id']);
					$tc_db->Execute("INSERT INTO " . KU_DBPREFIX . "posts_" . $board_to . " SELECT * FROM " . KU_DBPREFIX . "posts_" . $board_from . " WHERE `id` = " . $temp_id);
					$insert_id = $tc_db->Insert_Id();
					if ($new_thread_id === null) {
						//op-post
						$new_thread_id = $insert_id;
					} else {
						$tc_db->Execute("UPDATE " . KU_DBPREFIX . "posts_" . $board_to . " SET `parentid` = " . $new_thread_id . " WHERE `id` = " . $insert_id);
					}
					processPost($insert_id, $new_thread_id, $old_thread_id, $board_from, $board_to, $moved_posts);
					if (!$line['initial_board']) {
						$tc_db->Execute("UPDATE " . KU_DBPREFIX . "posts_" . $board_to . " SET `initial_board` = '" . $board_from . "' WHERE `id` = '" . $insert_id . "'");
					}
					$tc_db->Execute("DELETE FROM " . KU_DBPREFIX . "posts_" . $board_from . " WHERE `id` = " . $temp_id);
					$moved_posts[$line['id']] = $insert_id;
				}
			} catch (Throwable $t) {
				echo "Some error happened! Trying to move files back...<br>";
				foreach ($moved_files as $moved_file) {
					try {
						if (!rename($moved_file['to'], $moved_file['from'])) {
							echo "Can't move back " . $moved_file['to'] . "<br>";
						}
					} catch (Throwable $t) {
						echo "Error while moving back ".$moved_file['to'] . "<br>";
					}
				}
				echo "Finished moving files back.<br>";

				if ($t->getMessage() === "Error moving files") {
					exitWithErrorPage("Error moving files");
				} else {
					throw $t;
				}
			}

echo "stage 3<br>";
			$tc_db->Execute("COMMIT");
			$board_from_object->RegenerateAll();
			$board_to_object->RegenerateAll();
			$tpl_page .= _gettext('Move complete.') . '<br><hr>';
		}

		$tpl_page .= '<form action="?action=movethread" method="post">
		
		<label for="id">' . _gettext('ID') . ':</label>
		<input type="text" name="id">
		<br>
		
		<label for="board_from">' . _gettext('From') . ':</label>' .
		$this->MakeBoardListDropdown('board_from', $this->BoardList($_SESSION['manageusername'])) .
		'<br>
		
		<label for="board_to">'  ._gettext('To') . ':</label>' .
		$this->MakeBoardListDropdown('board_to', $this->BoardList($_SESSION['manageusername'])) .
		'<br>
		
		<input type="submit" value="' . _gettext('Move thread') . '">';
	}
	
	/* Search for text in posts */
	function search() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		if (isset($_GET['query'])) {
			$search_query = $_GET['query'];
			if (isset($_GET['s'])) {
				$s = $_GET['s'];
			} else {
				$s = 0;
			}
			$search_query_array = explode('KUSABA_AND', $search_query);
			$trimmed = trim($search_query);
			$limit = 10;
			if ($trimmed == '') {
				$tpl_page .= _gettext('Please enter a search query.');
				exit;
			}
			$boardlist = $this->BoardList($_SESSION['manageusername']);
			$likequery = '';
			foreach ($search_query_array as $search_split) {
				$likequery .= "`message` LIKE '" . mysqli_real_escape_string($tc_db->link, str_replace('_', '\_', $search_split)) . "' AND ";
			}
			$likequery = substr($likequery, 0, -4);
			$query = '';
			foreach ($boardlist as $board) {
				$query .= "SELECT *, '" . $board . "' as board FROM `posts_" . $board . "` WHERE `IS_DELETED` = 0 AND " . $likequery . " UNION ";
			}
			$query = substr($query, 0, -6) . 'ORDER BY `postedat` DESC';
			$numresults = $tc_db->GetAll($query);
			$numrows = count($numresults);
			if ($numrows == 0) {
				$tpl_page .= '<h4>' . _gettext('Results') . '</h4>';
				$tpl_page .= '<p>' . _gettext('Sorry, your search returned zero results.') . '</p>';
			} else {
				$query .= " LIMIT $s, $limit";
				$results = $tc_db->GetAll($query);
				$tpl_page .= '<p style="font-size: 1.5em;">Results for: <b>' . $search_query . '</b></p>';
				$count = 1 + $s;
				foreach ($results AS $line) {
					$tpl_page .= '<span style="font-size: 1.5em;">' . $count . '.</span> <span style="font-size: 1.3em;">Board: /' . $line['board'] . '/, <a href="'.KU_BOARDSPATH . '/' . $line['board'] . '/res/';
					if ($line['parentid'] == 0) {
						$tpl_page .= $line['id'] . '.html">';
					} else {
						$tpl_page .= $line['parentid'] . '.html#' . $line['id'] . '">';
					}
					
					if ($line['parentid'] == 0) {
						$tpl_page .= 'Thread #' . $line['id'];
					} else {
						$tpl_page .= 'Thread #' . $line['parentid'] . ', Post #' . $line['id'];
					}
					$tpl_page .= '</a></span>';
					
					$regexp = '/(';
					foreach ($search_query_array as $search_word) {
						$regexp .= preg_quote($search_word) . '|';
					}
					$regexp = substr($regexp, 0, -1) . ')/';
					//$line['message'] = preg_replace_callback($regexp, array(&$this, 'search_callback'), stripslashes($line['message']));
					$line['message'] = stripslashes($line['message']);
					$tpl_page .= '<fieldset>' . $line['message'] . '</fieldset><br>';
					$count++;
				}
				$currPage = (($s / $limit) + 1);
				$tpl_page .= '<br>';
				if ($s >= 1) {
					$prevs = ($s - $limit);
					$tpl_page .= "&nbsp;<a href=\"?action=search&s=$prevs&query=" . urlencode($search_query) . "\">&lt;&lt; Prev 10</a>&nbsp&nbsp;";
				}
				$pages = intval($numrows / $limit);
				if ($numrows % $limit) {
					$pages++;
				}
				if (!((($s + $limit) / $limit) == $pages) && $pages != 1) {
					$news = $s + $limit;
					$tpl_page .= "&nbsp;<a href=\"?action=search&s=$news&query=" . urlencode($search_query) . "\">Next 10 &gt;&gt;</a>";
				}
				
				$a = $s + ($limit);
				if ($a > $numrows) {
					$a = $numrows;
				}
				$b = $s + 1;
				
				$tpl_page .= $this->search_results_display($a, $b, $numrows);
			}
		}
		
		$tpl_page .= '<form action="?" method="get">
		<input type="hidden" name="action" value="search">
		<input type="hidden" name="s" value="0">
		
		<b>'._gettext('Query').'</b>:<br><input type="text" name="query" ';
		if (isset($_GET['query'])) {
			$tpl_page .= 'value="' . $_GET['query'] . '" ';
		}
		$tpl_page .= 'size="52"><br>
		
		<input type="submit" value="'._gettext('Search').'"><br><br>
		
		<h1>Search Help</h1>
		
		Separate search terms with the word <b>KUSABA_AND</b><br><br>
		
		To find a single phrase anywhere in a post\'s message, use:<br>
		%some phrase here%<br><br>
		
		To find a phrase at the beginning of a post\'s message:<br>
		some phrase here%<br><br>
		
		To find a phrase at the end of a post\'s message:<br>
		%some phrase here<br><br>
		
		To find two phrases anywhere in a post\'s message, use:<br>
		%first phrase here%KUSABA_AND%second phrase here%<br><br>
		
		</form>';
	}
	
	function search_callback($matches) {
		print_r($matches);
		return '<b>' . $matches[0] . '</b>';
	}
	
	function search_results_display($a, $b, $numrows) {
		return '<p>' . _gettext('Results') . ' <b>' . $b . '</b> to <b>' . $a . '</b> of <b>' . $numrows . '</b></p>' . "\n" .
		'<hr>';
	}
	
	/* View and delete reports */
	function reports() {
		global $tc_db, $smarty, $tpl_page;
		$this->ModeratorsOnly();
		if($this->CheckAccess() < 2) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		$tpl_page .= '<h2>' . _gettext('Reports') . '</h2><br>';
		if (isset($_GET['clear'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "reports` WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['clear']) . "' LIMIT 1");
			if (count($results) > 0) {
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "reports` SET `cleared` = '1' WHERE `id` = '" . mysqli_real_escape_string($tc_db->link, $_GET['clear']) . "' LIMIT 1");
				$tpl_page .= 'Report successfully cleared.<hr>';
			}
		}
		$query = "SELECT * FROM `" . KU_DBPREFIX . "reports` WHERE `cleared` = 0";
		if (!$this->CurrentUserIsAdministrator()) {
			$boardlist = $this->BoardList($_SESSION['manageusername']);
			if (!empty($boardlist)) {
				$query .= ' AND (';
				foreach ($boardlist as $board) {
					$query .= ' `board` = \'' . $board . '\' OR';
				}
				$query = substr($query, 0, -3) . ')';
			} else {
				$tpl_page .= 'You do not moderate any boards.';
			}
		}
		$resultsreport = $tc_db->GetAll($query);
		if (count($resultsreport) > 0) {
			$tpl_page .= '<table border="1" width="100%"><tr><th>Board</th><th>Post</th><th>File</th><th>Message</th><th>Reporter IP</th><th>Action</th></tr>';
			foreach ($resultsreport as $linereport) {
				$results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts_" . $linereport['board'] . "` WHERE `id` = " . mysqli_real_escape_string($tc_db->link, $linereport['postid']) . "");
				foreach ($results as $line) {
					if ($line['IS_DELETED'] == 0) {
						$tpl_page .= '<tr><td>/' . $linereport['board'] . '/</td><td><a href="' . KU_BOARDSPATH . '/' . $linereport['board'] . '/res/';
						if ($line['parentid'] == '0') {
							$tpl_page .= $linereport['postid'];
							$post_threadorpost = 'thread';
						} else {
							$tpl_page .= $line['parentid'];
							$post_threadorpost = 'post';
						}
						$tpl_page .= '.html#' . $linereport['postid'] . '">' . $line['id'] . '</a></td><td>';
						if ($line['filename'] == 'removed') {
							$tpl_page .= 'removed';
						} elseif ($line['filename'] == '') {
							$tpl_page .= 'none';
						} elseif ($line['filetype'] == 'jpg' || $line['filetype'] == 'gif' || $line['filetype'] == 'png') {
							$tpl_page .= '<a href="' . KU_BOARDSPATH . '/' . $linereport['board'] . '/src/' . $line['filename'] . '.' . $line['filetype'] . '"><img src="' . KU_BOARDSPATH . '/' . $linereport['board'] . '/thumb/' . $line['filename'] . 's.' . $line['filetype'] . '" border="0"></a>';
						} else {
							$tpl_page .= '<a href="' . KU_BOARDSPATH . '/' . $linereport['board'] . '/src/' . $line['filename'] . '.' . $line['filetype'] . '">File</a>';
						}
						$tpl_page .= '</td><td>';
						if ($line['message'] != '') {
							$tpl_page .= stripslashes($line['message']);
						} else {
							$tpl_page .= '&nbsp;';
						}
						$tpl_page .= '</td><td>' . md5_decrypt($linereport['ip'], KU_RANDOMSEED) . '</td><td><a href="?action=reports&clear=' . $linereport['id'] . '">Clear</a>&nbsp;&#91;<a href="?action=delposts&boarddir=' . $linereport['board'] . '&del' . $post_threadorpost . 'id=' . $line['id'] . '" title="Delete" onclick="return confirm(\'Are you sure you want to delete this thread/post?\');">D</a>&nbsp;<a href="' . KU_CGIPATH . '/manage_page.php?action=delposts&boarddir=' . $linereport['board'] . '&del' . $post_threadorpost . 'id=' . $line['id'] . '&postid=' . $line['id'] . '" title="Delete &amp; Ban" onclick="return confirm(\'Are you sure you want to delete and ban this poster?\');">&amp;</a>&nbsp;<a href="?action=bans&banboard=' . $linereport['board'] . '&banpost=' . $line['id'] . '" title="Ban">B</a>&#93;</td></tr>';
					} else {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "reports` SET `cleared` = 1 WHERE id = " . $linereport['id'] . "");
					}
				}
			}
			$tpl_page .= '</table>';
		} else {
			$tpl_page .= 'No reports to show.';
		}
	}
	
	/* View recently uploaded images */
	function recentimages() {
		global $tc_db, $smarty, $tpl_page;
		$this->ModeratorsOnly();
		if($this->CheckAccess() < 1) {
			exitWithErrorPage('You do not have permission to access this page');
		}
		if (!isset($_SESSION['imagesperpage'])) {
			$_SESSION['imagesperpage'] = 50;
		}
		
		if (isset($_GET['show'])) {
			if ($_GET['show'] == '25' || $_GET['show'] == '50' || $_GET['show'] == '75' || $_GET['show'] == '100') {
				$_SESSION['imagesperpage'] = $_GET['show'];
			}
		}
		
		$tpl_page .= '<h2>' . ucwords(_gettext('Recently uploaded images')) . '</h2><br>
		Number of images to show per page: <a href="?action=recentimages&show=25">25</a>, <a href="?action=recentimages&show=50">50</a>, <a href="?action=recentimages&show=75">75</a>, <a href="?action=recentimages&show=100">100</a> (note that this is a rough limit, more may be shown)<br>';
		if (isset($_POST['clear'])) {
			if ($_POST['clear'] != '') {
				$clear_decrypted = md5_decrypt($_POST['clear'], KU_RANDOMSEED);
				if ($clear_decrypted != '') {
					$clear_unserialized = unserialize($clear_decrypted);
					
					foreach ($clear_unserialized as $clear_sql) {
						$tc_db->Execute($clear_sql);
					}
					$tpl_page .= 'Successfully marked previous images as reviewed.<hr>';
				}
			}
		}
		
		$dayago = (time() - 86400);
		$imagesshown = 0;
		$reviewsql_array = array();
		
		$boardlist = $this->BoardList($_SESSION['manageusername']);
		foreach ($boardlist as $board) {
			if ($imagesshown <= $_SESSION['imagesperpage']) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY p.`id`, p.`parentid`, p.`filename`, p.`filetype`, p.`thumb_w`, p.`thumb_h`, f.`mediatype` FROM `" . KU_DBPREFIX . "posts_" . $board . "` AS p JOIN `" . KU_DBPREFIX . "filetypes` AS f ON f.filetype=p.filetype WHERE `postedat` > " . $dayago . " AND `mediatype` IN ('image','video') AND `reviewed` = 0 AND `IS_DELETED` = 0 ORDER BY RAND() LIMIT " . mysqli_real_escape_string($tc_db->link, $_SESSION['imagesperpage']));
				if (count($results) > 0) {
					$reviewsql = "UPDATE `" . KU_DBPREFIX . "posts_" . $board . "` SET `reviewed` = 1 WHERE ";
					foreach ($results as $line) {
						$reviewsql .= '`id` = ' . $line['id'] . ' OR ';
						$real_parentid = ($line['parentid'] == 0) ? $line['id'] : $line['parentid'];
						if($line['mediatype'] == 'video'){
							$line['filetype']='jpg';
						}
						$tpl_page .= '<a href="' . KU_BOARDSPATH . '/' . $board . '/res/' . $real_parentid . '.html#' . $line['id'] . '"><img src="' . KU_BOARDSPATH . '/' . $board . '/thumb/' . $line['filename'] . 's.' . $line['filetype'] . '" width="' . $line['thumb_w'] . '" height="' . $line['thumb_h'] . '" border="0"></a> ';
					}
					
					$reviewsql = substr($reviewsql, 0, -3) . 'LIMIT ' . count($results);
					$reviewsql_array[] = $reviewsql;
					$imagesshown += count($results);
				}
			}
		}
		
		if ($imagesshown > 0) {
			$tpl_page .= '<br><br>' . $imagesshown . ' images shown.<br>
			<form action="?action=recentimages" method="post">
			<input type="hidden" name="clear" value="' . md5_encrypt(serialize($reviewsql_array), KU_RANDOMSEED) . '">
			<input type="submit" value="Clear All On Page As Reviewed">
			</form>';
		} else {
			$tpl_page .= '<br><br>No recent images currently need review.';
		}
	}
	
	/* Display posting rates for the past hour */
	function posting_rates() {
		global $tc_db, $smarty, $tpl_page;
		
		$tpl_page .= '<h2>' . _gettext('Posting rates (past hour)') . '</h2><br>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` ORDER BY `order` ASC");
		if (count($results) > 0) {
			$tpl_page .= '<table border="1" cellspacing="2" cellpadding="2" width="100%"><tr><th>' . _gettext('Board') . '</th><th>' . _gettext('Threads') . '</th><th>' . _gettext('Replies') . '</th><th>' . _gettext('Posts') . '</th></tr>';
			foreach ($results as $line) {
				$rows_threads = $tc_db->GetOne("SELECT HIGH_PRIORITY count(id) FROM `" . KU_DBPREFIX . "posts_" . $line['name'] . "` WHERE `parentid` = 0 AND `postedat` >= " . (time() - 3600));
				$rows_replies = $tc_db->GetOne("SELECT HIGH_PRIORITY count(id) FROM `" . KU_DBPREFIX . "posts_" . $line['name'] . "` WHERE `parentid` != 0 AND `postedat` >= " . (time() - 3600));
				$rows_posts = $rows_threads + $rows_replies;
				$threads_perminute = $rows_threads;
				$replies_perminute = $rows_replies;
				$posts_perminute = $rows_posts;
				$tpl_page .= '<tr><td><b>' . $line['name'] . '</b></td><td>' . $threads_perminute . '</td><td>' . $replies_perminute . '</td><td>' . $posts_perminute . '</td></tr>';
			}
			$tpl_page .= '</table>';
		} else {
			$tpl_page .= _gettext('No boards');
		}
	}
	
	function statistics() {
		global $tc_db, $smarty, $tpl_page;
		
		$tpl_page .= '<h2>Statistics</h2><br>';
		$tpl_page .= '<img src="manage_page.php?graph&type=day"> 
		<img src="manage_page.php?graph&type=week"> 
		<img src="manage_page.php?graph&type=postnum"> 
		<img src="manage_page.php?graph&type=unique"> 
		<img src="manage_page.php?graph&type=posttime">';
	}
	
	/* If the user logged in isn't an admin, kill the script */
	function AdministratorsOnly() {
		global $tc_db, $smarty, $tpl_page;
		
		if (!$this->CurrentUserIsAdministrator()) {
			exitWithErrorPage('That page is for admins only.');
		}
	}
	/* Root administrator defined in the config */
	function RootOnly() {
		global $tc_db, $smarty, $tpl_page;
		
		if (!$this->CurrentUserIsAdministrator() || $_SESSION['manageusername'] != KU_ROOT) {
			exitWithErrorPage('Permission denied.');
		}
	}
	
	/* If the user logged in isn't an moderator or higher, kill the script */
	function ModeratorsOnly() {
		global $tc_db, $smarty, $tpl_page;
		
		if ($this->CurrentUserIsAdministrator()) {
			return true;
		} else {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
			foreach ($results as $line) {
				if ($line['type'] != 2) {
					exitWithErrorPage('That page is for moderators and administrators only.');
				}
			}
		}
	}
	function CheckAccess() {
		if ($this->CurrentUserIsAdministrator()) {
			return 9999;
		}
		global $tc_db, $smarty, $tpl_page;		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `access` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
		foreach ($results as $line) {
			return $line['access'];
		}
	}
	
	/* See if the user logged in is an admin */
	function CurrentUserIsAdministrator() {
		global $tc_db, $smarty, $tpl_page;
		
		if ($_SESSION['manageusername'] == '' || $_SESSION['managepassword'] == '') {
			$_SESSION['manageusername'] = '';
			$_SESSION['managepassword'] = '';
			return false;
		}
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
		foreach ($results as $line) {
			if ($line['type'] == 1) {
				return true;
			} else {
				return false;
			}
		}
		
		/* If the function reaches this point, something is fishy.  Kill their session */
		session_destroy();
		exitWithErrorPage('Invalid session, please log in again.');
	}
	
	/* See if the user logged in is a moderator */
	function CurrentUserIsModerator() {
		global $tc_db, $smarty, $tpl_page;
		
		if ($_SESSION['manageusername'] == '' || $_SESSION['managepassword'] == '') {
			$_SESSION['manageusername'] = '';
			$_SESSION['managepassword'] = '';
			return false;
		}
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
		foreach ($results as $line) {
			if ($line['type'] == 2) {
				return true;
			} else {
				return false;
			}
		}
		
		/* If the function reaches this point, something is fishy.  Kill their session */
		session_destroy();
		exitWithErrorPage('Invalid session, please log in again.');
	}
	
	/* See if the user logged in is a moderator of a specified board */
	function CurrentUserIsModeratorOfBoard($board, $username) {
		global $tc_db, $smarty, $tpl_page;
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type`, `boards` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $username . "' LIMIT 1");
		if (count($results) > 0) {
			foreach ($results as $line) {
				if ($line['boards'] == 'allboards') {
					return true;
				} else {
					if ($line['type'] == '1') {
						return true;
					} else {
						$array_boards = explode('|', $line['boards']);
						if (in_array($board, $array_boards)) {
							return true;
						} else {
							return false;
						}
					}
				}
			}
		} else {
			return false;
		}
	}
	
	/* Generate a list of boards a moderator controls */
	function BoardList($username) {
		global $tc_db, $smarty, $tpl_page;
		
		$staff_boardsmoderated = array();
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `boards` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $username . "' LIMIT 1");
		if ($this->CurrentUserIsAdministrator() || $results[0][0] == 'allboards') {
			$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
			foreach ($resultsboard as $lineboard) {
				$staff_boardsmoderated = array_merge($staff_boardsmoderated, array($lineboard['name']));
			}
		} else {
			if ($results[0][0] != '') {
				foreach ($results as $line) {
					$array_boards = explode('|', $line['boards']);
				}
				foreach ($array_boards as $this_board_name) {
					$staff_boardsmoderated = array_merge($staff_boardsmoderated, array($this_board_name));
				}
			}
		}
		
		return $staff_boardsmoderated;
	}
	
	/* Generate a list of boards in query format */
	function sqlboardlist() {
		global $tc_db, $smarty, $tpl_page;
		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		$sqlboards = '';
		foreach ($results as $line) {
			$sqlboards .= 'posts_' . $line['name'] . ', ';
		}
		
		return substr($sqlboards, 0, -2);
	}
	
	/* Generate a dropdown box from a supplied array of boards */
	function MakeBoardListDropdown($name, $boards, $selected = null) {
		$output = '<select name="' . $name . '"><option value="">Select a Board</option>';
		if ($boards != '') {
			foreach ($boards as $board) {
				$output .= '<option value="' . $board . '" ' . ($selected == $board ? 'selected' : '') . '>/' . $board . '/</option>';
			}
		}
		$output .= '</select>';
		
		return $output;
	}
	
	/* Generate a series of checkboxes from a supplied array of boards */
	function MakeBoardListCheckboxes($prefix, $boards, $checked = []) {
		$output = '';
		
		if ($boards != '') {
			foreach ($boards as $board) {
				$checked_attribute = in_array($board, $checked) ? ' checked' : '';
				$output .= '<label for="' . $prefix . $board . '">' . $board . '</label><input type="checkbox" name="' . $prefix . $board . '"' . $checked_attribute . '> ';
			}
		}
		
		return $output;
	}
		
	/* Delete files without their md5 stored in the database */
	function delunusedimages($verbose = false) {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards`");
		foreach ($resultsboard as $lineboard) {
			if ($verbose) {
				$tpl_page .= '<b>Looking for unused images in /' . $lineboard['name'] . '/</b><br>';
			}
			$filemd5list = array();
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `filemd5` FROM `" . KU_DBPREFIX . "posts_" . $lineboard['name'] . "` WHERE `IS_DELETED` = 0 AND `filename` != '' AND `filename` != 'removed' AND `filemd5` != ''");
			foreach ($results as $line) {
				$filemd5list[] = $line['filemd5'];
			}
			$dir = './' . $lineboard['name'] . '/src';
			$files = glob("$dir/{*.jpg, *.png, *.gif, *.swf}", GLOB_BRACE);
			if (is_array($files)) {
				foreach ($files as $file) {
					if (in_array(md5_file(KU_BOARDSDIR . $lineboard['name'] . '/src/' . basename($file)), $filemd5list) == false) {
						if (time() - filemtime(KU_BOARDSDIR . $lineboard['name'] . '/src/' . basename($file)) > 120) {
							if ($verbose == true) {
								$tpl_page .= 'A live record for ' . $file . ' was not found;  the file has been removed.<br>';
							}
							unlink(KU_BOARDSDIR . $lineboard['name'] . '/src/' . basename($file));
							@unlink(KU_BOARDSDIR . $lineboard['name'] . '/thumb/' . substr(basename($file), 0, -4) . 's' . substr(basename($file), strlen(basename($file)) - 4));
							@unlink(KU_BOARDSDIR . $lineboard['name'] . '/thumb/' . substr(basename($file), 0, -4) . 'c' . substr(basename($file), strlen(basename($file)) - 4));
						}
					}
				}
			}
		}
		
		return true;
	}
	
	/* Delete replies currently not marked as deleted who belong to a thread which is marked as deleted */
	function delorphanreplies($verbose = false) {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		
		$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards`");
		foreach ($resultsboard as $lineboard) {
			if ($verbose) {
				$tpl_page .= '<b>Looking for orphans in /' . $lineboard['name'] . '/</b><br>';
			}
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `parentid` FROM `" . KU_DBPREFIX . "posts_" . $lineboard['name'] . "` WHERE `parentid` != '0' AND `IS_DELETED` = 0");
			foreach ($results as $line) {
				$exists_rows = $tc_db->GetAll("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "posts_" . $lineboard['name'] . "` WHERE `id` = '" . $line['parentid'] . "' AND `IS_DELETED` = 0", 1);
				if ($exists_rows[0] == 0) {
					$post_class = new Post($line['id'], $lineboard['name']);
					$post_class->Delete;
					
					if ($verbose) {
						$tpl_page .= 'Reply #' . $line['id'] . '\'s thread (#' . $line['parentid'] . ') does not exist!  It has been deleted.<br>';
					}
				}
			}
		}
		
		return true;
	}
	function delspecorphanreplies($board) {
	global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `parentid` FROM `" . KU_DBPREFIX . "posts_" . $board . "` WHERE `parentid` != '0' AND `IS_DELETED` = 0");
		foreach ($results as $line) {
			$exists_rows = $tc_db->GetAll("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "posts_" . $board . "` WHERE `id` = '" . $line['parentid'] . "' AND `IS_DELETED` = 0", 1);
			if ($exists_rows[0] == 0) {
				$post_class = new Post($line['id'], $lineboard['name']);
				$post_class->Delete;
			}
		}		
		return true;
	}
	function delspecunusedimages($board) {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$filemd5list = array();
		$verbose = true;
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `filemd5` FROM `" . KU_DBPREFIX . "posts_" . $board . "` WHERE `IS_DELETED` = 0 AND `filename` != '' AND `filename` != 'removed' AND `filemd5` != ''");
			foreach ($results as $line) {
				$filemd5list[] = $line['filemd5'];
			}
			$dir = './' . $board . '/src';
			$files = glob("$dir/{*.jpg, *.png, *.gif, *.swf}", GLOB_BRACE);
			if (is_array($files)) {
				foreach ($files as $file) {
					if (in_array(md5_file(KU_BOARDSDIR . $board . '/src/' . basename($file)), $filemd5list) == false) {
						if (time() - filemtime(KU_BOARDSDIR . $board . '/src/' . basename($file)) > 120) {
							if ($verbose == true) {
								$tpl_page .= 'A live record for ' . $file . ' was not found;  the file has been removed.<br>';
							}
							unlink(KU_BOARDSDIR . $board . '/src/' . basename($file));
							@unlink(KU_BOARDSDIR . $board . '/thumb/' . substr(basename($file), 0, -4) . 's' . substr(basename($file), strlen(basename($file)) - 4));
							@unlink(KU_BOARDSDIR . $board . '/thumb/' . substr(basename($file), 0, -4) . 'c' . substr(basename($file), strlen(basename($file)) - 4));
						}
					}
				}
			}
		return true;		
	}

	// Merge threads
	function mergethreads() {
		global $tc_db, $smarty, $tpl_page;
		$this->AdministratorsOnly();
		$tpl_page .= '<h2>' . ucwords(_gettext('Merge threads')) . "</h2><br/>";

		//Logs and logic go here
		if (isset($_POST['from_id']) && isset($_POST['to_id']) && isset($_POST['board'])) {
			echo "Stage 1: Validating form params...<br/>";

			if(!is_numeric($_POST['from_id'])) {
				exitWithErrorPage('Invalid FROM thread ID');
			}
			if(!is_numeric($_POST['to_id'])) {
				exitWithErrorPage('Invalid TO thread ID');
			}
			if(empty($_POST['board'])) {
				exitWithErrorPage('Please select a board');
			}

			echo "Stage 2: Checking threads...<br/>";
			$board = mysqli_real_escape_string($tc_db->link, $_POST['board']);
			$from_id = mysqli_real_escape_string($tc_db->link, $_POST['from_id']);
			$to_id = mysqli_real_escape_string($tc_db->link, $_POST['to_id']);

			$from_results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `filename`, `filetype` FROM " . KU_DBPREFIX . "posts_" . $board . " WHERE `id` = '" . $from_id . "' AND `parentid` = 0 AND `IS_DELETED` = 0 LIMIT 1");
			$to_results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `filename`, `filetype` FROM " . KU_DBPREFIX . "posts_" . $board . " WHERE `id` = '" . $to_id . "' AND `parentid` = 0 AND `IS_DELETED` = 0 LIMIT 1");
			if(count($from_results) <= 0) {
				exitWithErrorPage('Invalid FROM thread ID');
			}
			if(count($to_results) <= 0) {
				exitWithErrorPage('Invalid TO thread ID');
			}

			echo "Stage 3: Merging... <br/>";
			$tc_db->Execute("START TRANSACTION");

			//Update link hrefs
			echo "UPDATE " . KU_DBPREFIX . "posts_" . $board . " SET `message` = REPLACE(message, 'href=\\\\\"/" . $board . "/res/" . $from_id . ".html#', 'href=\\\\\"/" . $board . "/res/" . $to_id . ".html#') WHERE `parentid` = '" . $from_id . "' OR `id` = '" . $from_id . "'";
			echo "<br/>";
			$tc_db->Execute("UPDATE " . KU_DBPREFIX . "posts_" . $board . " SET `message` = REPLACE(message, 'href=\\\\\"/" . $board . "/res/" . $from_id . ".html#', 'href=\\\\\"/" . $board . "/res/" . $to_id . ".html#') WHERE `parentid` = '" . $from_id . "' OR `id` = '" . $from_id . "'");

			//Update link preview params
			echo "UPDATE " . KU_DBPREFIX . "posts_" . $board . " SET `message` = REPLACE(message, 'class=\\\\\"ref|" . $board . "|" . $from_id . "|', 'class=\\\\\"ref|" . $board . "|" . $to_id . "|') WHERE `parentid` = '" . $from_id . "' OR `id` = '" . $from_id . "'";
			echo "<br/>";
			$tc_db->Execute("UPDATE " . KU_DBPREFIX . "posts_" . $board . " SET `message` = REPLACE(message, 'class=\\\\\"ref|" . $board . "|" . $from_id . "|', 'class=\\\\\"ref|" . $board . "|" . $to_id . "|') WHERE `parentid` = '" . $from_id . "' OR `id` = '" . $from_id . "'");

			//Update parents
			echo "UPDATE " . KU_DBPREFIX . "posts_" . $board . " SET `parentid` = '" . $to_id . "' WHERE `parentid` = '" . $from_id . "' OR `id` = '" . $from_id . "'";
			echo "<br/>";
			$tc_db->Execute("UPDATE " . KU_DBPREFIX . "posts_" . $board . " SET `parentid` = '" . $to_id . "' WHERE `parentid` = '" . $from_id . "' OR `id` = '" . $from_id . "'");

			$tc_db->Execute("COMMIT");

			echo "Stage 3.0+1.0: Rebuild of post-merge board...<br/><br/>";
			$board_class = new Board($board);
			$board_class->RegenerateAll();

			$tpl_page .= _gettext('Merge complete.') . '<br/><hr/><br/>';
		}

		//Form output here
		$tpl_page .= '<form action="?action=mergethreads" method="post">

		<label for="from_id">' . _gettext('From thread') . ':</label>
		<input type="text" name="from_id"/>
		<br/>

		<label for="to_id">' . _gettext('To thread') . ':</label>
		<input type="text" name="to_id"/>
		<br/>

		<label for="board">' . _gettext('Board') . ':</label>' .
		$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .
		'<br/>

		<input type="submit" value="' . _gettext('Merge threads') . '"/>

		</form>';
	}
}
?>
