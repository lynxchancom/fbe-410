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
 * Script configuration
 *
 * Tells the script what to call itself, where the database and other things are
 * located, along with define what features to enable.
 * 
 * @package kusaba  
 */
/*
To enable a feature, change the value to true:
	define('KU_INSTANTREDIRECT', true);
To disable a feature, change the value to false:
	define('KU_INSTANTREDIRECT', false;

To change the text value of a configuration, edit the text in the single quotes:
	define('KU_NAME', 'Serissa');
Becomes:
	define('KU_NAME', 'Mychan');
Warning: Do not insert single quotes in the value yourself, or else you will cause problems.  To overcome this, you use what is called escaping, which is the process of adding a backslash before the single quote, to show it is part of the string:
	define('KU_NAME', 'Jason\'s chan');

The postbox is where you mix dynamic values with your own text.  The text from what you enter is then parsed and will be displayed under the postbox on each board page and thread page:
	define('KU_POSTBOX', '<ul><li>Supported file types are: <!tc_filetypes /></li><li>Maximum file size allowed is <!tc_maximagekb /> KB.</li><li>Images greater than <!tc_maxthumbwidth />x<!tc_maxthumbheight /> pixels will be thumbnailed.</li><li>Currently <!tc_uniqueposts /> unique user posts.<!tc_catalog /></li></ul>');
Will become (if you had my settings):
	* Supported file types are: GIF, JPG, PNG
	* Maximum file size allowed is 1000 KB.
	* Images greater than 200x200 pixels will be thumbnailed.
	* Currently 221 unique user posts. View catalog
Possible values you may use:
	<!tc_filetypes />
	<!tc_maximagekb />
	<!tc_maxthumbwidth />
	<!tc_maxthumbheight />
	<!tc_uniqueposts />
	<!tc_catalog />
*/
if (!headers_sent()) {
	header('Content-Type: text/html; charset=utf-8');
}

$cf = array();
mb_internal_encoding("UTF-8");
date_default_timezone_set('Europe/Moscow');

/* Caching (this needs to be set at the start because if enabled, it skips the rest of the configuration process) */
	$cf['KU_APC'] = false;
$cache_loaded = false;
if ($cf['KU_APC']) {
	if (apc_load_constants('config')) {
		$cache_loaded = true;
	}
}

