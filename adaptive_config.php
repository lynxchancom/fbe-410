<?php

define('KU_ADAPTIVE_CAPTCHA', serialize(array('a' => 1, 'int' => 1, 'cu' => 1, 'dev' => 1, 'ts' => 1, 'tm' => 1, 'nano' => 1, 'gnx' => 1, 'ci' => 1, 'b' => 1))); /* adaptive captcha is allow user enter captcha once and then do not verify himself on future posts */

/* server key */
define('KU_SECRET', 'super secret');
/* where keep user session (non-php) files */
define('KU_SESSION', '/var/www/kusaba/.htsession');
define('KU_ADAPCHA_TIMEOUT', 86400);
?>
