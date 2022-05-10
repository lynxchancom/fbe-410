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
require_once KU_ROOTDIR . 'inc/func/pages.php';
require_once KU_ROOTDIR . 'inc/classes/topmenu.class.php';

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

$subpages = [[
	'name' => 'FAQ', 'file' => 'faq.html', 'hidden' => 0
], [
	'name' => 'Rules', 'file' => 'rules.html', 'hidden' => 0
], [
	'name' => 'English', 'file' => 'rules.en.html', 'hidden' => 0
], [
	'name' => 'Радио', 'file' => 'radio.html', 'hidden' => 0
], [
	'name' => 'radio.unix', 'file' => 'radio.unix.html', 'hidden' => 1
], [
	'name' => 'radio.windows', 'file' => 'radio.windows.html', 'hidden' => 1
]];

$main_subpages_table_exists = $tc_db->GetOne("SELECT COUNT(*)
FROM information_schema.tables
WHERE table_schema = '" . KU_DBDATABASE. "'
	AND table_name = 'main_subpages'
");
if ($main_subpages_table_exists) {
	$db_subpages = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "main_subpages` ORDER BY `index`, `id`");
	if (count($db_subpages)) {
		$subpages = $db_subpages;
	}
}


// {{{ Link bar (news, faq, rules)
$linkbar = ($_GET['p']=='') ? _gettext('News') : '<a href="news.php">' . _gettext('News') . '</a>';
$linkbar .= ' | ';
if (isset($kusabaorg)) {
	$linkbar .= '<a href="download.html">Download</a> | ';
}

$not_hidden_subpages = array_filter($subpages, function($subpage) {
	return $subpage['hidden'] == 0;
});

$subpage_links = array_map(function($subpage) {
	$p = preg_replace('/\\.html$/', '', $subpage['file']);
	return ($_GET['p']==$p) ? _gettext($subpage['name']) : '<a href="news.php?p=' . $p . '">' . _gettext($subpage['name']) . '</a>';
}, $not_hidden_subpages);

$linkbar .= implode(' | ', $subpage_links);

$linkbar .= '<br/>';

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

$smarty->assign('topMenu', TopMenu::TopMenuHtml());

// {{{ Main content

$content = null;

foreach ($subpages as $subpage) {
	$p = preg_replace('/\\.html$/', '', $subpage['file']);
	if ($_GET['p'] == $p) {
		$content = file_get_contents(KU_ROOTDIR . 'inc/pages/'.$subpage['file']);
		break;
	}
}

if (!$content) {
	if (DEFINED('KU_NEWSCONTENT') && KU_NEWSCONTENT) {
		$content = KU_NEWSCONTENT;
	} else {
		$content = '<hr/><img src="410.png" width="300px" height="300px" alt="Logo" style="float: left; padding-left:1.6em;"/><div class="telega" style="padding-left:1.6em; margin-top: 3em;">
	Добро пожаловать на 410chan! Наш сайт является имиджбордом — одним из специфических сетевых форумов, где нет принудительной регистрации, а к сообщениям
	можно легко прикреплять графические файлы. Тематика сайта почти ничем не ограничена: те вопросы, для которых не выделено отдельного тематического раздела, можно обсудить на доске <a href="/b">/b/</a>.<br/><br/>
	Поскольку сайт построен по принципу наполнения самими пользователями, то, как он будет выглядеть, всецело зависит от них. Наше сообщество стремится к достижению высокой культуры общения.
	Мы надеемся на то, что пассажиры нашего Автобуса будут вежливыми и интересными собеседниками и предпочтут содержательное общение бессмысленным разборкам.<br/><br/>
	И помните: <a href="http://noobtype.ru/wiki/Автобус_410" target="_blank">Автобус следует в ад</a>.
	</div><hr style="clear: both;"/>'; /* Вельми кривая вёрстка, я знаю.*/
	}
	$entries = 0;
	/* Get all of the news entries, ordered with the newest one placed on top */
	$news_per_page = defined('KU_NEWSPERPAGE') && KU_NEWSPERPAGE
		? KU_NEWSPERPAGE
		: 5;

	$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 0;

	$offset = $current_page * $news_per_page;

	$total_news_row = $tc_db->GetAll("SELECT COUNT(*) as total_news FROM `".KU_DBPREFIX."news`");
	$total_news = $total_news_row[0]['total_news'];
	$total_news_pages = ceil($total_news/$news_per_page);

	$results = $tc_db->GetAll("SELECT * FROM `".KU_DBPREFIX."news` ORDER BY `postedat` DESC LIMIT $offset, $news_per_page ");
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
	}
	if ($total_news > $news_per_page) {
		$content .= newsPageList($current_page, $total_news_pages);
	}
}
$smarty->assign('content', $content);
// }}}

$smarty->display('news.tpl');
?>
