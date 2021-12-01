<?php

class Warnings {
	function WarningCheck($ip, $board) {
		global $tc_db;
		$results = $tc_db->GetAll("SELECT * FROM `".KU_DBPREFIX."warnings` WHERE ( `ipmd5` = '" . md5($ip) . "' AND viewed = 0 )");
		foreach($results AS $line) {
			if ($line['global']==1 || in_array($board, explode('|', $line['boards']))) {
				echo $this->DisplayWarningMessage($line['text'], $line['at']);
				$tc_db->Execute("UPDATE `".KU_DBPREFIX."warnings` SET viewed = 1 WHERE id = " . $line['id']);
				die();
			}
		}

		return true;
	}

	function DisplayWarningMessage($text, $at) {
		/* Set a cookie with the users current IP address in case they use a proxy to attempt to make another post */
		setcookie('tc_previousip', $_SERVER['REMOTE_ADDR'], (time() + 604800), KU_BOARDSFOLDER);

		require_once KU_ROOTDIR . 'lib/smarty.php';

		$smarty->assign('thewarningwasissuedon', _gettext('The warning was issued on'));
		$smarty->assign('youripaddressis', _gettext('Your IP address is'));
		$smarty->assign('youhavebeenissuedawarning', _gettext('You have been issued a warning'));
		$smarty->assign('title', _gettext('WARNING') . '!');
		$smarty->assign('ku_slogan', KU_SLOGAN);
		$smarty->assign('text', $text);
		$smarty->assign('at', date("F j, Y, g:i a", $at));
		$smarty->assign('ip', $_SERVER['REMOTE_ADDR']);
		$jquery = '<script src="' . getCWebPath() . 'lib/javascript/jquery-3.3.1.min.js"></script>' . "\n";
		$style_cookie = '<script>' . "\n" .
			'	var ku_boardspath = \'' . KU_BOARDSPATH . '\';' . "\n" .
			'	var ku_cgipath = \'' . KU_CGIPATH . '\'' . "\n" .
			'	var style_cookie="kustyle";' . "\n" .
			'	var style_cookie_txt = "";' . "\n" .
			'</script>' . "\n";

		$smarty->assign('head', $jquery . $style_cookie . printStylesheetsSite());

		return $smarty->fetch('warning.tpl');
	}
}

?>
