<?php
/**
 * Plugin : QueryChangelog
 * Version : 1 (05/05/2009)
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Vincent de Lagabbe <vincent@delagabbe.com>
 * @based_on   "userhistory" plugin by Ondra Zara <o.z.fw@seznam.cz>
 * @based_on   "pagemove" plugin by Gary Owen <gary@isection.co.uk>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'admin.php');
require_once(DOKU_INC.'inc/search.php');


class admin_plugin_querychangelog extends DokuWiki_Admin_Plugin {
    var $show_form = true;
    var $errors = array();
    var $opts = array();
    var $changes = array();

    /**
     * function constructor
     */
    function admin_plugin_querychangelog(){
      // enable direct access to language strings
      $this->setupLocale();
    }

    /**
     * return some info
     */
    function getInfo(){
      return array(
        'author' => 'Vincent de Lagabbe',
        'email'  => 'vincent@delagabbe.com',
        'date'   => '2009-05-05',
        'name'   => 'QueryChangelog',
        'desc'   => $this->lang['desc'],
        'url'    => 'http://wiki.splitbrain.org/plugin:querychangelog',
      );
    }

    function forAdminOnly(){ return false; }
    function getMenuText() {
        return $this->lang['menu'];
    }

    /**
     * return sort order for position in admin menu
     */
    function getMenuSort() {
      return 999;
    }

    /**
     * handle user request
     *
     * @author  Vincent de Lagabbe <vincent@delagabbe.com>
     */
    function handle() {
        global $lang;
        global $ID;
        global $opts;
        global $conf;

        $opts['ns'] = getNS($ID);

        $opts['startd'] = isset($_REQUEST['startd']) ? $_REQUEST['startd'] : "YYYY-MM-DD HH:MM";
        $opts['endd'] = isset($_REQUEST['endd']) ? $_REQUEST['endd'] : "YYYY-MM-DD HH:MM";

        if (isset($_REQUEST['base_ns'])) {
            if (!checkSecurityToken())
                return;
            // get begining date
            $date_from = 0;
            if ($_REQUEST['qcsd'] == '<def>') {
                $date_from = $this->_qc_getstamp($opts['startd']);
                if (!$date_from) {
                    $this->errors[] = $this->lang['qc_err_date'];
                }
            }
            // get ending date
            $date_to = time();
            if ($_REQUEST['qced'] == '<def>') {
                $date_to = $this->_qc_getstamp($opts['endd']);
                if (!$date_to) {
                    $this->errors[] = $this->lang['qc_err_date'];
                }
            }

            if ($date_from && $date_to - $date_from <= 0) {
                $this->errors[] = $this->lang['qc_err_period'];
            }

            // get namespace
            $opts['base_ns'] = $_REQUEST['base_ns'];
            $base_dir = $this->_ns2path($opts['base_ns']);
            if (count($this->errors) == 0) {
                // get users
                if (!in_array(".", $_REQUEST['qcusers'])) {
                    $opts['users'] = $_REQUEST['qcusers'];
                }

                $opts['major_only'] = $_REQUEST['qcmo'] == '<on>';
                $opts['date_from'] = $date_from;
                $opts['date_to'] = $date_to;
                $this->changes = $this->_getChanges($base_dir);
                $this->show_form = false;
            }
            if ($_REQUEST['as_csv']) {
                $this->_generate_csv();
            }
        }

    }

    /**
     * output appropriate html
     *
     * @author  Vincent de Lagabbe <vincent@delagabbe.com>
     */
    function html() {
        global $lang;
        global $conf;
        global $ID;
        global $opts;
        global $auth;

        ptln('<!-- QueryChangelog Plugin start -->');
        if ($this->show_form) {
            ptln($this->locale_xhtml('querychangelog'));
            $this->_qc_form();
        } else {
            $href = wl($ID).'?do=admin&amp;page='.$this->getPluginName();
            ptln('<p><a href="'.$href.'">['.$this->lang['qc_back'].']</a></p>');

            // Display the query settings
            $start_date = $opts['date_from'] == 0 ? $this->lang['qc_begining'] : strftime($conf['dformat'], $opts['date_from']);
            ptln($this->lang['qc_res_from'].': '.$start_date.'<br/>');
            ptln($this->lang['qc_res_to'].': '.strftime($conf['dformat'], $opts['date_to']).'<br/>');
            ptln($this->lang['qc_res_ns'].': '.$opts['base_ns'].'<br/>');
            $str_list = "";
            if (isset($opts['users'])) {
                $user_list = $auth->retrieveUsers();
                foreach ($user_list as $user => $info) {
                    if (in_array($user, $opts['users'])) {
                        $str_list .= ($info['name'].', ');
                    }
                }
                $str_list = substr($str_list, 0, -2);
            } else {
                $str_list = $this->lang['qc_res_all'];
            }
            ptln($this->lang['qc_res_users'].': '.$str_list.'<br/>');
            ptln('<h2>'.$this->lang['qc_res_title'].'</h2>');

            // Display the changelog
            if (count($this->changes) == 0) {
                ptln($this->lang['qc_res_nc'].'<p/>');
            } else {
                $this->_show_changes();
            }
        }
        ptln('<!-- QueryChangelog Plugin end -->');
    }


    /**
     * show the query changelog form
     *
     * @author  Gary Owen <gary@isection.co.uk>
     * @author  Vincent de Lagabbe <vincent@delagabbe.com>
     */
    function _qc_form() {
        global $ID;
        global $lang;
        global $conf;
        global $opts;
        global $auth;

        ptln('  <div align="center">');
        ptln('  <script language="Javascript">');
        ptln('      function setradio( group, choice ) {');
        ptln('        for ( i = 0 ; i < group.length ; i++ ) {');
        ptln('          if ( group[i].value == choice )');
        ptln('            group[i].checked = true;');
        ptln('        }');
        ptln('      }');
        ptln('  </script>');
        ptln('  <form name="frm" action="'.wl($ID).'" method="post">');
        formSecurityToken();
        ptln('  <fieldset>');
        // output hidden values to ensure dokuwiki will return back to this plugin
        ptln('    <input type="hidden" name="do"   value="admin" />');
        ptln('    <input type="hidden" name="page" value="'.$this->getPluginName().'" />');
        ptln('    <input type="hidden" name="id" value="'.$ID.'" />');
        ptln('    <table border="0">');

        //Show any errors
        if (count($this->errors) > 0) {
            ptln ('<tr><td bgcolor="red" colspan="5">');
            foreach($this->errors as $error)
                ptln ($error.'<br>');
            ptln ('</td></tr>');
        }
        // Start and End dates Selection
        ptln( '      <tr><td align="right" rowspan="2" nowrap><label><span>'.$this->lang['qc_from'].':</span></label></td>');
        ptln( '        <td><input type="radio" name="qcsd" value="<def>" '.($_REQUEST['qcsd'] == '<def>' ? 'CHECKED' : '').'></td>');
        ptln( '        <td align="left"><input type="text" name="startd" size="25" maxlength="16" value="'.formtext($opts['startd']).'" class="edit" onChange="setradio(document.frm.qcsd, \'<new>\');" /></td></tr>');
        ptln( '      <tr><td><input type="radio" name="qcsd" value="<beg>" '.($_REQUEST['qcsd'] != '<def>' ? 'CHECKED' : '').'></td>');
        ptln( '        <td align="left">'.$this->lang['qc_begining'].'</td>');
        ptln( '      </tr>');
        ptln( '      <tr><td>&nbsp;</td></tr>');

        ptln( '      <tr><td align="right" rowspan="2" nowrap><label><span>'.$this->lang['qc_to'].':</span></label></td>');
        ptln( '        <td><input type="radio" name="qced" value="<def>" '.($_REQUEST['qced'] == '<def>' ? 'CHECKED' : '').'></td>');
        ptln( '        <td align="left"><input type="text" name="endd" size="25" maxlength="16" value="'.formtext($opts['endd']).'" class="edit" onChange="setradio(document.frm.qced, \'<new>\');" /></td></tr>');
        ptln( '      <tr><td><input type="radio" name="qced" value="<beg>" '.($_REQUEST['qced'] != '<def>' ? 'CHECKED' : '').'></td>');
        ptln( '        <td align="left">'.$this->lang['qc_now'].'</td>');
        ptln( '      </tr>');
        ptln( '      <tr><td colspan="5">&nbsp;</td></tr>');


        // NS Selection
        $namesp = array( 0 => '' );     //Include root in namespace list
        search($namesp,$conf['metadir'],'search_namespaces',$opts);
        sort($namesp);
        ptln( '      <tr>');
        ptln( '        <td align="right" nowrap><label><span>'.$this->lang['qc_base_ns'].':</span></label></td>');
        ptln( '        <td>&nbsp;</td>');
        ptln( '        <td colspan="3" align="left"><select name="base_ns">');
        foreach($namesp as $row) {
            if ( auth_quickaclcheck($row['id'].':*') >= AUTH_CREATE or $row['id'] == $opts['ns'] ) {
                ptln ( '          <option value="'.$row['id'].
                       ($_REQUEST['base_ns'] ?
                        (($row['id'] ? $row['id'] : ":") == $_REQUEST['base_ns'] ? '" SELECTED>' : '">') :
                        ($row['id'] == $opts['ns'] ? '" SELECTED>' : '">') ).
                       ($row['id'] ? $row['id'].':' : ": ".$this->lang['qc_root']).
                       ($row['id'] == $opts['ns'] ? ' '.$this->lang['qc_current'] : '').
                       "</option>" );
            }
        }
        ptln( '          </select></td>');
        ptln( '      </tr>');
        ptln( '      <tr><td colspan="5">&nbsp;</td></tr>');

        // User(s) selection
        ptln( '      <tr>');
        ptln( '        <td align="right" nowrap><label><span>'.$this->lang['qc_users'].':</span></label></td>');
        ptln( '        <td>&nbsp;</td>');
        ptln( '        <td colspan="3" align="left"><select name="qcusers[]" size="10" multiple="yes">');
        ptln ( '          <option value="." SELECTED>'.$this->lang['qc_all_users'].'</option>');
        $user_list = $auth->retrieveUsers();
        foreach ($user_list as $user => $info) {
            ptln ( '          <option value="'.$user.'">'.$info['name'].' ('.$user.')</option>');
        }
        ptln( '          </select></td>');
        ptln( '      </tr>');
        ptln( '      <tr><td colspan="5">&nbsp;</td></tr>');

        // Major changes / all changes switch
        ptln( '      <tr>');
        ptln( '        <td align="right" nowrap><label><span>'.$this->lang['qc_major_only'].':</span></label></td>');
        ptln( '        <td><input type="checkbox" name="qcmo" value="<on>" '.($_REQUEST['qcmo'] == '<on>' ? 'checked="checked"' : '').'"></td>');
        ptln( '      </tr>');
        ptln( '      <tr>');
        ptln( '        <td align="right" nowrap><label><span>'.$this->lang['qc_as_csv'].':</span></label></td>');
        ptln( '        <td><input type="checkbox" name="as_csv" value="<on>"></td>');
        ptln( '      </tr>');
        ptln( '      <tr><td colspan="5">&nbsp;</td></tr>');

        // Submit
        ptln( '      <tr>');
        ptln( '        <td colspan="5" align="center"><input type="submit" value="'.formtext($this->lang['qc_submit']).'" class="button" /></td>');
        ptln( '      </tr>');
        ptln( '    </table>');
        ptln( '  </fieldset>');
        ptln( '</form>');
        ptln( '</div>');
    }

    /**
     * Convert namespace to its corresponding path in meta
     */
    function _ns2path($ns) {
        global $conf ;

        if ($ns == ':' || $ns == '')
            return $conf ['metadir'] ;
        $ns = trim ($ns, ':') ;
        $path = $conf ['metadir'] . '/' . str_replace (':', '/', $ns) ;

        return $path ;
    }

    /**
     * Convert the user input date into a UNIX timestamps.
     *
     * @author  Vincent de Lagabbe <vincent@delagabbe.com>
     */
    function _qc_getstamp($str_date) {
        $date = array();
        $tstamp = false;

        if (0 == preg_match('/([0-9]{4})-([01][0-9])-([0-3][0-9]) ([0-2][0-9]):([0-6][0-9])/', $str_date, $date)) {
            return false;
        }
        $tstamp = mktime($date[4], $date[5], 0, $date[2], $date[3], $date[1]);
        return $tstamp;
    }

    /**
     * Get the changelogs entries according to the user query
     *
     * @author  Ondra Zara <o.z.fw@seznam.cz>
     * @author  Vincent de Lagabbe <vincent@delagabbe.com>
     */
    function _getChanges($base_dir, $user = 0) {
		global $conf;
        global $opts;

        function globr($dir, $pattern) {
			$files = glob($dir.'/'.$pattern);
			foreach (glob($dir.'/*', GLOB_ONLYDIR) as $subdir) {
				$subfiles = globr($subdir, $pattern);
				$files = array_merge($files, $subfiles);
			}
			return $files;
		}

		$changes = array();
		$alllist = globr($base_dir, '*.changes');
		$skip = array('_comments.changes', '_dokuwiki.changes');

		for ($i = 0; $i < count($alllist); $i++) {
			$fullname = $alllist[$i];
			if (in_array(basename($fullname), $skip)) {
                continue;
            }
			$f = file($fullname);
			for ($j = 0; $j < count($f); $j++) {
				$change = parseChangelogLine($f[$j]);
                if ($opts['major_only'] && $change['type'] === DOKU_CHANGE_TYPE_MINOR_EDIT) {
                    continue;
                } elseif (isset($opts['users']) && !in_array($change['user'], $opts['users'])) {
                    continue;
                } if ($change['date'] > $opts['date_from'] &&
                    $change['date'] < $opts['date_to']) {
                    $changes[] = $change;
                }
			} /* for all lines */
		} /* for all files */

		function cmp($a,$b) {
			$time1 = $a['date'];
			$time2 = $b['date'];
			if ($time1 == $time2) { return 0; }
			return ($time1 < $time2 ? 1 : -1);
		}

		uasort($changes, 'cmp');

		return $changes;
    }

    /**
     * Display the changes as DK
     *
     * @author  Ondra Zara <o.z.fw@seznam.cz>
     * @author  Vincent de Lagabbe <vincent@delagabbe.com>
     */
    function _show_changes() {
        global $conf;
        global $lang;

        ptln('<div class="level2">');
		ptln('<ul>');

		foreach($this->changes as $change){
			$date = strftime($conf['dformat'], $change['date']);
			ptln($change['type']==='e' ? '<li class="minor">' : '<li>');
			ptln('<div class="li">');

			ptln($date.' ');

			ptln('<a href="'.wl($change['id'],"do=diff&rev=".$change['date']).'">');
			$p = array();
			$p['src']    = DOKU_BASE.'lib/images/diff.png';
			$p['width']  = 15;
			$p['height'] = 11;
			$p['title']  = $lang['diff'];
			$p['alt']    = $lang['diff'];
			$att = buildAttributes($p);
			ptln("<img $att />");
			ptln('</a> ');

			ptln('<a href="'.wl($change['id'],"do=revisions").'">');
			$p = array();
			$p['src']    = DOKU_BASE.'lib/images/history.png';
			$p['width']  = 12;
			$p['height'] = 14;
			$p['title']  = $lang['btn_revs'];
			$p['alt']    = $lang['btn_revs'];
			$att = buildAttributes($p);
			ptln("<img $att />");
			ptln('</a> ');

			ptln(html_wikilink(':'.$change['id'],$conf['useheading'] ? NULL : $change['id']));
			ptln(' &ndash; '.hsc($change['sum']));

            ptln('<span class="user" >'.$change['user'].' ('.$change['ip'].')</span>');

			ptln('</div>');
			ptln('</li>');
		}
		ptln('</ul>');

		ptln('</div>');
    }

    /**
     * Generate CSV data from the list of changes
     *
     * @author  Emmanuel Benoît <tseeker@nocternity.net>
     */
    function _generate_csv() {
        global $conf;
        global $lang;
        global $auth;
        global $ID;

        $titles = [];
        $users = [];
        $xhtml_renderer = p_get_renderer('xhtml');

        header('Content-type: text/csv;charset=utf-8');
        header('Content-Disposition: attachment; filename="changes.csv"');
        $fd = fopen('php://output', 'w');
        fputcsv($fd, [
            'date', 'time', 'minor', 'pageid', 'pagetitle', 'userid', 'username', 'ip'
        ]);
        foreach($this->changes as $change){
            $id = $change['id'];
            $user = $change['user'];
            if (!array_key_exists($id, $titles)) {
                resolve_pageid(getNS($id), $id, $exists, $change['date'], true);
                $titles[$id] = p_get_first_heading($id);
            }
            if (!array_key_exists($user, $users)) {
                $users[$user] = $auth->getUserData($user);
            }
            fputcsv($fd, [
                strftime('%y-%m-%d', $change['date']),
                strftime('%T', $change['date']),
                $change['type'] === 'e' ? 'y' : 'n',
                $id, $titles[$id],
                $user, $users[$user]['name'],
                $change['ip'],
            ]);
        }
        fclose($fd);
        die;
    }
}
