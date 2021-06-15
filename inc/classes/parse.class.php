<?php
/*
* This file is part of kusaba.
*
*/

class Parse {
	var $boardtype;
	var $parentid;
	var $id;

	function urlcallback($matches) {
		return '<a target="_blank" rel="nofollow" href="'.$matches[1].$matches[2].'">'.$matches[1].urldecode($matches[2]).'</a>';
	}

	function exturlcallback($matches) {
		$text = strtr(urldecode($matches[1]), array('/' => '&#47;'));
		return '<a target="_blank" rel="nofollow" href="'.$matches[2].$matches[3].'">'.$text.'</a>';
	}

	function MakeClickable($txt) {
		$txt = preg_replace_callback('#«([^«»]*)»:(http://|https://|ftp://)([^(\s<|\[)]+(?:\([\w\d]+\)|([^[:punct:]«»\s]|/)))#u',array(&$this, 'exturlcallback'),$txt);
		$txt = preg_replace_callback('#(?<!href=")((?:http:|https:|ftp:)\/\/)([^(\s<|\[)]+(?:\([\w\d]+\)|([^[:punct:]«»\s]|\/)))#u',array(&$this, 'urlcallback'),$txt);
		return $txt;
	}

	function BBCode($string){
		$string = preg_replace_callback('#`(.+?)`#is', array(&$this, 'inline_code_callback'), $string);
#		$string = preg_replace_callback('`\[tex\](.+?)\[/tex\]`is', array(&$this, 'latex_callback'), $string);
		$string = preg_replace_callback('`((?:(?:(?:^[\-\*] )(?:[^\r\n]+))[\r\n]*)+)`m', array(&$this, 'bullet_list'), $string);
		$string = preg_replace_callback('`((?:(?:(?:[+\#] )(?:[^\r\n]+))[\r\n]*)?(?:(?:(?:^[+\#] )(?:[^\r\n]+))[\r\n]*)+)`m', array(&$this, 'number_list'), $string);

		$patterns = array(
			'`\*\*(.+?)\*\*`is', 
			'`\*(.+?)\*`is', 
			'`%%(.+?)%%`is', 
			'`\[b\](.+?)\[/b\]`is', 
			'`\[i\](.+?)\[/i\]`is', 
			'`\[u\](.+?)\[/u\]`is', 
			'`\[s\](.+?)\[/s\]`is', 
			'`\^\^(.+?)\^\^`is',
			'`\[aa\](.+?)\[/aa\]`is', 
			'`\[spoiler\](.+?)\[/spoiler\]`is', 
			'#`(.+?)`#',
		);
		$replaces =  array(
			'<b>\\1</b>', 
			'<i>\\1</i>',
			'<span class="spoiler">\\1</span>', 
			'<b>\\1</b>', 
			'<i>\\1</i>', 
			'<span style="border-bottom: 1px solid">\\1</span>', 
			'<strike>\\1</strike>', 
			'<strike>\\1</strike>', 
			'<span style="font-family: Mona,\'MS PGothic\' !important;">\\1</span>', 
			'<span class="spoiler">\\1</span>',
			'<span class="inline-code">\\1</span>',
		);
		$string = preg_replace($patterns, $replaces , $string);
		return $string;
	}

	function bullet_list($matches) {
		$output = '<ul>';
		$lines = explode(PHP_EOL,$matches[1]);
		foreach($lines as $line) {
			if(strlen($line))
			$output .= '<li>'.substr($line, 2).'</li>';
		}
		$output .= '</ul>';
		return $output;
	}

	function number_list($matches) {
		$output = '<ol>';
		$lines = explode(PHP_EOL,$matches[1]);
		foreach($lines as $line) {
			if(strlen($line))
			$output .= '<li>'.substr($line, 2).'</li>';
		}
		$output .= '</ol>';
		return $output;
	}


	function code_callback($matches) {
		$matches[1]=str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', $matches[1]);
		$tr = array( "["=>"&#91;", "]"=>"&#93;", "*"=>"&#42;", "%"=>"&#37;", "/"=>"&#47;", "&quot;"=>"&#34;", "-"=>"&#45;", ":"=>"&#58;", " "=>"&nbsp;", "#"=>"&#35;", "~"=>"&#126;",  "&#039;"=>"'", "&apos;"=>"'", "`"=>'&#96;', "&gt;"=>"&#62;", "&lt;"=>"&#60;" );
		$return = '<pre class="prettyprint">'.  strtr($matches[1],$tr) . '</pre>'; 
		return $return;
	}

