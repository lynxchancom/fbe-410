<!DOCTYPE html>
<html>
<head>
	<title>{$title}</title>
    {$head}
	<link rel="shortcut icon" href="{$ku_webpath}/favicon.ico">
</head>
<body class="site-wrapper">
<h1>{$ku_name}</h1>
<h3>{$ku_slogan}</h3>
<div style="margin: 3em;">
	<h2>&nbsp;{gettext text="WARNING"}</h2>
    {gettext text="You have been issued a warning"}:<br><br>
	<b>{$text}</b><br><br>
    {gettext text="The warning was issued on"} <b>{$at}</b>.<br><br>
    {gettext text="Your IP address is"} <b>{$ip}</b>.<br><br>
</div>
</body>
</html>