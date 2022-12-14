<?php

function _log($message) {
	$log = fopen("/tmp/kusaba.log", "a");
	flock($log, LOCK_EX);
	fwrite($log, $message);
	fclose($log);
}

/**
 * Display the embedded video
 *
 * @param array $post Post data 
 * @return string Embedded video
 */ 
function embeddedVideoBox($post) {
	$output = '<span style="float: left;">' . "\n";
				
	if ($post['filetype'] == 'you') {
		$output .= '<iframe width="' . KU_YOUTUBEWIDTH . '" height="' . KU_YOUTUBEHEIGHT . '" src="https://www.youtube-nocookie.com/embed/' . $post['filename'] . '?rel=0" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>';
	} elseif ($post['filetype'] == 'goo') {
		$output .= '<script>' . "\n" .
		'document.write(\'<embed style="width:246px; height:205px;" id="VideoPlayback" type="application/x-shockwave-flash" src="http://video.google.com/googleplayer.swf?docId=' . $post['filename'] . '&hl=en" flashvars=""><\/embed>\');' . "\n" .
		'</script>' . "\n";
	}
	 elseif ($post['filetype'] == 'red') {
		$output .= '<object height="263" width="334"><param name="movie" value="http://embed.redtube.com/player/"><param name="FlashVars" value="id='. $post['filename'].'&style=redtube"><embed src="http://embed.redtube.com/player/?id='. $post['filename'].'&style=redtube" pluginspage="http://www.adobe.com/shockwave/download/download.cgi?P1_Prod_Version=ShockwaveFlash" type="application/x-shockwave-flash" height="263" width="334"></object>';
	}
	elseif ($post['filetype'] == '5min') {
		$output .= '<div style="text-align:center"><object width="246" height="205" id="FiveminPlayer"><param name="allowfullscreen" value="true"/><param name="allowScriptAccess" value="always"/><param name="movie" value="http://www.5min.com/Embeded/'. $post['filename'].'/"/><embed src="http://www.5min.com/Embeded/'. $post['filename'].'/" type="application/x-shockwave-flash" width="246" height="205" allowfullscreen="true" allowScriptAccess="always"></embed></object></div>';
	}
	
	$output .= '</span>&nbsp;' . "\n";
	
	return $output;
}

/**
 * Check if the supplied md5 file hash is currently recorded inside of the database, attached to a non-deleted post
 */
function checkMd5($md5, $board) {
	global $tc_db;
	
	$matches = $tc_db->GetAll("SELECT `id`, `parentid` FROM `".KU_DBPREFIX."posts_".mysqli_real_escape_string($tc_db->link, $board)."` WHERE `IS_DELETED` = 0 AND `filemd5` = '".mysqli_real_escape_string($tc_db->link, $md5)."' LIMIT 1");
	if (count($matches) > 0) {
		$real_parentid = ($matches[0][1] == 0) ? $matches[0][0] : $matches[0][1];
		
		return array($real_parentid, $matches[0][0]);
	}
	
	return false;
}

/* Image handling */
/**
 * Create a thumbnail
 *
 * @param string $name File to be thumbnailed
 * @param string $filename Path to place the thumbnail
 * @param integer $new_w Maximum width 
 * @param integer $new_h Maximum height
 * @return boolean Success/fail 
 */ 