require("adaptive_config.php");
if (!$cache_loaded) {
	/* Database */
		$cf['KU_DBHOST']          = 'localhost'; /* Database hostname */
		$cf['KU_DBDATABASE']      = '410chan'; /* Database... database */
		$cf['KU_DBUSERNAME']      = '410chan'; /* Database username */
		$cf['KU_DBPASSWORD']      = 'dbpassword'; /* Database password */
		$cf['KU_DBPREFIX']        = ''; /* Database table prefix */
		$cf['KU_DBUSEPERSISTENT'] = false; /* Use persistent connection to database */
	/* Search Database (separate database recommended */
		$cf['KU_SEARCH']          = '0'; /* Add records to search table? */
		$cf['KU_SEARCHEXCLUDEBOARDS']          = array('cp' => 1, 'raeateringplubs' => 1); /* exclude those boards from search */
		$cf['KU_DBSEARCHHOST']          = 'localhost'; /* Database hostname */
		$cf['KU_DBSEARCHDATABASE']      = '410search'; /* Database... database */
		$cf['KU_DBSEARCHUSERNAME']      = '410chan'; /* Database username */
		$cf['KU_DBSEARCHPASSWORD']      = 'dbsearchpassword'; /* Database password */
		$cf['KU_FLAGBOARDS']          = array('int' => 1); /* add country information to this boards */

	/* Root dir */
		$cf['KU_ROOTDIR']   = '/path/to/sites/410chan/';  /* Full system path of the folder containing kusaba.php, with trailing slash. If you're running a Windows server, add .'\\'; to the end of the folder name*/
		
	/* Chan info */
		$cf['KU_NAME']      = '410chan'; /* The name of your site */
		$cf['KU_SLOGAN']    = '<em>"We are going to hell. And I&rsquo;m driving the Bus"</em>'; /* Site slogan, set to nothing to disable its display */
		$cf['KU_HEADERURL'] = ''; /* Full URL to the header image (or rotation script) to be displayed, can be left blank for no image */
		$cf['KU_IRC']       = ''; /* IRC info, which will be displayed in the menu.  Leave blank to remove it */
	
	/* Paths and URLs */
		/* Main installation directory */
			$cf['KU_WEBFOLDER'] = '/'; /* The path from the domain of the board to the folder which kusaba is in, including the trailing slash.  Example: "http://www.yoursite.com/misc/kusaba/" would have a $cf['KU_WEBFOLDER'] of "/misc/kusaba/" */
			$cf['KU_WEBPATH']   = ''; /* The path to the index folder of kusaba, without trailing slash */
			$cf['KU_DOMAIN']    = '410chan.org'; /* Used in cookies for the domain parameter.  Should be a period and then the top level domain, which will allow the cookies to be set for all subdomains.  For http://www.randomchan.org, the domain would be .randomchan.org; http://zachchan.freehost.com would be zach.freehost.com */
		/* Root administrator account */
			$cf['KU_ROOT'] = 'root'; /* Used to bypass security checks, cannot be modifed or deleted */
		/* Board subdomain/alternate directory (optional, change to enable) */
			/* DO NOT CHANGE THESE IF YOU DO NOT KNOW WHAT YOU ARE DOING!!  GOD DAMN TEE YOU ARE A FAG*/
			$cf['KU_BOARDSDIR']    = $cf['KU_ROOTDIR'];
			$cf['KU_BOARDSFOLDER'] = $cf['KU_WEBFOLDER'];
			$cf['KU_BOARDSPATH']   = $cf['KU_WEBPATH'];
		
		/* CGI subdomain/alternate directory (optional, change to enable) */
			/* DO NOT CHANGE THESE IF YOU DO NOT KNOW WHAT YOU ARE DOING!! */
			$cf['KU_CGIDIR']    = $cf['KU_BOARDSDIR'];
			$cf['KU_CGIFOLDER'] = $cf['KU_BOARDSFOLDER'];
			$cf['KU_CGIPATH']   = $cf['KU_BOARDSPATH'];
			
		/* Coralized URLs (optional, change to enable) */
			$cf['KU_WEBCORAL']    = ''; /* Set to the coralized version of your webpath to enable.  If not set to '', URLs which can safely be cached will be coralized, and will use the Coral Content Distribution Network.  Example: http://www.kusaba.org becomes http://www.kusaba.org.nyud.net, http://www.crapchan.org/kusaba becomes http://www.crapchan.org.nyud.net/kusaba */
			$cf['KU_BOARDSCORAL'] = '';
			
	/* Templates */
		$cf['KU_TEMPLATEDIR']       = $cf['KU_ROOTDIR'] . 'smarty/templates'; /* Smarty templates directory */
		$cf['KU_CACHEDTEMPLATEDIR'] = $cf['KU_ROOTDIR'] . 'smarty/templates_c'; /* Smarty compiled templates directory.  This folder MUST be writable (you may need to chmod it to 755).  Set to '' to disable template caching */
	
	/* CSS styles */
		$cf['KU_STYLES']        = 'umnochan:burichan:futaba:photon:kusaba:bluemoon'; /* Styles which are available to be used for the boards, separated by colons, in lower case.  These will be displayed next to [Home] [Manage] if KU_STYLESWIKUHER is set to true */
		$cf['KU_DEFAULTSTYLE']  = 'umnochan'; /* If Default is selected in the style list in board options, it will use this style.  Should be lower case */
		$cf['KU_STYLESWITCHER'] = true; /* Whether or not to display the different styles in a clickable switcher at the top of the board */
		
		$cf['KU_TXTSTYLES']        = 'futatxt:buritxt:yotsuba:headline:pseud0ch:umnotxt'; /* Styles which are available to be used for the boards, separated by colons, in lower case */
		$cf['KU_DEFAULTTXTSTYLE']  = 'umnotxt'; /* If Default is selected in the style list in board options, it will use this style.  Should be lower case */
		$cf['KU_TXTSTYLESWITCHER'] = true; /* Whether or not to display the different styles in a clickable switcher at the top of the board */
		
		$cf['KU_MENUTYPE']          = 'normal'; /* Type of display for the menu.  normal will add the menu styles and such as it normally would, plain will not use the styles, and will look rather boring */
		$cf['KU_MENUSTYLES']        = 'umnochan:burichan:futaba:photon:kusaba:bluemoon'; /* Menu styles */
		$cf['KU_DEFAULTMENUSTYLE']  = 'umnochan'; /* Default menu style */
		$cf['KU_MENUSTYLESWITCHER'] = true; /* Whether or not to display the different styles in a clickable switcher in the menu */
		
	/* Limitations */
		$cf['KU_NEWTHREADDELAY'] = 30; /* Minimum time in seconds a user must wait before posting a new thread again */
		$cf['KU_REPLYDELAY']     = 7; /* Minimum time in seconds a user must wait before posting a reply again */
		$cf['KU_LINELENGTH']     = 150; /* Used when cutting long post messages on pages and placing the message too long notification */
	
	/* Image handling */
		$cf['KU_THUMBWIDTH']       = 200; /* Maximum thumbnail width */
		$cf['KU_THUMBHEIGHT']      = 200; /* Maximum thumbnail height */
		$cf['KU_REPLYTHUMBWIDTH']  = 200; /* Maximum thumbnail width (reply) */
		$cf['KU_REPLYTHUMBHEIGHT'] = 200; /* Maximum thumbnail height (reply) */
		$cf['KU_CATTHUMBWIDTH']    = 100; /* Maximum thumbnail width (catalog) */
		$cf['KU_CATTHUMBHEIGHT']   = 100; /* Maximum thumbnail height (catalog) */
		$cf['KU_THUMBMETHOD']      = 'gd'; /* Method to use when thumbnailing images in jpg, gif, or png format. Options available: ffmpeg, gd, imagemagick */
		$cf['KU_ANIMATEDTHUMBS']   = false; /* Whether or not to allow animated thumbnails (only applies if using ffmpeg or imagemagick) */
		
	/* Post handling */
		$cf['KU_NEWWINDOW']       = true; /* When a user clicks a thumbnail, whether to open the link in a new window or not */
		$cf['KU_MAKELINKS']       = true; /* Whether or not to turn http:// links into clickable links */
		$cf['KU_NOMESSAGETHREAD'] = 'Предлагаю забанить ОПа'; /* Text to set a message to if a thread is made with no text */
		$cf['KU_NOMESSAGEREPLY']  = '<span style="white-space: nowrap">ｷﾀ━━━(ﾟ∀ﾟ)━━━!!</span>'; /* Text to set a message to if a reply is made with no text */
	
	/* Post display */
		$cf['KU_THREADS']         = 10; /* Number of threads to display on a board page */
		$cf['KU_THREADSTXT']      = 15; /* Number of threads to display on a text board front page */
		$cf['KU_REPLIES']         = 7; /* Number of replies to display on a board page */
		$cf['KU_REPLIESSTICKY']   = 4; /* Number of replies to display on a board page when a thread is stickied */
		$cf['KU_THUMBMSG']        = false; /* Whether or not to display the "Thumbnail displayed, click image for full size." message on posts with images */
		$cf['KU_BANMSG']          = '<br><font color="#FF0000"><b>(ПОТРЕБИТЕЛЬ БЫЛ ЗАПРЕЩЁН ДЛЯ ЭТОГО СТОЛБА)</b></font>'; /* The text to add at the end of a post if a ban is placed and "Add ban message" is checked */
		$cf['KU_TRADITIONALREAD'] = false; /* Whether or not to use the traditional style for multi-quote urls.  Traditional: read.php/board/thread/posts, Non-traditional: read.php?b=board&t=thread&p=posts */
		$cf['KU_YOUTUBEWIDTH']    = 200; /* Width to display embedded YouTube videos */
		$cf['KU_YOUTUBEHEIGHT']   = 164; /* Height to display embedded YouTube videos */
		$cf['KU_MULTIPLE'] = true; /* Multiple post delete */
		
	/* Pages */
		$cf['KU_POSTBOX']   = array('ru' => '<ul style="margin-left: 0; margin-top: 0; margin-bottom: 0; padding-left: 0;">
<li>Прежде чем постить, ознакомьтесь с <a href="//'. $cf['KU_DOMAIN'] .'/news.php?p=rules">правилами</a>.</li>
<li>Поддерживаются файлы типов <!tc_filetypes /> размером до <!tc_maximagekb /> кБ.</li>
<!--li>Изображения, размер которых превышает <!tc_maxthumbwidth /> на <!tc_maxthumbheight /> пикселей, будут уменьшены.</li-->
<li>Ныне <!tc_uniqueposts /> unique user posts.<!tc_catalog /></li>
<li>Максимальное количество бампов нити: <!tc_bumplimit /></li>
<li>Радио: <a href="http://radio.410chan.ru:8000/status.xsl"><span id="radio-<!--#include virtual=/radiostat/radiostat.txt -->"><!--#include virtual=/radiostat/radiostat.txt --></span></a></li>
</ul>',
'en' => '
<ul style="margin-left: 0; margin-top: 0; margin-bottom: 0; padding-left: 0;">
<li>Please read the <a href="//'. $cf['KU_DOMAIN'] .'/news.php?p=rules.en">Rules</a> before posting</li>
<li>Supported filetypes for uploads: <!tc_filetypes /></li>
<li>Maximum file size for upload: <!tc_maximagekb /></li>
<!--li>Images uploaded over <!tc_maxthumbwidth />x<!tc_maxthumbheight /> pixels will be thumbnailed</li-->
<li><!tc_uniqueposts /> unique user posts. <!tc_catalog /> </li>
<li>Bumplimit: <!tc_bumplimit /></li>
<li>Radio: <a href="http://radio.410chan.ru:8000/status.xsl"><span id="radio-<!--#include virtual=/radiostat/radiostat.txt -->"><!--#include virtual=/radiostat/radiostat.txt --></span></a></li>
</ul>',
'cu' => '
<ul style="margin-left: 0; margin-top: 0; margin-bottom: 0; padding-left: 0;">
<li>Прѣдъ напьсаниꙗ сътворѥниѥмь ꙁьри <a href="//'. $cf['KU_DOMAIN'] .'/news.php?p=rules">правила</a>.</li>
<li>Поволѥнꙑ дѣлъ тѷпꙑ: <!tc_filetypes />, ижє мѣроѭ вѧщє <!tc_maximagekb /> х҃Б нє сѫтъ</li>
<!--li>Видꙑ, ижє <!tc_maxthumbwidth /> на <!tc_maxthumbheight /> пиѯєлъ сѫтъ, оумалѥнꙑ бѫдѫтъ.</li-->
<li>Нꙑнѣ <!tc_uniqueposts /> unique user posts .<!tc_catalog />.</li>
<li>Послѣди сѥго числа напьсании нить опакꙑ въ врьхъ нє придєтъ: <!tc_bumplimit /></li>
<li>Радїо: <a href="http://radio.410chan.ru:8000/status.xsl"><span id="radio-<!--#include virtual=/radiostat/radiostat.txt -->"><!--#include virtual=/radiostat/radiostat.txt --></span></a></li>
</ul>',
'default' => 'ru',
'lang_def' => 'default language postbox'
);
		/* Notice displayed under the post area */
		$cf['KU_FIRSTPAGE'] = 'board.html'; /* Filename of the first page of a board.  Only change this if you are willing to maintain the .htaccess files for each board directory (they are created with a DirectoryIndex board.html, change them if you change this) */
		$cf['KU_DIRTITLE']  = false; /* Whether or not to place the board directory in the board's title and at the top of the page.  true would render as "/b/ - Random", false would render as "Random" */
		
	/* File tagging */
		$cf['KU_TAGS'] = array('Japanese' => 'J',
		                       'Anime'    => 'A',
		                       'Game'     => 'G',
		                       'Loop'     => 'L',
		                       'Other'    => '*'); /* Used only in Upload imageboards.  These are the tags which a user may choose to use as they are posting a file.  If you wish to disable tagging on Upload imageboards, set this to '' */
	
	/* Special Tripcodes */
		$cf['KU_TRIPS'] = array('#secret tripcode for namefags'  => 'innomines');
//		                        '#changeme2' => 'changeme2'); /* Special tripcodes which can have a predefined output.  Do not include the initial ! in the output.  Maximum length for the output is 30 characters.  Set to array(); to disable */
	
	/* Extra features */
		$cf['KU_RSS']             = true; /* Whether or not to enable the generation of rss for each board and modlog */
		$cf['KU_EXPAND']          = true; /* Whether or not to add the expand button to threads viewed on board pages */
		$cf['KU_INDICATEMOVEDTHREADS'] = false;	/* Wheter or not to show if thread was moved from another board */
		$cf['KU_QUICKREPLY']      = true; /* Whether or not to add quick reply links on posts */
		$cf['KU_WATCHTHREADS']    = true; /* Whether or not to add thread watching capabilities */
		$cf['KU_POSTSPY']   	  = false; /* Whether or not to add thread watching capabilities */
		$cf['KU_FIRSTLAST']       = true; /* Whether or not to generate extra files for the first 100 posts/last 50 posts */
		$cf['KU_BLOTTER']         = true; /* Whether or not to enable the blotter feature */
		$cf['KU_SITEMAP']         = false; /* Whether or not to enable automatic sitemap generation (you will still need to link the search engine sites to the sitemap.xml file) */
		$cf['KU_APPEAL']          = ''; /* List of email addresses separated by colons to send ban appeal messages to.  Set to '' to disable the ban appeal system */
		$cf['KU_PINGBACK']        = ''; /* The password to use when making a ping to the chan directory.  Set to '' to disable */
		$cf['KU_PINGBACKDESC']    = ''; /* Description of site to send when making a ping to the chan directory.  This will have no effect if KU_PINGBACK is blank */
		$cf['KU_MODLOG']   		  = ''; /* Name of the modlog */
		$cf['KU_ANONYMOUS']       = ''; /* Name of the board which has no moderation. (No bans or moderator actions can be used, delete only)*/
		
	/* Misc config */
		$cf['KU_MODLOGDAYS']        = 7; /* Days to keep modlog entries before removing them */
		$cf['KU_RANDOMSEED']        = 'loveriskyucialssionsiescubnali'; /* Type a bunch of random letters/numbers here, any large amount (35+ characters) will do */
		$cf['KU_STATICMENU']        = false; /* Whether or not to generate the menu files as static files, instead of linking to menu.php.  Enabling this will reduce load, however some users have had trouble with getting the files to generate */
		$cf['KU_GENERATEBOARDLIST'] = true; /* Set to true to automatically make the board list which is displayed ad the top and bottom of the board pages, or false to use the boards.html file */
		
	/* Language / timezone / encoding */
		$cf['KU_LOCALE']  = 'ru'; /* The locale of kusaba you would like to use.  Locales available: en, de, et, es, fi, pl, nl, nb, ru, it, ja */
		$cf['KU_CHARSET'] = 'UTF-8'; /* The character encoding to mark the pages as.  This must be the same in the .htaccess file (AddCharset charsethere .html and AddCharset charsethere .php) to function properly.  Only UTF-8 and Shift_JIS have been tested */
		putenv('TZ=Europe/Moscow'); /* The time zone which the server resides in */
	
	/* Post-configuration actions, don't modify these */
		$cf['KU_VERSION']		= '1.2.518-git';
		$cf['KU_PROJECT_NAME']	= 'Flower Bus Engine';
		$cf['KU_PROJECT_URL']	= 'https://bitbucket.org/innomines/fbe-410';
		$cf['KU_TAGS']			= serialize($cf['KU_TAGS']);
		$cf['KU_TRIPS']			= serialize($cf['KU_TRIPS']);
		$cf['KU_SEARCHEXCLUDEBOARDS'] = serialize($cf['KU_SEARCHEXCLUDEBOARDS']);
		$cf['KU_FLAGBOARDS'] = serialize($cf['KU_FLAGBOARDS']);
		$cf['KU_POSTBOX']    = serialize($cf['KU_POSTBOX']);

		$cf['KU_LINELENGTH'] = $cf['KU_LINELENGTH'] * 15;
		
		if (substr($cf['KU_WEBFOLDER'], -2) == '//') { $cf['KU_WEBFOLDER'] = substr($cf['KU_WEBFOLDER'], 0, -1); }
		if (substr($cf['KU_BOARDSFOLDER'], -2) == '//') { $cf['KU_BOARDSFOLDER'] = substr($cf['KU_BOARDSFOLDER'], 0, -1); }
		if (substr($cf['KU_CGIFOLDER'], -2) == '//') { $cf['KU_CGIFOLDER'] = substr($cf['KU_CGIFOLDER'], 0, -1); }
		
		$cf['KU_WEBPATH'] = trim($cf['KU_WEBPATH'], '/');
		$cf['KU_BOARDSPATH'] = trim($cf['KU_BOARDSPATH'], '/');
		$cf['KU_CGIPATH'] = trim($cf['KU_CGIPATH'], '/');
		
		if ($cf['KU_APC']) {
			apc_define_constants('config', $cf);
		}
		while (list($key, $value) = each($cf)) {
			define($key, $value);
		}
		unset($cf);
}

