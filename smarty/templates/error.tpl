<!DOCTYPE html>
<html>
<head>
<title>{$ku_name}</title>
<link rel="shortcut icon" href="{$ku_webpath}/favicon.ico">
<script src="{$ku_webpath}lib/javascript/jquery-3.3.1.min.js"></script>
<script>
    style_cookie="kustyle";
    style_cookie_txt="";
</script>
{$head}<style>{literal}
body {
	width: 100% !important;
}
{/literal}</style>
</head>
<body class="site-wrapper">
<h1 style="font-size: 3em;">Error</h1>
<br>
<h2 style="font-size: 2em;font-weight: bold;text-align: center;">
{$errormsg}
</h2>
{$errormsgext}
<div style="text-align: center;width: 100%;position: absolute;bottom: 10px;">
{$footer}
</div>
</body>
</html>