function createThumbnail($name, $filename, $new_w, $new_h) {
	global $board_class;
	_log(sprintf("%s will be %s, dims %d x %d\n", $name, $filename, $new_w, $new_h));
	$filetype = preg_replace('/.*\.(.+)/', '\1', $name);
	if ($board_class->allowed_file_types[$filetype][0] == 'video'){
		$videowidth = exec('ffprobe -v quiet -show_entries stream=width -of default=noprint_wrappers=1:nokey=1 '. escapeshellarg($name));
		$videoheight = exec('ffprobe -v quiet -show_entries stream=height -of default=noprint_wrappers=1:nokey=1 '. escapeshellarg($name));
		
		$convert = 'ffmpeg -i '.escapeshellarg($name).' -q 1 -frames:v 1 ';
		if ( ($videowidth / $new_w) > ($videoheight / $new_h) ) {
			$convert .= '-vf "scale=' . $new_w . ':-1:flags=lanczos" ';
		} else {
			$convert .= '-vf "scale=-1:' . $new_h . ':flags=lanczos" ';
		}
		$convert .= escapeshellarg($filename);
		exec($convert);
		
		if (is_file($filename)) {
			return true;
		} else {
			return false;
		}
	} elseif (KU_THUMBMETHOD == 'imagemagick') {
		// ImageMagick v6.x does not have `magick` command, only `convert`:
		$convert = 'convert ' . $filetype . ':' . escapeshellarg($name);
		if ($filetype == 'gif' || $filetype == 'webp') { // animation processing:
			if (KU_ANIMATEDTHUMBS) {
				$convert .= ' -coalesce';
			} else {
				$convert .= '[0]'; // grab only the 0th frame of the animation
			}
		}
		$convert .= ' +profile "*"'; // removes ICM/EXIF/IPTC/other profiles,
		//see https://legacy.imagemagick.org/script/command-line-options.php#profile
		$convert .= ' -resize ' . $new_w . 'x' . $new_h . '\>'; // escape from shell
		//The `-quality` option is actually quite format-specific in ImageMagick,
		//see https://legacy.imagemagick.org/script/command-line-options.php#quality
		if ($filetype == 'png') {
			$convert .= ' -quality 95'; // 9 = zlib level 9; 5 = adaptive filter
		} elseif ($filetype == 'webp') {
			if (KU_ANIMATEDTHUMBS) { // need more quality to lessen the speckling:
				$convert .= ' -quality 93';
			} else $convert .= ' -quality 85'; // already smaller files than JPEG q=80
			// segment-smoothness-oriented luma-weighed chroma subsampling:
			$convert .= ' -define webp:method=6 -define webp:preprocessing=5';
		} elseif ($filetype != 'gif') {
			$convert .= ' -quality 80'; // does not make any sense to apply it to GIFs
		} else $convert .= ' -dither FloydSteinberg'; //change GIF dithering method,
		// see https://www.imagemagick.org/Usage/quantize/#dither_how for an example
		// (note: Floyd-Steinberg used instead of Riemersma to lessen the speckling)
		$convert .= ' ' . escapeshellarg($filename);
		exec($convert);
		
		if (is_file($filename)) {
			return true;
		} else {
			return false;
		}
	} elseif (KU_THUMBMETHOD == 'ffmpeg') {
		// original idea: https://nullnyan.net/b/thread/20928#P22458
		// original implementation: https://gitgud.io/devarped/instant-0chan/commit/351c5f0230ec52e2738a589c1ee0fefca08639b8
		// this code improves that, see https://bitbucket.org/Therapont/fbe-410/pull-requests/12 for the list of improvements
		
		$imagewidth = exec('ffprobe -v quiet -show_entries stream=width -of default=noprint_wrappers=1:nokey=1 '. escapeshellarg($name));
		$imageheight = exec('ffprobe -v quiet -show_entries stream=height -of default=noprint_wrappers=1:nokey=1 '. escapeshellarg($name));
		
		if ($filetype != 'gif') { // not GIF, ignores KU_ANIMATEDTHUMBS
			$convert = 'ffmpeg -f image2 -pattern_type none -i ' . escapeshellarg($name);
			if ( ($imagewidth / $new_w) > ($imageheight / $new_h) ) {
				$convert .= ' -vf "scale=' . $new_w . ':-1:flags=lanczos" ';
			} else {
				$convert .= ' -vf "scale=-1:' . $new_h . ':flags=lanczos" ';
			}
			if ($filetype == 'jpg') {
				$convert .= '-q 1 '; // 89%, see http://www.ffmpeg-archive.org/Create-high-quality-JPEGs-td4669205.html
			}
			$convert .= escapeshellarg($filename);
			exec($convert);
		} else { // high quality GIF, see http://blog.pkh.me/p/21-high-quality-gif-with-ffmpeg.html
			$palette = 'ffmpeg -f gif -i ' . escapeshellarg($name);
			$convert = 'ffmpeg -f gif -i ' . escapeshellarg($name);
			$convert .= ' -i ' . escapeshellarg($filename . '.palette.png');
			if (!KU_ANIMATEDTHUMBS) {
				$palette .= ' -vframes 1';
				$convert .= ' -vframes 1';
			}
			if ( ($imagewidth / $new_w) > ($imageheight / $new_h) ) {
				$palette .= ' -vf "scale=' . $new_w . ':-1:flags=lanczos,palettegen" ';
				$convert .= ' -lavfi "scale=' . $new_w . ':-1:flags=lanczos [x]; [x][1:v] paletteuse=dither=floyd_steinberg" ';
			} else {
				$palette .= ' -vf "scale=-1:' . $new_h . ':flags=lanczos,palettegen" ';
				$convert .= ' -lavfi "scale=-1:' . $new_h . ':flags=lanczos [x]; [x][1:v] paletteuse=dither=floyd_steinberg" ';
			}
			$palette .= escapeshellarg($filename . '.palette.png');
			exec($palette);
			$convert .= escapeshellarg($filename);
			exec($convert);
			unlink($filename . '.palette.png');
		}
		
		if (is_file($filename)) {
			return true;
		} else {
			return false;
		}
	} elseif (KU_THUMBMETHOD == 'gd') {
		$uploaded_parts = pathinfo($name);
		$type = $uploaded_parts['extension'];
		gd_create_thumbnail($name, $filename, $type, $new_w, $new_h);
/*		$system=explode(".", $filename);
		$system = array_reverse($system);
		if (preg_match("/jpg|jpeg/", $system[0])) {
			$src_img=imagecreatefromjpeg($name);
		} else if (preg_match("/png/", $system[0])) {
			$src_img=imagecreatefrompng($name);
		} else if (preg_match("/gif/", $system[0])) {
			$src_img=imagecreatefromgif($name);
		} else {
			return false;
		}
		
		if (!$src_img) {
			exitWithErrorPage(_gettext('Unable to read uploaded file during thumbnailing.'), _gettext('A common cause for this is an incorrect extension when the file is actually of a different type.'));
		}
		$old_x = imageSX($src_img);
		$old_y = imageSY($src_img);
		if ($old_x > $old_y) {
			$percent = $new_w / $old_x;
		} else {
			$percent = $new_h / $old_y;
		}
		$thumb_w = round($old_x * $percent);
		$thumb_h = round($old_y * $percent);
		
		$dst_img = ImageCreateTrueColor($thumb_w, $thumb_h);
		fastImageCopyResampled($dst_img, $src_img, 0, 0, 0, 0, $thumb_w, $thumb_h, $old_x, $old_y);
		
		if (preg_match("/png/", $system[0])) {
			if (!imagepng($dst_img, $filename)) {
				echo 'unable to imagepng.';
				return false;
			}
		} else if (preg_match("/jpg|jpeg/", $system[0])) {
			if (!imagejpeg($dst_img, $filename, 70)) {
				echo 'unable to imagejpg.';
				return false;
			}
		} else if (preg_match("/gif/", $system[0])) {
			if (!imagegif($dst_img, $filename)) { 
				echo 'unable to imagegif.';
				return false;
			}
		}
		
		imagedestroy($dst_img); 
		imagedestroy($src_img);
 */
		
		return true;
	}
	
	return false;
}

