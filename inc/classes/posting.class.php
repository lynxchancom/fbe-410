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
 * Posting class
 * +------------------------------------------------------------------------------+
 */
class Posting {
	
	function CheckOekaki() {
		global $board_class;
		
		/* If oekaki seems to be in the url... */
		if (isset($_POST['oekaki'])) {
			/* See if it checks out and is a valid oekaki id */
			if ($_POST['oekaki'] != '' && is_file(KU_CGIDIR . 'kusabaoek/' . $_POST['oekaki'] . '.png') && $board_class->board_type == '2') {
				/* Set the variable to tell the script it is handling an oekaki posting, and the oekaki file which will be posted */
				return KU_CGIDIR . 'kusabaoek/' . $_POST['oekaki'] . '.png';
			}
		}
		
		return '';
	}
	
	function CheckReplyTime() {
		global $tc_db, $board_class;
		
		/* Get the timestamp of the last time a reply was made by this IP address */
		$results = $tc_db->GetAll("SELECT MAX(postedat) FROM `" . KU_DBPREFIX . "posts_" . $board_class->board_dir . "` WHERE `parentid` != 0 AND `ipmd5` = '" . md5($_SERVER['REMOTE_ADDR']) . "' AND `postedat` > " . (time() - KU_REPLYDELAY));
		/* If they have posted before and it was recorded... */
		if (isset($result)) {
		/* If the time was shorter than the minimum time distance */
			if (time() - $line['postedat'] <= KU_REPLYDELAY) {
				exitWithErrorPage(_gettext('Please wait a moment before posting again.'), _gettext('You are currently posting faster than the configured minimum post delay allows.'));
			}
		}
	}
	
	function CheckNewThreadTime() {
		global $tc_db, $board_class;
		
		/* Get the timestamp of the last time a new thread was made by this IP address */
		$result = $tc_db->GetOne("SELECT MAX(postedat) FROM `" . KU_DBPREFIX . "posts_" . $board_class->board_dir . "` WHERE `parentid` = 0 AND `ipmd5` = '" . md5($_SERVER['REMOTE_ADDR']) . "' AND `postedat` > " . (time() - KU_NEWTHREADDELAY));
		/* If they have posted before and it was recorded... */
		if (isset($result)) {
			/* If the time was shorter than the minimum time distance */
			if (time() - $result <= KU_NEWTHREADDELAY) {
				exitWithErrorPage(_gettext('Please wait a moment before posting again.'), _gettext('You are currently posting faster than the configured minimum post delay allows.'));
			}
		}
	}
	
	function UTF8Strings() {
		if (function_exists('mb_convert_encoding') && function_exists('mb_check_encoding')) {
			if (isset($_POST['name']) && !mb_check_encoding($_POST['name'], 'UTF-8')) {
				$_POST['name'] = mb_convert_encoding($_POST['name'], 'UTF-8');
			}
			if (isset($_POST['em']) && !mb_check_encoding($_POST['em'], 'UTF-8')) {
				$_POST['em'] = mb_convert_encoding($_POST['em'], 'UTF-8');
			}
			if (isset($_POST['subject']) && !mb_check_encoding($_POST['subject'], 'UTF-8')) {
				$_POST['subject'] = mb_convert_encoding($_POST['subject'], 'UTF-8');
			}
			if (isset($_POST['message']) && !mb_check_encoding($_POST['message'], 'UTF-8')) {
				$_POST['message'] = mb_convert_encoding($_POST['message'], 'UTF-8');
			}
		}
	}
	
	function CheckValidPost($is_oekaki) {
		global $tc_db, $board_class;
		
		if (
			( /* A message is set, or an image was provided */
				isset($_POST['message']) ||
				isset($_FILES['imagefile'])
			) || /* It is a validated oekaki posting */
			$is_oekaki ||
			( /* It is a text board, meaning only a message is required */
				$board_class->board_type == '1' &&
				isset($_POST['message'])
			) || (
				( /* It has embedding allowed */
						$board_class->board_uploadtype == '1' ||
						$board_class->board_uploadtype == '2'
				) && ( /* An embed ID was provided, or no file was checked and no ID was supplied */
						isset($_POST['embed']) ||
						(
							$board_class->board_uploadtype == '2' &&
							!isset($_FILES['imagefile']) &&
							isset($_POST['nofile']) &&
							$board_class->board_enablenofile == true
						)
				)
			)
		) {
			return true;
		} else {
			return false;
		}
	}
	
