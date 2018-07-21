<?php
/*
 * This file is part of kusaba.
 *
 * kusaba is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * kusaba is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * kusaba; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
/** 
 * Faptcha system
 *
 * User must identify a dick, vagoo, ass or tits
 * 
 * @package kusaba  
 */

session_start();

$board = strval($_GET['board']);

switch(strtolower($board)) {
	case 'raeateringplubs':
	$dir = "testmapcha";
	break;
	case 'tm':
	$dir = "tmaptcha";
	break;
	case 'cu':
	$dir = "cuptcha";
	break;
	case 'int':
	$dir = "flagapcha";
	break;
	case 'dev':
	$dir = "devapcha";
	break;
	case 'gnx':
	$dir = "gainapcha";
	break;
	default:
	$dir = "animapcha";
	break;
}


# $dir = 'animapcha' . '/';
$dh  = opendir($dir);
$bad_ext = array("db" =>1,"txt"=>1);
while (false !== ($filename = readdir($dh))) {
	if($filename == 'index.html') {
		continue;
	}
	$ext =  explode('.',$filename);
	if(!isset($bad_ext[$ext[1]]) and !is_dir($filename)){
	   $files[] = $filename;
	}
}	
closedir($dh);

$brightness = rand(0,50);
$nooffildi = count($files);
$nooffiles = ($nooffildi-1);
srand((double)microtime()*1000000);
$randnum = rand(0,$nooffiles);
$file = $dir . '/'. $files[$randnum];	
$filename = $files[$randnum];
$type = explode('_',$filename);
$type = $type[0];
#  Get aliases list 
$namekey = mb_strtolower($type, "UTF-8");
$aliases_list = array($namekey => 1);
if (is_file($dir.'/'.$type.'.txt'))
{
	$alist = file_get_contents($dir.'/'.$type.'.txt');
	$alist = explode(' ',$alist);
	foreach($alist as $i => $al)
	{
		$aliases_list[mb_strtolower(trim($al), "UTF-8")]=1;
	}
}
# all images should be 80x40 and their name should begin with they key;
$_SESSION['faptcha_type'] = $aliases_list;
$image = imagecreatefrompng($file);
$width = 90;
$height = 50;
$pointx = rand(0, $width - 1);
$pointy = rand(0, $height - 1);
$pointc = @imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));

@imagesetpixel($image, $pointx, $pointy, $pointc);

	header('Content-Type: image/png');
	imagepng($image);
?>
