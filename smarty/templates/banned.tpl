<!DOCTYPE html>
<html>
<head>
<title>{$title}</title>
<link rel="stylesheet" href="{$ku_boardspath}/css/futaba.css" title="Futaba">
<link rel="shortcut icon" href="{$ku_webpath}/favicon.ico">
</head>
<body class="site-wrapper">
<h1>{$ku_name}</h1>
<h3>{$ku_slogan}</h3>
<div style="margin: 3em;">
	<h2>&nbsp;{$youarebanned}</h2>
	<img src="{$ku_boardspath}/youarebanned.jpg" style="float: right;" alt=":'(">
	{$youhavebeenbannedfrompostingon} <b>{$boards}</b> {$forthefollowingreason}:<br><br>
	<b>{$reason}</b><br><br>
	{$yourbanwasplacedon} <b>{$at}</b>, {$and} {$expires}.<br><br>
	{$youripaddressis} <b>{$ip}</b>.<br><br>
	{$appeal}
</div>
</body>
</html>