/* PART OF KOTOBA PROJECT */
/*
 * gd_create_thumbnail: create thumbnail from image
 * return boolean (false on unknown filetype)
 * arguments
 * $source: source file name
 * $destination: destination file name
 * $type: file type
 * $x and $y: dimensions of source image
 * $resize_x and $resize_y: dimensions of destination image
 */
function gd_create_thumbnail($source, $destination, $type, $resize_x, $resize_y) {
	switch(strtolower($type)) {
	case 'gif':
		return gif_gd_create($source, $destination, $resize_x, $resize_y);
		break;
	case 'jpeg':
	case 'jpg':
		return jpg_gd_create($source, $destination, $resize_x, $resize_y);
		break;
	case 'png':
		return png_gd_create($source, $destination, $resize_x, $resize_y);
		break;
	default:
		return false;
		break;
	}
}
/*
 * gd_resize: resize gd image object proportionaly
 * return new gd image object
 * arguments:
 * $img: source image gd object referenc
 * $size_x and $size_y: dimensions of destination image
 * $source: source file name
 * $destination: destination file name
 * $fill: fill image with transparent color
 * $blend: blend image with transparent color FIXME
*/
function gd_resize(&$img, $size_x, $size_y, $source, $destination, $fill = false, $blend = false) {
	if(!$img) {
		exitWithErrorPage(_gettext('Unable to read uploaded file during thumbnailing.'), _gettext('A common cause for this is an incorrect extension when the file is actually of a different type.'));
	}
	$x = imageSX($img);
	$y = imageSY($img);
	if($size_x < $x || $size_y < $y) {
		if($x >= $y) { // calculate proportions of destination image
			$ratio = $y / $x;
			$size_y = $size_y * $ratio;
		}
		else {
			$ratio = $x / $y;
			$size_x = $size_x * $ratio;
		}
	}

	$res = imagecreatetruecolor($size_x, $size_y);
	if($fill && $blend) { // png. slow on big images (need tests)
		imagealphablending($res, false);
		imagesavealpha($res, true);
		$transparent = imagecolorallocatealpha($res, 255, 255, 255, 127);
		imagefilledrectangle($res, 0, 0, $size_x, $size_y, $transparent);
	}
	elseif($fill && !$blend) { //gif
		$colorcount = imagecolorstotal($img);
		imagetruecolortopalette($res, true, $colorcount);
		imagepalettecopy($res, $img);
		$transparentcolor = imagecolortransparent($img);
		imagefill($res, 0, 0, $transparentcolor);
		imagecolortransparent($res, $transparentcolor);
	}
	imagecopyresampled($res, $img, 0, 0, 0, 0, $size_x, $size_y, $x, $y);
	return $res;
}

