<!DOCTYPE html>
<html{$htmloptions}>
<head>
<title>{$title}</title>
{$head}<script type="text/javascript" src="{$ku_webpath}/lib/javascript/kusaba.js"></script>
		<script type="text/javascript">
		var path = "{$ku_webpath}";
		var captcha_message = "{$captcha_message}";
		var req = null;
{literal}
function request_faptcha(board) {
	req = new XMLHttpRequest();
	var f = document.getElementById('faptcha_input');
	if(f) {
		f.disabled = false;
		f.value = "";
	}

	if(req) {
		req.open('GET', path + '/api_adaptive.php?board=' + board);
		req.onreadystatechange = handle;
		req.send(null);
	}
	else {
		alert("error");
	}
}
function handle() {
	var f = document.getElementById('faptcha_input');
	if(f) {
		try {
			if(req.readyState == 4) {
				var adapted = req.responseText;
				if(adapted == 1) {
					f.value = captcha_message;
					f.disabled = true;
				}
			}
		}
		catch(e) {
			alert(req.statusText);
		}
	}
}
		</script>
{/literal}
<script type="text/javascript">
	var hiddenthreads = getCookie('hiddenthreads').split('!');
</script>
<link rel="shortcut icon" href="{$ku_webpath}/favicon.ico">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="cache-control" content="no-cache">
<meta http-equiv="expires" content="Sat, 17 Mar 1990 00:00:01 GMT">
<meta name="viewport" content="width=device-width,initial-scale=1">
</head>
{$page}
<a name="bottom"></a>
</body>
</html>
