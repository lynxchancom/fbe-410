<?php

require_once KU_ROOTDIR . 'lib/smarty/Smarty.class.php';

function smarty_gettext ($params, &$smarty) {
	return _gettext($params['text']);
}

function getSmarty() {
	$smarty = new Smarty();

	$smarty->template_dir = KU_TEMPLATEDIR;
	if (KU_CACHEDTEMPLATEDIR != '') {
		$smarty->compile_dir = KU_CACHEDTEMPLATEDIR;
		$smarty->cache_dir = KU_CACHEDTEMPLATEDIR;
	}
	$smarty->config_dir = KU_ROOTDIR . 'smarty/configs';

	$smarty->assign('ku_name', KU_NAME);
	$smarty->assign('ku_webpath', KU_WEBPATH);
	$smarty->assign('ku_boardspath', KU_BOARDSPATH);
	$smarty->assign('ku_cgipath', KU_CGIPATH);

	$smarty->register_function('gettext', 'smarty_gettext');

	return $smarty;
}

$smarty = getSmarty();

?>