	function inline_code_callback($matches) {
		$matches[1]=str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', $matches[1]);
		$tr = array( "["=>"&#91;", "]"=>"&#93;", "*"=>"&#42;", "%"=>"&#37;", "/"=>"&#47;", "&quot;"=>"&#34;", "-"=>"&#45;", ":"=>"&#58;", " "=>"&nbsp;", "#"=>"&#35;", "~"=>"&#126;",  "&#039;"=>"'", "&apos;"=>"'", "&gt;"=>"&#62;", "&lt;"=>"&#60;" );
		$return = '<pre class="inline-pp prettyprint">' . strtr($matches[1],$tr) . '</pre>'; 
		return $return;
	}


	function latex_callback($matches) {
	$tr = array( "["=>"&#91;", "]"=>"&#93;", "*"=>"&#42;", "%"=>"&#37;", "/"=>"&#47;", "&quot;"=>"&#34;", "-"=>"&#45;", ":"=>"&#58;");
		$return = '<span lang="latex">'
		. strtr($matches[1],$tr) .
		'</span>'; 
		return $return;
	}

	
	function ColoredQuote($buffer, $boardtype) {
		/* Add a \n to keep regular expressions happy */
		if (substr($buffer, -1, 1)!="\n") {
			$buffer .= "\n";
		}
	
		if ($boardtype==1) {
			/* The css for text boards use 'quote' as the class for quotes */
			$class = 'quote';
			$linechar = '';
		} else {
			/* The css for imageboards use 'unkfunc' (???) as the class for quotes */
			$class = 'unkfunc';
			$linechar = "\n";
		}
		$buffer = preg_replace('/^(&gt;[^>](.*))\n/m', '<span class="'.$class.'">\\1</span>' . $linechar, $buffer);
		/* Remove the > from the quoted line if it is a text board */
		if ($boardtype==1) {
			$buffer = str_replace('<span class="'.$class.'">&gt;', '<span class="'.$class.'">', $buffer);
		}
	
		return $buffer;
	}
	
	function ClickableQuote($buffer, $board, $boardtype, $parentid, $ispage = false) {
		global $thread_board_return;
		$thread_board_return = $board;

		/* Add html for links to posts in the board the post was made */
		$buffer = preg_replace_callback('/&gt;&gt;([0-9]+)/', array(&$this, 'InterthreadQuoteCheck'), $buffer);

		/* Add html for links to posts made in a different board */
		$buffer = preg_replace_callback('/&gt;&gt;\/([a-z]+)\/([0-9]+)/', array(&$this, 'InterboardQuoteCheck'), $buffer);

		return $buffer;
	}

	function InterthreadQuoteCheck($matches) {
		global $tc_db, $ispage, $thread_board_return;

		$return = "";

		if ($this->boardtype != 1) {
			$query = "SELECT `parentid` FROM `".KU_DBPREFIX."posts_".mysqli_real_escape_string($tc_db->link, $thread_board_return)."` WHERE `id` = '".mysqli_real_escape_string($tc_db->link, $matches[1])."'";
			$result = $tc_db->GetOne($query);
			$res_type = gettype($result);
			if ($result != NULL and $result !== '') {
				if ($result == 0) {
					$realid = $matches[1];
				} else {
					$realid = $result;
				}
			} else {
				return '&gt;&gt;' . $matches[1];
			}

//			$return = "[[btype != 1 ($query : {$res_type}$result)]]" . '<a href="'.KU_BOARDSFOLDER.$thread_board_return.'/res/'.$realid.'.html#'.$matches[1].'" class="ref|' . $thread_board_return . '|' .$realid . '|' . $matches[1] . '">'.$matches[0].'</a>';
			$return = formatQuote($thread_board_return, $realid, $matches[1], false);
		} else {
			$return = $matches[0];

			$postids = getQuoteIds($matches[1]);
			if (count($postids) > 0) {
				$realid = $this->parentid;
				if ($realid === 0) {
					if ($this->id > 0) {
						$realid = $this->id;
					}
				}
				if ($realid !== '') {
					$return = '<a href="' . KU_BOARDSFOLDER . 'read.php';
					if (KU_TRADITIONALREAD) {
						$return .= '/' . $thread_board_return . '/' . $realid.'/' . $matches[1];
					} else {
						$return .= '?b=' . $thread_board_return . '&t=' . $realid.'&p=' . $matches[1];
					}
					$return .= '">' . $matches[0] . '</a>';
				}
			}
		}

		return $return;
	}