/*
 * functions xxx_gd_create: create resized file
 * one function for one image type (based on prefix)
 * return int FIXME
 * arguments:
 * $source: source file name
 * $destination: destination file name
 * $x and $y: dimensions of source image
 * $size_x and $size_y: dimensions of destination image
 */

function gif_gd_create($source, $destination, $resize_x, $resize_y) {
	$gif = imagecreatefromgif($source);
	$thumbnail = gd_resize($gif, $resize_x, $resize_y, $source, $destination, true, false);
	imagegif($thumbnail, $destination);

	imagedestroy($thumbnail);
	imagedestroy($gif);
	return true;
}
function jpg_gd_create($source, $destination, $resize_x, $resize_y) {
	$jpeg = imagecreatefromjpeg($source);
	$thumbnail = gd_resize($jpeg, $resize_x, $resize_y, $source, $destination, false,false);
	imagejpeg($thumbnail, $destination);
	imagedestroy($thumbnail);
	imagedestroy($jpeg);
	return true;
}
function png_gd_create($source, $destination, $resize_x, $resize_y) {
	$png = imagecreatefrompng($source);
	$thumbnail = gd_resize($png, $resize_x, $resize_y, $source, $destination, true ,true);
	imagepng($thumbnail, $destination);
	imagedestroy($thumbnail);
	imagedestroy($png);
	return true;
}

/* END OF PART OF KOTOBA PROJECT */

/* Author: Tim Eckel - Date: 12/17/04 - Project: FreeRingers.net - Freely distributable. */
/**
 * Faster method than only calling imagecopyresampled()
 *
 * @return boolean Success/fail 
 */ 
