<!DOCTYPE html>
<html>
<head>
<title>Search</title>
<script type="text/javascript" src="{$ku_webpath}/lib/javascript/kusaba.js"></script>
<link rel="shortcut icon" href="{$ku_webpath}/favicon.ico">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="cache-control" content="no-cache">
<meta http-equiv="expires" content="Sat, 17 Mar 1990 00:00:01 GMT">
<link rel="stylesheet" href="{$ku_webpath}/css/img_global.css">
<link rel="alternate stylesheet" href="{$ku_webpath}/css/burichan.css" title="Burichan">
<link rel="alternate stylesheet" href="{$ku_webpath}/css/futaba.css" title="Futaba">
<link rel="alternate stylesheet" href="{$ku_webpath}/css/photon.css" title="Photon">
<link rel="alternate stylesheet" href="{$ku_webpath}/css/kusaba.css" title="Kusaba">
<link rel="alternate stylesheet" href="{$ku_webpath}/css/bluemoon.css" title="Bluemoon">

<link rel="stylesheet" href="{$ku_webpath}/css/umnochan.css" title="Umnochan">
</head>
<body>
<div class="adminbar"><select name="switcher">
<option value="Burichan" onclick="javascript:set_stylesheet('Burichan');return false;">Burichan</option><option value="Futaba" onclick="javascript:set_stylesheet('Futaba');return false;">Futaba</option><option value="Photon" onclick="javascript:set_stylesheet('Photon');return false;">Photon</option><option value="Kusaba" onclick="javascript:set_stylesheet('Kusaba');return false;">Kusaba</option><option value="Bluemoon" onclick="javascript:set_stylesheet('Bluemoon');return false;">Bluemoon</option><option value="Umnochan" onclick="javascript:set_stylesheet('Umnochan');return false;">Umnochan</option>-&nbsp;[<a href="#" onclick="javascript:showwatchedthreads();return false" title="Избранные треды">WT</a>]&nbsp;</select>&nbsp;[<a href="{$ku_webpath}/search.php">Поиск</a>]&nbsp;[<a href="{$ku_webpath}" target="_top">Домой</a>]&nbsp;[<a href="{$ku_webpath}/manage.php" target="_top">Управление</a>]</div>
<div class="navbar">[ <a title="/d/испетчерская" href="/d/">d</a> ] [ <a title="Авто/b/ус" href="/b/">b</a> / <a title="International" href="/int/">int</a> / <a title="Кулинария" href="/cu/">cu</a> / <a title="Разработка" href="/dev/">dev</a> ] [ <a title="Радио 410" href="/r/">r</a> ] [ <a title="Аниме" href="/a/">a</a> / <a title="Цундере" href="/ts/">ts</a> / <a title="Type-Moon" href="/tm/">tm</a> / <a title="Mahō Shōjo Lyrical Nanoha" href="/nano/">nano</a> ] [ <a title="Городская жизнь" href="/ci/">ci</a> ] </div>

<div class="logo">Поиск</div>

<hr>
<div class="postarea">

<table class="postform">

<tr>
<form method="GET" action="search.php">
<td class="postblock">Поиск</td><td><input type="text" size="55" maxlength="75" name="q" value="{$query|escape}"><input type="submit" value="go"></td>
</tr>
<tr>
<td class="postblock">Доски</td><td>{foreach from=$boards item=board}
<label><input type="checkbox" name="inboards[]" value={$board} {if $inboards.$board}checked{/if}>/{$board}/</input></label> {/foreach}
</tr>
</form>
<tr>
<td colspan="2" class="rules">
		<ul style="margin-left: 0; margin-top: 0; margin-bottom: 0; padding-left: 0;">
<li>Минимальная длина слова: 4.</li>
<li>Если не отмечена ни одна доска, поиск идёт по всем.</li>
<li>Дополнтельную информацио о поиске вы можете получить на странице <a href="http://410chan.ru/news.php?p=faq">FAQ</a>.</li>
</td>
</tr>
</table></div>
<hr/>
