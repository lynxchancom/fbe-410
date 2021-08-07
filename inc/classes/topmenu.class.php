<?php

require_once "inc/func/html.php";

class TopMenu
{
    /**
     * Display top menu including board list
     *
     * @param Board $board
     * @return string
     */
    public static function TopMenuHtml($board = null) {
        $output = '<div class="topmenu"><div class="adminbar"><select name="switcher" onchange="set_stylesheet(this.value);reloadmenu();">' . "\n";
        if (KU_STYLESWITCHER) {
            $styles = explode(':', KU_STYLES);

            foreach ($styles as $stylesheet) {
                $output .= '<option value="' .ucfirst($stylesheet). '">' .ucfirst($stylesheet). "</option>\n";
            }
        }
        $output .= '</select>&nbsp;';
        if ($board) {
            if (KU_WATCHTHREADS) {
                $output .= '<a href="#" class="nav-btn nav-btn-wt" onclick="showwatchedthreads();return false" title="' . _gettext('Watched Threads') . '">' . svgIcon('wt', '32') . '</a>&nbsp;';
            }
            if ($board->board_enablearchiving == 1) {
                $output .= '<a class="nav-btn nav-btn-archive" href="' . KU_WEBPATH . '/' . $board->board_dir . '/arch/res/" title="Архив">' . svgIcon('archive', '32') . '</a>&nbsp;';
            }
            if ($board->board_enablecatalog == 1) {
                $output .= ($board->board_type != 1 && $board->board_type != 3) ? '<a class="nav-btn nav-btn-catalog" href="' . KU_BOARDSFOLDER . $board->board_dir . '/catalog.html" title="' . _gettext('View catalog') . '">' . svgIcon('catalog', '32') . '</a>&nbsp;' : '';
            }
        }

        $output .= '<a class="nav-btn nav-btn-search" href="'.KU_WEBPATH.'/search.php" title="Поиск">' . svgIcon('search', '32') . '</a>&nbsp;' .
            '<a class="nav-btn nav-btn-home" href="'.KU_WEBPATH.'/" target="_top" title="' . _gettext('Home') . '">' . svgIcon('mainpage', '32') . '</a>&nbsp;' .
            '<a class="nav-btn nav-btn-admin" href="' . KU_CGIPATH . '/manage.php" target="_top" title="' . _gettext('Manage') . '">' . svgIcon('admin', '32') . '</a></div>' . "\n";

        $output .= TopMenu::BoardListHtml(false);
        $output .= '</div>';

        return $output;
    }

    /**
     * Display the user-defined list of boards found in boards.html
     *
     * @param boolean $is_textboard If the board this is being displayed for is a text board
     * @return string The board list
     */
    public static function BoardListHtml($is_textboard = false) {
        $div_name = ($is_textboard) ? 'topbar' : 'navbar';

        if (KU_GENERATEBOARDLIST) {
            global $tc_db;

            $output = '';
            $results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "sections` ORDER BY `order` ASC");
            $board_sections = array();
            foreach($results AS $line) {
                $board_sections[] = $line['id'];
            }
            foreach ($board_sections as $board_section) {
                $board_this_section = '';
                $results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "boards` WHERE `section` = '" .  $board_section . "' ORDER BY `order` ASC");
                if (count($results) > 0) {
                    $output .= '[';
                    foreach($results AS $line) {
                        $board_this_section .= ' <a title="' . $line['desc'] . '" href="' . KU_BOARDSFOLDER . $line['name'] . '/">' . $line['name'] . '</a> /';
                    }
                    $board_this_section = substr($board_this_section, 0, strlen($board_this_section)-1);
                    $output .= $board_this_section;
                    $output .= '] ';
                }
            }

            return '<div class="'.$div_name.'">' . $output . '</div>';
        } else {
            if (is_file(KU_ROOTDIR . 'boards.html')) {
                return '<div class="'.$div_name.'">' . file_get_contents(KU_ROOTDIR . 'boards.html') . '</div>';
            } else {
                return '';
            }
        }
    }
}