function fastImageCopyResampled(&$dst_image, &$src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h, $quality = 3) {
	/*
	Optional "quality" parameter (defaults is 3).  Fractional values are allowed, for example 1.5.
	1 = Up to 600 times faster.  Poor results, just uses imagecopyresized but removes black edges.
	2 = Up to 95 times faster.  Images may appear too sharp, some people may prefer it.
	3 = Up to 60 times faster.  Will give high quality smooth results very close to imagecopyresampled.
	4 = Up to 25 times faster.  Almost identical to imagecopyresampled for most images.
	5 = No speedup.  Just uses imagecopyresampled, highest quality but no advantage over imagecopyresampled.
	*/
	
	if (empty($src_image) || empty($dst_image)) { return false; }

	if ($quality <= 1) {
		$temp = imagecreatetruecolor ($dst_w + 1, $dst_h + 1);
		imagecopyresized ($temp, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w + 1, $dst_h + 1, $src_w, $src_h);
		imagecopyresized ($dst_image, $temp, 0, 0, 0, 0, $dst_w, $dst_h, $dst_w, $dst_h);
		imagedestroy ($temp);
	} elseif ($quality < 5 && (($dst_w * $quality) < $src_w || ($dst_h * $quality) < $src_h)) {
		
		$tmp_w = $dst_w * $quality;
		$tmp_h = $dst_h * $quality;
		$temp = imagecreatetruecolor ($tmp_w + 1, $tmp_h + 1);
		
		imagecopyresized ($temp, $src_image, $dst_x * $quality, $dst_y * $quality, $src_x, $src_y, $tmp_w + 1, $tmp_h + 1, $src_w, $src_h);
		
		imagecopyresampled ($dst_image, $temp, 0, 0, 0, 0, $dst_w, $dst_h, $tmp_w, $tmp_h);
		
		imagedestroy ($temp);
		
	} else {
		imagecopyresampled ($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
	}

	
	return true;
}

/**
 * Check if a thread is marked for deletion
 * 
 * @param string $post Post data
 * @param integer $maxage Maximum thread age
 * @return boolean Marked or not
 */ 
function checkMarkedForDeletion($post, $maxage) {
	if (!$post['stickied'] && $post['parentid'] == 0 && (($maxage > 0 && ($post['postedat']  + ($maxage * 3600)) < (time() + 7200)) || ($post['deletedat'] > 0 && $post['deletedat'] <= (time() + 7200)))) {
		return true;
	}
	
	return false;
}

function textBoardReplyBox($board, $forcedanon, $enablecaptcha, $numreplies = false, $threadid = false, $formid = '') {
	if ($threadid === false) {
		$threadid = '0';
	}
	$output = '<table class="postform">' . "\n";
	if ($numreplies === false) {
		$output .= textBoardReplyBoxSubject();
	}
	$output .= '<tr>' . "\n";
	if ($forcedanon != 1) {
		$output .= textBoardReplyBoxName() .
		textBoardReplyBoxEmail() .
		textBoardReplyBoxSubmit($board, $numreplies, $threadid, $formid) .
		'</tr>' . "\n" .
		'<tr>' . "\n";
		if ($enablecaptcha == 1) {
			$output .= textBoardReplyBoxCaptcha();
		}
		$output .= textBoardReplyBoxPassword();
	} else {
		$output .= textBoardReplyBoxEmail();
		if ($enablecaptcha == 1) {
			$output .= textBoardReplyBoxCaptcha();
		} else {
			$output .= textBoardReplyBoxPassword();
		}
		$output .= textBoardReplyBoxSubmit($board, $numreplies, $threadid, $formid);
		if ($enablecaptcha == 1) {
			$output .= '</tr>' . "\n" .
			'<tr>' . "\n" .
			textBoardReplyBoxPassword();
		}
	}
	$output .= '</tr>' . "\n" .
	'<tr style="display: none;" id="opt' . $threadid . '"><td></td></tr>' . "\n" .
	'<tr>' . "\n" .
	'	<td class="postfieldleft">' . "\n" .
	'		<span class="postnum">' . "\n";
	if ($numreplies !== false) {
		$output .= '			' . ($numreplies + 2) . "\n";
	} else {
		$output .= '			1' . "\n";
	}
	$output .= '		</span>' . "\n" .
	'	</td>' . "\n" .
	'	<td colspan="4">' . "\n" .
	'		<textarea name="message" rows="8" cols="64"></textarea>' . "\n" .
	'	</td>' . "\n" .
	'</tr>' . "\n" .
	'</table>' . "\n" .
	'<div id="preview' . $threadid . '"></div>' . "\n";
	
	return $output;
}

function textBoardReplyBoxSubject() {
	return '<tr>' . "\n" .
	'	<td class="label">' . "\n" .
	'		<label>' . _gettext('Subject').':</label>' . "\n" .
	'	</td>' . "\n" .
	'	<td colspan="4">' . "\n" .//
	'		<input name="subject" maxlength="75" size="50" style="width: 70%;">' . "\n" .
	'	</td>' . "\n" .
	'</tr>' . "\n";
}

function textBoardReplyBoxName() {
	return '	<td class="label">' . "\n" .
	'		<label>' . _gettext('Name').':</label>' . "\n" .
	'	</td>' . "\n" .
	'	<td>' . "\n" .
	'		<input name="name" size="25" maxlength="75">' . "\n" .
	'	</td>' . "\n";
}

function textBoardReplyBoxEmail() {
	return '	<td class="label">' . "\n" .
	'		<label>' . _gettext('Email') . ':</label>' . "\n" .
	'	</td>' . "\n" .
	'	<td>' . "\n" .
	'		<input name="em" size="25" maxlength="75">' . "\n" .
	'	</td>' . "\n";
}

function textBoardReplyBoxCaptcha() {
	return '<td class="label"><label for="captcha">'._gettext('Captcha').':</label></td>' . "\n" .
	'<td>' . "\n" .
	'	<a href="#" onclick="document.getElementById(\'captchaimage\').src = \'' . KU_CGIPATH . '/captcha.php?\' + Math.random();return false;">' . "\n" .
	'	<img id="captchaimage" src="' . KU_CGIPATH .'/captcha.php" border="0" width="90" height="30" alt="Captcha image">' . "\n" .
	'	</a>&nbsp;' . "\n" .
	'	<input type="text" id="captcha" name="captcha" size="8" maxlength="6">' . "\n" .
	'</td>' . "\n";
}

function textBoardReplyBoxPassword() {
	return  '	<td class="label">' . "\n" .
	'		<label>' . _gettext('Password') . ':</label>' . "\n" .
	'	</td>' . "\n" .
	'	<td>' . "\n" .
	'		<input type="password" name="postpassword" size="8" accesskey="p" maxlength="75">' . "\n" .
	'	</td>' . "\n";
}

function textBoardReplyBoxSubmit($board, $numreplies, $threadid, $formid) {
	$return = '	<td>' . "\n";
	if ($numreplies !== false) {
		$return .= '		<input type="submit" name="submit" value="' . _gettext('Reply') . '" class="submit">' . "\n";
	} else {
		$return .= '		<input type="submit" name="submit" value="' . _gettext('Submit') . '" class="submit">' . "\n";
	}
	$return .= '		<a href="#" onclick="toggleOptions(\'' . $threadid . '\', \'' . $formid . '\', \'' . $board . '\');return false;">' . _gettext('More') . '...</a>' . "\n" .
	'	</td>' . "\n";
	
	return $return;
}

/*
Link validator

Will use cURL to attempt to visit a webpage, and then return based upon how the
request was handled.  Used for embedded videos to validate the ID is existant.

Thanks phadeguy - http://www.zend.com/codex.php?id=1256&single=1
expects a link url as string
returns an array of three elements:
return_array[0] = HTTP version
return_array[1] = Returned error number (200, 404, etc)
return_array[2] = Returned error text ("OK", "File Not Found", etc) */
function check_link($link) {
	$main = array();
	$ch = curl_init();
	curl_setopt ($ch, CURLOPT_URL, $link);
	curl_setopt ($ch, CURLOPT_HEADER, 1);
	curl_setopt ($ch, CURLOPT_NOBODY, 1);
	curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt ($ch, CURLOPT_TIMEOUT, 10);
	curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
	ob_start();
	curl_exec ($ch);
	$stuff = ob_get_contents();
	ob_end_clean();
	curl_close ($ch);
	$parts = split("n",$stuff,2);
	$main = split(" ",$parts[0],3);
	return $main;
}
?>
