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
 * News display, which is the first page shown when a user visits a chan's index
 *
 * Any news added by an administrator in the manage panel will show here, with
 * the newest entry on the top.
 * 
 * @package kusaba  
 */   

/** 
 * Require the configuration file
 */ 
require 'config.php';
require KU_ROOTDIR . 'inc/functions.php';
require_once KU_ROOTDIR . 'lib/smarty.php';

if (!isset($_GET['p'])) {
	$_GET['p'] = '';
}

$smarty->assign('name', KU_NAME);
$smarty->assign('css', printStylesheetsSite(KU_DEFAULTMENUSTYLE, false));
if (KU_SLOGAN != '') {
	$smarty->assign('slogan', '<h3>' . KU_SLOGAN . '</h3>' . "\n");
} else {
	$smarty->assign('slogan', '');
}
$smarty->assign('favicon', getCWebPath() . 'favicon.ico');

// {{{ Link bar (news, faq, rules)
$linkbar = ($_GET['p']=='') ? _gettext('News') : '<a href="news.php">' . _gettext('News') . '</a>';
$linkbar .= ' | ';
if (isset($kusabaorg)) {
	$linkbar .= '<a href="download.html">Download</a> | ';
}
$linkbar .= ($_GET['p']=='faq') ? _gettext('FAQ') : '<a href="news.php?p=faq">' . _gettext('FAQ') . '</a>';
$linkbar .= ' | ';
$linkbar .= ($_GET['p']=='rules') ? _gettext('Rules') : '<a href="news.php?p=rules">' . _gettext('Rules') . '</a>';
$linkbar .= ' | ';
$linkbar .= ($_GET['p']=='rules.en') ? _gettext('English') : '<a href="news.php?p=rules.en">' . _gettext('English') . '</a>';
$linkbar .= ' | ';
$linkbar .= ($_GET['p']=='radio') ? _gettext('Радио') : '<a href="news.php?p=radio">' . _gettext('Радио') . '</a><br/>';

/* Don't worry about this, it only applies to my personal installation of kusaba */
if (isset($kusabaorg)) {
	$linkbar .= '<br><!-- Begin: AdBrite -->
	<script>
	   var AdBrite_Title_Color = \'CC1105\';
	   var AdBrite_Text_Color = \'800000\';
	   var AdBrite_Background_Color = \'FFFFEE\';
	   var AdBrite_Border_Color = \'FFFFEE\';
	</script>
	<span style="white-space:nowrap;"><script src="http://ads.adbrite.com/mb/text_group.php?sid=568716&amp;zs=3732385f3930"></script><!--
	--><a target="_top" href="http://www.adbrite.com/mb/commerce/purchase_form.php?opid=568716&amp;afsid=1"><img src="http://files.adbrite.com/mb/images/adbrite-your-ad-here-leaderboard.gif" style="background-color:#FFFFEE;border:none;padding:0;margin:0;" alt="Your Ad Here" width="14" height="90" border="0"></a></span>
	<!-- End: AdBrite --><br>';
}

$smarty->assign('linkbar', $linkbar);
// }}}

// {{{ Main content
if ($_GET['p']=='faq') {
	$content = file_get_contents(KU_ROOTDIR . 'inc/pages/faq.html');
} else if ($_GET['p']=='rules') {
	$content = file_get_contents(KU_ROOTDIR . 'inc/pages/rules.html');
} else if ($_GET['p']=='rules.en') {
	$content = file_get_contents(KU_ROOTDIR . 'inc/pages/rules.en.html');
} else if ($_GET['p']=='radio.unix') {
	$content = file_get_contents(KU_ROOTDIR . 'inc/pages/radio.unix.html');
} else if ($_GET['p']=='radio') {
	$content = file_get_contents(KU_ROOTDIR . 'inc/pages/radio.html');
} else {
	$content = '<hr/><img src="410.png" width="300px" height="300px" alt="Logo" style="float: left; padding-left:1.6em;"/><div class="telega" style="padding-left:1.6em; margin-top: 3em;">
	Добро пожаловать на 410chan! Наш сайт является имиджбордом — одним из специфических сетевых форумов, где нет принудительной регистрации, а к сообщениям
	можно легко прикреплять графические файлы. Тематика сайта почти ничем не ограничена: те вопросы, для которых не выделено отдельного тематического раздела, можно обсудить на доске <a href="/b">/b/</a>.<br/><br/>
	Поскольку сайт построен по принципу наполнения самими пользователями, то, как он будет выглядеть, всецело зависит от них. Наше сообщество стремится к достижению высокой культуры общения.
	Мы надеемся на то, что пассажиры нашего Автобуса будут вежливыми и интересными собеседниками и предпочтут содержательное общение бессмысленным разборкам.<br/><br/>
	И помните: <a href="http://noobtype.ru/wiki/Автобус_410" target="_blank">Автобус следует в ад</a>.
	</div><hr style="clear: both;"/>'; /* Вельми кривая вёрстка, я знаю.*/
	$entries = 0;
	/* Get all of the news entries, ordered with the newest one placed on top */
	$results = $tc_db->GetAll("SELECT * FROM `".KU_DBPREFIX."news` ORDER BY `postedat` DESC");
	foreach($results AS $line) {
		$entries++;
		$content .= '<div class="content">' . "\n" .
		'<h2><span class="newssub">'.stripslashes($line['subject']).' by ';
		/* If the message had an email attached to it, add the proper html to link to it */
		if ($line['postedemail']!="") {
			$content .= '<a href="mailto:'.stripslashes($line['postedemail']).'">';
		}
		$content .= stripslashes($line['postedby']);
		if ($line['postedemail']!="") {
			$content .= '</a>';
		}
		$content .= ' - '.date("n/j/y @ g:iA T", $line['postedat']);
		$content .= '</span><span class="permalink"><a href="#' . $line['id'] . '" name="' . $line['id'] . '" title="permalink">#</a></span></h2>
		'.stripslashes($line['message']).'</div><br>';
		$content .= 'Memory: ' . memory_get_usage();
	}
}
$smarty->assign('content', $content);
// }}}

$smarty->display('news.tpl');
?>