	function CheckMessageLength() {
		global $board_class;
		
		/* If the length of the message is greater than the board's maximum message length... */
		if (strlen($_POST['message']) > $board_class->board_messagelength) {
			/* Kill the script, stopping the posting process */
			exitWithErrorPage(printf(_gettext('Sorry, your message is too long.  Message length: %d, maximum allowed length: %d'), strlen($_POST['message']), $board_class->board_messagelength));
		}
	}
	
	function CheckCaptcha() {
		global $board_class;
		
		/* If the board has captcha's enabled... */
		if ($board_class->board_enablecaptcha == 1) {
			/* Check if they entered the correct code.  If not... */
			if ($_SESSION['security_code'] != strtolower($_POST['captcha']) || empty($_SESSION['security_code'])) {
				/* Kill the script, stopping the posting process */
				exitWithErrorPage(_gettext('Incorrect captcha entered.'));
			}
		}
	}
	function ClearFaptchaAttempts() {
		global $tc_db, $smarty, $tpl_page, $board_class;
		$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "faptcha_attempts` WHERE `timestamp` < '" . (time() - 1200) . "'");
	}
	function CheckFaptcha() {
		global $board_class, $tc_db, $bans_class;
$fh = fopen(".htlog.txt", "a+");
fwrite($fh, "\n");
fwrite($fh, date(DATE_RSS));
		/* check for adaptive captcha settings enabled on this board */
		$adaptive_boards = unserialize(KU_ADAPTIVE_CAPTCHA);
		$adaptive_string = sprintf("%s:%s:%s", KU_SECRET, $board_class->board_dir, $_SERVER['REMOTE_ADDR']);
		$adaptive_hash = sha1($adaptive_string);
fwrite($fh, $adaptive_string . " ". $adaptive_hash . "\n");
		$userfile = sprintf("%s/%s", KU_SESSION, $adaptive_hash);
		$cookie = 0;
		if(array_key_exists($board_class->board_dir, $adaptive_boards) && $adaptive_boards[$board_class->board_dir] > 0)
		{ // adaptive captcha enabled on board 
			$cookie = 1;
			fwrite($fh, "enabled $board_class->board_dir $userfile $cookie\n");
			$_SESSION[$board_class->board_dir] = $adaptive_hash;
			if(isset($_COOKIE[$board_class->board_dir]) && $_COOKIE[$board_class->board_dir] == $adaptive_hash)
			{ // user already entered captcha in previous posts and was not forced to enter captcha
				if(file_exists($userfile))
				{
					// so probably time() gives GMT now? But anyway shouldn't be daylight saving hours?
					$localtime = time();
					if(($localtime - filemtime($userfile)) < KU_ADAPCHA_TIMEOUT)
					{
//						unset($_SESSION['faptcha_type']);
						fwrite($fh, "in time: ". $localtime. " - " . filemtime($userfile) . "\n");
//						$_SESSION['verified_user'][$board_class->board_dir] = time();
						unset($_SESSION['faptcha_type']);
						setcookie($board_class->board_dir, $adaptive_hash, $localtime + KU_ADAPCHA_TIMEOUT);
						touch($userfile);
						return;
					}
					else 
					{
						fwrite($fh, "expired\n");
						// remove adaptive cookie
						setcookie($board_class->board_dir, "", time() - 3600);
						unset($_SESSION['faptcha_type']);
						unlink($userfile);
						exitWithErrorPage('Session expired.');
					}
				}
				else
				{
					fwrite($fh, "tricks\n");
					exitWithErrorPage('__DONTTRICK');
				}
			}
		}

		/* if user logged in (Manage page), no captcha checked */
		if(isset($_SESSION['manageusername']) && mb_strlen($_SESSION['manageusername']) > 0)
		{
			return;
		}

		/* If the board has captcha's enabled... */
		if ($board_class->board_enablefaptcha == 1) {
			if ($board_class->board_enablefaptcha == 1 && !isset($_SESSION['faptcha_type']) || empty($_SESSION['faptcha_type'])) 
			{
				exitWithErrorPage('Old faptcha is old. Please renew.');
			}
			if (!isset($_POST['faptcha']) || empty($_POST['faptcha'])) {
				exitWithErrorPage('Please enter a faptcha.');
			}
			$faptchainput = $_POST['faptcha'];
			$faptchainput = str_replace(' ', '_', $faptchainput);
			if (!array_key_exists(mb_strtolower($faptchainput, "UTF-8"), $_SESSION['faptcha_type'])) 
			{ // wrong captcha entered
				$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "faptcha_attempts` ( `ip` , `timestamp` ) VALUES ( '" . $_SERVER['REMOTE_ADDR'] . "' , '" . time() . "' )");
				exitWithErrorPage(_gettext('Incorrect faptcha entered.'));
			}
			unset($_SESSION['faptcha_type']);
			if($cookie > 0) {
fwrite($fh, "setcookie\n");
				setcookie($board_class->board_dir, $adaptive_hash, time() + KU_ADAPCHA_TIMEOUT);
				touch($userfile);
			}
fwrite($fh, "good\n");
			return true;
		}
	}
	
	function CheckBannedHash() {
		global $tc_db, $board_class, $bans_class;
		
		/* Banned file hash check */
		if (isset($_FILES['imagefile'])) {
			if ($_FILES['imagefile']['name'] != '') {
				$results = $tc_db->GetAll("SELECT `bantime` , `description` FROM `" . KU_DBPREFIX . "bannedhashes` WHERE `md5` = '" . mysqli_real_escape_string($tc_db->link, md5_file($_FILES['imagefile']['tmp_name'])) . "' LIMIT 1");
				if (count($results) > 0) {
						$bans_class->BanUser($_SERVER['REMOTE_ADDR'], 'SERVER', '1', $results[0]['bantime'], '', 'Posting a banned file.<br>' . $results[0]['description'], 0, 0, 1);
						$bans_class->BanCheck($_SERVER['REMOTE_ADDR'], $board_class->board_dir);
						die();
				}
			}
		}
	}
	
	function CheckIsReply() {
		global $tc_db, $board_class;
		
		/* If it appears this is a reply to a thread, and not a new thread... */
		if (isset($_POST['replythread'])) {
			if ($_POST['replythread'] != '0') {
				/* Check if the thread id supplied really exists */
				$results = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts_" . $board_class->board_dir . "` WHERE `IS_DELETED` = '0' AND `id` = '" . mysqli_real_escape_string($tc_db->link, $_POST['replythread']) . "' AND `parentid` = '0' LIMIT 1");
				/* If it does... */
				if ($results > 0) {
					return true;
				/* If it doesn't... */
				} else {
					/* Kill the script, stopping the posting process */
					exitWithErrorPage(_gettext('Invalid thread ID.'), _gettext('That thread may have been recently deleted.'));
				}
			}
		}
		
		return false;
	}
	
	function CheckNotDuplicateSubject($subject) {
		global $tc_db, $board_class;
		
		$result = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts_" . $board_class->board_dir . "` WHERE `IS_DELETED` = '0' AND `subject` = '" . mysqli_real_escape_string($tc_db->link, $subject) . "' AND `parentid` = '0'");
		if ($result > 0) {
			exitWithErrorPage(_gettext('Duplicate thread subject'), _gettext('Text boards may have only one thread with a unique subject.  Please pick another.'));
		}
	}
	
	function GetThreadInfo($id) {
		global $tc_db, $board_class;
		
		/* Check if the thread id supplied really exists and if it is locked */
		$results = $tc_db->GetAll("SELECT `id`,`locked` FROM `" . KU_DBPREFIX . "posts_" . $board_class->board_dir . "` WHERE `IS_DELETED` = '0' AND `id` = '" . mysqli_real_escape_string($tc_db->link, $id) . "' AND `parentid` = '0'");
		/* If it does... */
		if (count($results) > 0) {
			/* Get the thread's info */
			$thread_locked = $results[0]['locked'];
			$thread_replyto = $results[0]['id'];
			/* Get the number of replies */
			$result = $tc_db->GetOne("SELECT COUNT(id) FROM `" . KU_DBPREFIX . "posts_" . $board_class->board_dir . "` WHERE `IS_DELETED` = '0' AND `parentid` = '" . mysqli_real_escape_string($tc_db->link, $id) . "'");
			$thread_replies = $result;
			
			return array($thread_replies, $thread_locked, $thread_replyto);
		} else {
			/* If it doesn't, kill the script, stopping the posting process */
			exitWithErrorPage(_gettext('Invalid thread ID.'), _gettext('That thread may have been recently deleted.'));
		}
	}
	
	function GetFields() {
		/* Fetch and process the name, email, and subject fields from the post data */
		$post_name = isset($_POST['name']) ? htmlspecialchars(stripslashes($_POST['name']), ENT_QUOTES) : '';
//		$post_email = isset($_POST['em']) ? str_replace('"', '', strip_tags(stripslashes($_POST['em']))) : '';
		$post_email = '';
		/* If the user used a software function, don't store it in the database */
		if ($post_email == 'return' || $post_email == 'noko') $post_email = '';
		$post_subject = isset($_POST['subject']) ? htmlspecialchars(stripslashes($_POST['subject']), ENT_QUOTES) : '';
		
		return array($post_name, $post_email, $post_subject);
	}
	
	function CheckBoardAccess() {
		if ($this->CurrentUserIsAdministrator()) {
			return 9999;
		}
		global $tc_db, $smarty, $tpl_page;		
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `access` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
		foreach ($results as $line) {
			return $line['access'];
		}
	}
	function GetUserAuthority() {
		global $tc_db, $board_class;
		
		$user_authority = 0;
		$flags = '';
		
		if (isset($_POST['modpassword'])) {			
			$results = $tc_db->GetAll("SELECT `type`, `boards`, `access` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . md5_decrypt($_POST['modpassword'], KU_RANDOMSEED) . "' LIMIT 1");
			
			if (count($results) > 0) {
				if ($results[0][0] == 1) {
					$user_authority = 1; /* admin */
					return array($user_authority, $flags);
				} 
				foreach($results as $line) {
					if($line['access'] >= 9) {
						$user_authority = 4; /*super mod */
						return array($user_authority, $flags);
					}
				}
				if ($results[0][0] == 2 && in_array($board_class->board_dir, explode('|', $results[0][1])) && $results['access'] < 9) {
					$user_authority = 2; /* mod */
					return array($user_authority, $flags);
				}
				if ($results[0][0] == 2 && $results[0][1] == 'allboards') {
					$user_authority = 2;
					return array($user_authority, $flags);
				}
				if ($results[0][0] == 3) {
					$user_authority = 3; /* VIP */
					return array($user_authority, $flags);
				}
				if ($user_authority < 4) { /* set posting flags for mods and admins */
					if (isset($_POST['displaystaffstatus'])) $flags .= 'D';
					if (isset($_POST['lockonpost'])) $flags .= 'L';
					if (isset($_POST['stickyonpost'])) $flags .= 'S';
					if (isset($_POST['rawhtml'])) $flags .= 'RH';
					if (isset($_POST['usestaffname'])) $flags .= 'N';
				}
			}
		}		
		return array($user_authority, $flags);
	}
	
	function CheckBadUnicode($post_name, $post_email, $post_subject, $post_message) {
		/* Check for bad characters which can cause the page to deform (right-to-left markers, etc) */
		$bad_ords = array(8235, 8238);
		
		$ords_name = unistr_to_ords($post_name);
		$ords_email = unistr_to_ords($post_email);
		$ords_subject = unistr_to_ords($post_subject);
		$ords_message = unistr_to_ords($post_message);
		$ords_filename = isset($_FILES['imagefile']) ? unistr_to_ords($_FILES['imagefile']['name']) : '';
		foreach ($bad_ords as $bad_ord) {
			if ($ords_name != '') {
				if (in_array($bad_ord, $ords_name)) { 
					exitWithErrorPage(_gettext('Your post contains one or more illegal characters.'));
				}
			}
			if ($ords_email != '') {
				if (in_array($bad_ord, $ords_email)) {
					exitWithErrorPage(_gettext('Your post contains one or more illegal characters.'));
				}
			}
			if ($ords_subject != '') {
				if (in_array($bad_ord, $ords_subject)) {
					exitWithErrorPage(_gettext('Your post contains one or more illegal characters.'));
				}
			}
			if ($ords_message != '') {
				if (in_array($bad_ord, $ords_message)) {
					exitWithErrorPage(_gettext('Your post contains one or more illegal characters.'));
				}
			}
			if ($ords_filename != '') {
				if (in_array($bad_ord, $ords_filename)) {
					exitWithErrorPage(_gettext('Your post contains one or more illegal characters.'));
				}
			}
		}
	}
	
	function GetPostTag() {
		global $board_class;
		
		/* Check for and parse tags if one was provided, and they are enabled */
		$post_tag = '';
		$tags = unserialize(KU_TAGS);
		if ($board_class->board_type == 3 && $tags != '' && isset($_POST['tag'])) {
			if ($_POST['tag'] != '') {
				$validtag = false;
				while (list($tag, $tag_abbr) = each($tags)) {
					if ($tag_abbr == $_POST['tag']) {
						$validtag = true;
					}
				}
				if ($validtag) {
					$post_tag = $_POST['tag'];
				}
			}
		}
		
		return $post_tag;
	}
	
	function CheckBlacklistedText() {
		global $bans_class;
		$badlinks = array_map('rtrim', file(KU_ROOTDIR . 'spam.txt'));

		foreach ($badlinks as $badlink) {
			if (strpos($_POST['message'], $badlink) !== false) {
				/* They included a blacklisted link in their post.  Ban them for an hour */
				$bans_class->BanUser($_SERVER['REMOTE_ADDR'], 'SERVER', 1, 3600, '', _gettext('Posting a blacklisted link.') . ' (' . $badlink . ')',0,0,1,'');
				exitWithErrorPage('Blacklisted link (' . $badlink . ') detected.');
			}
		}
	}
}

?>
