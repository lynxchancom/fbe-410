<?php
/**
 * Generate the list of pages, linking to each
 *
 * @param integer $page Current page
 * @param integer $pages Number of pages
 * @param callable $getPageUrl Takes page number, returns url
 * @return string Generated page list
 */
function pageListCommon($page, $pages, $getPageUrl) {
    $output = '<div class="pgstbl"><table><tbody><tr><td>';

    if ($page == 0) {
        $output .= _gettext('←');
    } else {
        $output .= '<form method="get" action="' . $getPageUrl($page-1) . '"><input value="' . _gettext('←') . '" type="submit"></form>';
    }

    $output .= '</td><td>';

    for ($i=0;$i<=$pages-1;$i++) {
        if ($page == $i) {
            $output .= '&#91;'.$i.'&#93;';
        } else {
            $output .= '&#91;<a href="' . $getPageUrl($i) . '">' . $i . '</a>&#93;';
        }

        $output .= ' ';
    }

    /* Remove the unwanted space */
    $output = substr($output, 0, -1);

    $output .= '</td><td>';

    if ($page == $pages-1) {
        $output .= _gettext('→');
    } else {
        $output .= '<form method="get" action="' . $getPageUrl($page + 1) . '"><input value="' . _gettext('→') . '" type="submit"></form>';
    }

    $output .= '</td></tr></tbody></table></div>';

    return $output;
}

/**
 * Generate the list of board pages, linking to each
 * 
 * @param integer $boardpage Current board page 
 * @param integer $pages Number of pages
 * @param string $board Board directory 
 * @return string Generated page list
 */   
function pageList($boardpage, $pages, $board) {
    return pageListCommon($boardpage, $pages, function($page) use ($board) {
        $url = KU_BOARDSFOLDER . $board . '/';

        if ($page != 0) {
            $url .= $page . '.html';
        }

        return $url;
    });
}

/**
* Generate the list of news pages, linking to each
*
 * @param integer $newspage Current news page
* @param integer $pages Number of pages
* @return string Generated page list
 */
function newsPageList($newspage, $pages) {
    return pageListCommon($newspage, $pages, function($page) {
        $url = 'news.php';

        if ($page!=0) {
            $url .= '?page=' . ($page);
        }

        return $url;
    });
}

/**
 * Create a single row for a thread, which will be displayed in the upload imageboard board pages
 * 
 * @param string $post Post data
 * @param string $board Board directory
 * @param integer $maxage Maximum thread age
 * @param integer $replies Number of replies to the thread 
 * @return string Thread row
 */ 
function uploadImageboardPageRow($post, $board, $maxage, $replies) {
	if ($post['tag'] == '') {
		$post['tag'] = '*';
	}
	if ($post['filesize_formatted'] == '') {
		$filesize = ConvertBytes($post['filesize']);
	} else {
		$filesize = $post['filesize_formatted'];
	}
	$output = '<tr';
	/* If the thread is two hours or less from being pruned, add the style for old rows */
	if (checkMarkedForDeletion($post, $maxage)) {
		$output .= ' class="replyhl"';
	}
	$output .= '>' . "\n" .
	'	<td align="center">' . "\n" .
	'		' . $post['id'] . "\n" .
	'	</td>' . "\n" .
	'	<td>' . "\n" .
	'		' . formatNameAndTrip($post['name'], $post['email'], $post['tripcode']) .
	'	</td>' . "\n" .
	'	<td align="center">' . "\n" .
	'		[<a href="' . KU_BOARDSFOLDER . $board . '/src/' . $post['filename'] . '.' . $post['filetype'] . '" target="_blank">' . $post['filename'] . '.' . $post['filetype'] . '</a>]' . "\n" .
	'	</td>' . "\n" .
	'	<td align="center">' . "\n" .
	'		[' . $post['tag'] . ']' . "\n" .
	'	</td>' . "\n" .
	'	<td>' . "\n" .
	'		' . $post['subject'] . "\n" .
	'	</td>' . "\n" .
	'	<td align="center">' . "\n" .
	'		' . $filesize . "\n" .
	'	</td>' . "\n" .
	'	<td>' . "\n" .
	'		<span style="white-space: nowrap;">' . date("y/m/d(D)H:i", $post['postedat']) . '</span>' . "\n" .
	'	</td>' . "\n" .
	'	<td align="center">' . "\n" .
	'		' . $replies . "\n" .
	'	</td>' . "\n" .
	'	<td align="center">' . "\n" .
	'		[<a href="' . KU_BOARDSFOLDER . $board . '/res/' . $post['id'] . '.html">Reply</a>]' . "\n" .
	'	</td>' . "\n" .
	'</tr>' . "\n";
	
	return $output;
}