/* DO NOT MODIFY BELOW THIS LINE UNLESS YOU KNOW WHAT YOU ARE DOING */
$modules_loaded = array();

require KU_ROOTDIR . 'lib/gettext/gettext.inc.php';
require KU_ROOTDIR . 'lib/mysql/mysql.class.php';
/* Gettext */
_textdomain('kusaba');
_setlocale(LC_ALL, KU_LOCALE);
_bindtextdomain('kusaba', KU_ROOTDIR . 'inc/lang');
_bind_textdomain_codeset('kusaba', KU_CHARSET);


if (!isset($tc_db) && !isset($preconfig_db_unnecessary)) {
	$tc_db = MySQL::getInstance();
	if (KU_DBUSEPERSISTENT) {
		$tc_db->PConnect(KU_DBHOST, KU_DBUSERNAME, KU_DBPASSWORD, KU_DBDATABASE);
	} else {
		$tc_db->Connect(KU_DBHOST, KU_DBUSERNAME, KU_DBPASSWORD, KU_DBDATABASE);
	}
	$tc_db->Execute("SET NAMES 'utf8mb4'");
	if (!file_exists("install.php") && !file_exists("install-mysql.php")) {
	$results_events = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "events` WHERE `at` <= " . time());
	if (count($results_events) > 0) {
			foreach($results_events AS $line_events) {
				if ($line_events['name'] == 'sitemap') {
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "events` SET `at` = " . (time() + 21600) . " WHERE `name` = 'sitemap'");
					if (KU_SITEMAP) {
						$sitemap = '<?xml version="1.0" encoding="UTF-8"?' . '>' . "\n" .
						'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" . "\n";
						
						$results = $tc_db->GetAll("SELECT `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
						if (count($results) > 0) {
							foreach($results AS $line) {
								$sitemap .= '	<url>' . "\n" .
								'		<loc>' . KU_BOARDSPATH . '/' . $line['name'] . '/</loc>' . "\n" .
								'		<lastmod>' . date('Y-m-d') . '</lastmod>' . "\n" .
								'		<changefreq>hourly</changefreq>' . "\n" .
								'	</url>' . "\n";
										
								$results2 = $tc_db->GetAll("SELECT `id`, `lastbumped` FROM `" . KU_DBPREFIX . "posts_" . $line['name'] . "` WHERE `parentid` = 0 AND `IS_DELETED` = 0 ORDER BY `lastbumped` DESC");
								if (count($results2) > 0) {
									foreach($results2 AS $line2) {
										$sitemap .= '	<url>' . "\n" .
										'		<loc>' . KU_BOARDSPATH . '/' . $line['name'] . '/res/' . $line2['id'] . '.html</loc>' . "\n" .
										'		<lastmod>' . date('Y-m-d', $line2['lastbumped']) . '</lastmod>' . "\n" .
										'		<changefreq>hourly</changefreq>' . "\n" .
										'	</url>' . "\n";
									}
								}
							}
						}
						
						$sitemap .= '</urlset>';
						
						$fp = fopen(KU_BOARDSDIR . 'sitemap.xml', 'w');
						fwrite($fp, $sitemap);
						fclose($fp);
						
						unset($sitemap, $fp);
					}
				}
			}
		unset($results_events, $line_events);
	}
}
}
if (get_magic_quotes_gpc()) {
/*
	foreach ($_GET as $key => $val) {
		$_GET[$key] = stripslashes($val);
	}
	foreach ($_POST as $key => $val) {
		$_POST[$key] = stripslashes($val);
	}
*/
}
if (get_magic_quotes_runtime()) {
	set_magic_quotes_runtime(0);
}

?>
