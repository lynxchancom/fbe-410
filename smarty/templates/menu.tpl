<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>{$ku_name} Navigation</title>
<script src="{$ku_webpath}lib/javascript/jquery-3.3.1.min.js"></script>
<script>
    style_cookie="kustyle";
    style_cookie_txt="";
</script>
{$styles}<link rel="shortcut icon" href="{$ku_webpath}/favicon.ico">
{literal}<script>
function toggle(button, area) {
	var tog=document.getElementById(area);
	if(tog.style.display)    {
		tog.style.display="";
	}    else {
		tog.style.display="none";
	}
	button.innerHTML=(tog.style.display)?'+':'&minus;';
	set_cookie('nav_show_'+area, tog.style.display?'0':'1', 30);
}
function showstyleswitcher() {
	var switcher = document.getElementById('sitestyles');
	switcher.innerHTML = '{/literal}{$styleswitcher}{literal}';
}
function removeframes() {
	var boardlinks = document.getElementsByTagName("a");
	for(var i=0;i<boardlinks.length;i++) if(boardlinks[i].className == "boardlink") boardlinks[i].target = "_top";
	
	document.getElementById("removeframes").innerHTML = '{/literal}{$framesremoved}{literal}';
	
	return false;
}
function hidedirs() {
	set_cookie('tcshowdirs', '', 30);
	window.location = '{/literal}{$ku_webpath}/{$menu_file_nodirs}';{literal}
}
function showdirs() {
	set_cookie('tcshowdirs', 'yes', 30);
	window.location = '{/literal}{$ku_webpath}/{$menu_file_dirs}';{literal}
}{/literal}{$redirscript}{literal}
</script>{/literal}
<base target="main">
</head>
<body class="site-wrapper sitemenu-wrapper">
{$logo}
<h1>{$ku_name}</h1>
<ul>
<li><a href="{$ku_webpath}/" target="_top">{$lang_frontpage}</a></li>
{$showhidedirs}
{$removeframes}
</ul>
{$boards}
{$irc}
</body>
</html>