/* <3 coda for this wonderful snippet
print $contents to $filename by using a temporary file and renaming it */
function print_page($filename, $contents, $board) {
	global $tc_db;
	
	$tempfile = tempnam(KU_BOARDSDIR . $board . '/res', 'tmp'); /* Create the temporary file */
	$fp = fopen($tempfile, 'w');
	fwrite($fp, $contents);
	fclose($fp);
	/* If we aren't able to use the rename function, try the alternate method */
	if (!@rename($tempfile, $filename)) {
		copy($tempfile, $filename);
		unlink($tempfile);
	}
	
	chmod($filename, 0664); /* it was created 0600 */
}

/**
 * Create thread links which are displayed at the top of thread pages, sometimes on the bottom as well, and also displayed in the thread info row
 *
 * @param integer $type Link type
 * @param integer $threadid Thread ID
 * @param string $board Board directory
 * @param integer $type Board type
 * @param boolean $modifier_last50 Last 50 modifier in effect
 * @param boolean $modifier_first100 First 100 modifier in effect 
 * @param boolean $forcereplymodehide Force the Reply Mode to be hidden
 * @param string $archive_dir Archive directory
 * @return string Thread links
 */ 
function threadLinks($type, $threadid, $board, $boardtype, $modifier_last50, $modifier_first100, $forcereplymodehide = false, $forceentirethreadlink = false, $archive_dir = '') {
	global $CURRENTLOCALE;
	if ($boardtype != 1) {
		if ($CURRENTLOCALE == 'ja') {
			$leftbracket = '［';
			$rightbracket = '］';
		} else {
			$leftbracket = '&#91;';
			$rightbracket = '&#93;';
		}
	} else {
		$leftbracket = '';
		$rightbracket = '';
	}

	if ($type == 'archive') {
		$output = $leftbracket . '<a href="' . KU_BOARDSFOLDER . $board . $archive_dir . '/res/">' . _gettext('Return') . '</a>' . $rightbracket;
	} elseif ($type == 'return') {
		$output = $leftbracket . '<a href="' . KU_BOARDSFOLDER . $board . '/">' . _gettext('Return') . '</a>' . $rightbracket;
	} elseif ($type == 'page' && $boardtype == 1) {
		$output = '<p class="hidden">' . _gettext('The 5 newest replies are shown below.') . '<br>';
	} elseif ($type == 'page' && $boardtype != 1) {
		$output = $leftbracket . '<a href="' . KU_BOARDSFOLDER . $board . '/res/' . $threadid . '.html">' . _gettext('Reply') . '</a>' . $rightbracket;
	}
	
	if ($type != 'archive' && ((KU_FIRSTLAST && $modifier_last50) || $boardtype == 1 || $forceentirethreadlink)) {
		if ($type == 'return') {
			$output .= ' ' . $leftbracket;
		}
		
		if ($type == 'return' || ($type == 'page' && $boardtype == 1)) {
			$output .= '<a href="' . KU_BOARDSFOLDER . $board . '/res/' . $threadid . '.html">';
			
			if ($type == 'return') {
				$output .= _gettext('Entire Thread');
			} elseif ($type == 'page') {
				$output .= _gettext('Read this thread from the beginning');
			}
			
			$output .= '</a>';
		}
		
		if ($type == 'return') {
			$output .= $rightbracket;
		}
		
		if ($modifier_first100) {
			$output .= ' ' . $leftbracket . '<a href="' . KU_BOARDSFOLDER . $board . '/res/' . $threadid . '-100.html">' . _gettext('First 100 posts') . '</a>' . $rightbracket;
		}
		
		if ($modifier_last50) {
			$output .= ' ' . $leftbracket . '<a href="' . KU_BOARDSFOLDER . $board . '/res/' . $threadid . '+50.html">' . _gettext('Last 50 posts') . '</a>' . $rightbracket;
		}
	}
	
	if ($boardtype == 1 && $type == 'return') {
		$output .= '<br><br>';
	} elseif ($type == 'page' && $boardtype == 1) {
		$output .= '</p>';
	} elseif ($type != 'page' && $boardtype != 1 && !$forcereplymodehide && $type != 'archive') {
		$output .= '<div class="replymode">' . _gettext('Posting mode: Reply') . '<!tc_postmodeinfo></div>';
	}
	
	return $output;
}
?>