	function InterboardQuoteCheck($matches) {
		global $tc_db;

		$result = $tc_db->GetOne("SELECT `type` FROM `".KU_DBPREFIX."boards` WHERE `name` = '".mysqli_real_escape_string($tc_db->link, $matches[1])."'");
		if ($result != '') {
			$result2 = $tc_db->GetOne("SELECT `parentid` FROM `".KU_DBPREFIX."posts_".mysqli_real_escape_string($tc_db->link, $matches[1])."` WHERE `id` = '".mysqli_real_escape_string($tc_db->link, $matches[2])."'");
			if ($result2 != '') {
				if ($result2 == 0) {
					$realid = $matches[2];
				} else {
					if ($result != 1) {
						$realid = $result2;
					}
				}

				if ($result != 1) {
					return formatQuote($matches[1], $realid, $matches[2], true);
				} else {
					return '<a href="'.KU_BOARDSFOLDER.$matches[1].'/res/'.$realid.'.html" class="ref|' . $matches[1] . '|' . $realid . '|' . $realid . '">'.$matches[0].'</a>';
				}
			}
		}

		return $matches[0];
	}

	function Wordfilter($buffer, $board) {
		global $tc_db;
		
		$query = "SELECT * FROM `".KU_DBPREFIX."wordfilter`";
		$results = $tc_db->GetAll($query);
		foreach($results AS $line) {
			$array_boards = explode('|', $line['boards']);
			if (in_array($board, $array_boards)) {
				$replace_word = $line['word'];
				$replace_replacedby = $line['replacedby'];
				
				$buffer = ($line['regex'] == 1) ? preg_replace($replace_word, $replace_replacedby, $buffer) : str_ireplace($replace_word, $replace_replacedby, $buffer);
			}
		}
		
		return $buffer;
	}

	function CheckNotEmpty($buffer) {
		$buffer_temp = str_replace("\n", "", $buffer);
		$buffer_temp = str_replace("<br>", "", $buffer_temp);
		$buffer_temp = str_replace("<br/>", "", $buffer_temp);
		$buffer_temp = str_replace("<br />", "", $buffer_temp);

		$buffer_temp = str_replace(" ", "", $buffer_temp);
		
		if ($buffer_temp=="") {
			return "";
		} else {
			return $buffer;
		}
	}
	function CutWord($txt, $where) {
		$txt_split_primary = preg_split('/\n/', $txt);
		$txt_processed = '';
		$usemb = (function_exists('mb_substr') && function_exists('mb_strlen')) ? true : false;
		
		foreach ($txt_split_primary as $txt_split) {
			$txt_split_secondary = preg_split('/ /', $txt_split);
			
			foreach ($txt_split_secondary as $txt_segment) {
				$segment_length = ($usemb) ? mb_strlen($txt_segment) : strlen($txt_segment);
				while ($segment_length > $where) {
					if ($usemb) {
						$txt_processed .= mb_substr($txt_segment, 0, $where) . "\n";
						$txt_segment = mb_substr($txt_segment, $where);
						
						$segment_length = mb_strlen($txt_segment);
					} else {
						$txt_processed .= substr($txt_segment, 0, $where) . "\n";
						$txt_segment = substr($txt_segment, $where);
						
						$segment_length = strlen($txt_segment);
					}
				}
				
				$txt_processed .= $txt_segment . ' ';
			}
			
			$txt_processed = ($usemb) ? mb_substr($txt_processed, 0, -1) : substr($txt_processed, 0, -1);
			$txt_processed .= "\n";
		}
		
		return $txt_processed;
	}
	

	function ParsePost($message, $board, $boardtype, $parentid) {
		$this->boardtype = $boardtype;
		$this->parentid = $parentid;

		$message = trim($message);
		$message = $this->CutWord($message, (KU_LINELENGTH / 15));
		$message = htmlspecialchars($message, ENT_QUOTES);
		
		$message = $this->BBCode($message);
		$message = $this->ClickableQuote($message, $board, $boardtype, $parentid);
		$message = $this->ColoredQuote($message, $boardtype);

		$message = str_replace("\n", '<br />', $message);
		$message = preg_replace('#(<br(?: \/)?>\s*){3,}#i', '<br /><br />', $message);

		$message = $this->CheckNotEmpty($message);
		$message = $this->Wordfilter($message, $board);

		if (KU_MAKELINKS) {
			$message = $this->MakeClickable($message);
		}		

		// $message = $this->Smileys($message); 

		return $message;
	}
}
?>
