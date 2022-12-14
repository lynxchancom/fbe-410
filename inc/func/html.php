<?php
/**
 * Print all available stylesheets
 *
 * Creates a <link> tag for each stylesheet defined in the config
 *  
 * @param string $prefered_stylesheet Prefered stylesheet, which will be given the type "stylesheet" instead of "alternate stylesheet".  Defaults to KU_DEFAULTSTYLE.
 * @return string HTML formatted stylesheet <link> tags
 */    
function printStylesheets($prefered_stylesheet = KU_DEFAULTSTYLE) {
	global $tc_db;
	$output_stylesheets = '';
	$styles = explode(':', KU_STYLES);
	
	if (!in_array($prefered_stylesheet, $styles)) {
		$prefered_stylesheet = KU_DEFAULTSTYLE;
	}
	
	foreach ($styles as $stylesheet) {
		$output_stylesheets .= '<link rel="';
		if ($stylesheet != $prefered_stylesheet) {
			$output_stylesheets .= 'alternate ';
		}
		$output_stylesheets .= 'stylesheet" href="' . getCLBoardPath() . 'css/' . $stylesheet . '.css" title="' . ucfirst($stylesheet) . '">' . "\n";
	}
	
	return $output_stylesheets;
}

function printStylesheetsTXT($prefered_stylesheet = KU_DEFAULTTXTSTYLE) {
	global $tc_db;
	$output_stylesheets = '';
	$styles = explode(':', KU_TXTSTYLES);
	
	if (!in_array($prefered_stylesheet, $styles)) {
		$prefered_stylesheet = KU_DEFAULTTXTSTYLE;
	}
	
	foreach ($styles as $stylesheet) {
		$output_stylesheets .= '<link rel="';
		if ($stylesheet != $prefered_stylesheet) {
			$output_stylesheets .= 'alternate ';
		}
		$output_stylesheets .= 'stylesheet" href="' . getCLBoardPath() . 'css/txt_' . $stylesheet . '.css" title="' . ucfirst($stylesheet) . '">' . "\n";
	}
	
	return $output_stylesheets;
}

function printStylesheetsSite($prefered_stylesheet = KU_DEFAULTMENUSTYLE, $menu = false) {
	global $tc_db;
	$output_stylesheets = '<link rel="stylesheet" href="' . getCLBoardPath() . 'css/img_global.css">' . "\n";
	$styles = explode(':', KU_MENUSTYLES);
	
	if (!in_array($prefered_stylesheet, $styles)) {
		$prefered_stylesheet = KU_DEFAULTMENUSTYLE;
	}

	foreach ($styles as $stylesheet) {
		$output_stylesheets .= '<link rel="';
		if ($stylesheet != $prefered_stylesheet) {
			$output_stylesheets .= 'alternate ';
		}
		$output_stylesheets .= 'stylesheet" href="' . getCLBoardPath() . 'css/' . $stylesheet . '.css" title="' . ucfirst($stylesheet) . '">' . "\n";
	}
	
	$output_stylesheets .= '<script src="' . getCWebPath() . 'lib/javascript/kusaba.js"></script>' . "\n";
	
	return $output_stylesheets;
}

/**
 * Generate the form for post deletion
 * 
 * @param string $board Board directory
 * @return string Generated Form tag and board element
 */    
function deletionForm($board) {
	return '<form id="delform" action="' . KU_CGIPATH . '/board.php" method="post">' . "\n" .
	'<input type="hidden" name="board" value="' . $board . '">' . "\n";
}

/**
 * Generate the un-hide thread span
 * 
 * @param integer $id Thread ID 
 * @param string $board Board directory
 * @return string Generated span
 */   
function unHideThreadSpan($id, $board) {
	return '<div class="unhidethread" id="unhidethread' . $id . $board . '" style="display: none;">' . "\n" .
	'	Thread <a href="' . KU_BOARDSFOLDER . $board . '/res/' . $id . '.html">' . $id . '</a> hidden. ' . "\n" .
	'	<a class="btn-small btn-unidethread" href="#" onclick="togglethread(\'' . $id . $board . '\');return false;" title="Un-Hide Thread">' . "\n" .
	'<svg class="icon icon-16"><use xlink:href="/css/icons/sprite.symbol.svg#plus" width="16" height="16" viewBox="0 0 16 16"></use></svg>' .
	'</a>' . "\n" .
	'</div>' . "\n";
}

/**
 * Marked for deletion message
 *
 * @return string Marked for deletion (old).
 */ 
function markedForDeletionMessage() {
	return '<span class="oldpost">' . "\n" .
	'	' . _gettext('Marked for deletion (old).') . "\n" .
	'</span>' . "\n" .
	'<br>' . "\n";
}

/**
 * Create the delete post box, displayed at the bottom left of pages in non text boards, or inside the thread in text boards
 *
 * @param integer $boardtype Board type
 * @param boolean $enablereporting Whether enable reporting is enabled
 * @return string Delete post box
 */ 
function deletePostBox($boardtype, $enablereporting) {
	$output = '<table class="';
	if ($boardtype == 1) {
		$output .= 'hborder';
	} else {
		$output .= 'userdelete';
	}
	$output .= '">' . "\n" .
	'<tbody>' . "\n" .
	'<tr>' . "\n" .
	'<td>' . "\n" .
		
	ucwords(_gettext('Delete post'));
	if ($boardtype == 1) {
		$output .= ':';
	}
	if ($boardtype != 1) {
		$output .= ' [<input type="checkbox" name="fileonly" id="fileonly" value="on"><label for="fileonly">' . _gettext('File Only') . '</label>]<br>' . _gettext('Password');
	}
	$output .= ' <input type="password" name="postpassword" size="8">&nbsp;<input name="deletepost" value="' . _gettext('Delete') . '" type="submit">' . "\n";

	if ($enablereporting == 1) {
		$output .= '<input name="reportpost" value="' . _gettext('Report') . '" type="submit">' . "\n";
	}
	$output .= '</td>' . "\n" .
	'</tr>' . "\n" .
	'<tr id="modbox"><td></td></tr>' . "\n" .
	'</tbody>' . "\n" .
	'</table>' . "\n" .
	'<div class="godmode">[<a href="#" onclick="toggleMod(); return false;" accesskey="m">Mod</a>]</div>' .
	'</form>' . "\n";
	
	return $output;
}

/**
 * Create the javascript which fills the password boxe for the delete post form
 *
 * @return string Javascript
 */ 
function setDelPassJavascript() {
	return '<script>' . "\n" .
	'	set_delpass("delform");' . "\n" .
	'</script>' . "\n";
}

function svgIcon($iconName, $iconSize) {
    $spriteUrl = KU_WEBPATH.'/css/icons/sprite.symbol.svg';
    return '<svg class="icon icon-' . $iconSize . '"><use xlink:href="' . $spriteUrl . '#' . $iconName . '" width="' . $iconSize . '" height="' . $iconSize . '" viewBox="0 0 ' . $iconSize . ' ' . $iconSize . '"></use></svg>';
}
?>