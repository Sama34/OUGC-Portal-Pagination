<?php

/***************************************************************************
 *
 *   OUGC Portal Pagination plugin (/inc/plugins/ougc_portalfeed.php)
 *	 Author: Omar Gonzalez
 *   Copyright: © 2012 Omar Gonzalez
 *   
 *   Website: http://community.mybb.com/user-25096.html
 *
 *  Shows a pagination into the forum portal page.
 *
 ***************************************************************************
 
****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

// Die if IN_MYBB is not defined, for security reasons.
defined('IN_MYBB') or die('This file cannot be accessed directly.');

// Run our hook.
if(!defined('IN_ADMINCP') && defined('THIS_SCRIPT') && THIS_SCRIPT == 'portal.php')
{
	global $templatelist, $settings;

	// All right, so what if fid = -1? Lest make that equal to all forums
	if($settings['portal_announcementsfid'] == '-1')
	{
		global $forum_cache;
		$forum_cache or cache_forums();

		$fids = array(0);
		foreach($forum_cache as $forum)
		{
			if($forum['type'] == 'f' && $forum['active'] == 1 && $forum['open'] == 1)
			{
				$fids[] = (int)$forum['fid'];
			}
		}
		$settings['portal_announcementsfid'] = implode(',', array_unique($fids));
	}

	$plugins->add_hook('portal_start', 'ougc_portalpagination');

	if(isset($templatelist))
	{
		$templatelist .= ', ';
	}
	else
	{
		$templatelist = '';
	}

	$templatelist .= 'multipage_page_current, multipage_page, multipage_nextpage, multipage_prevpage, multipage_start, multipage_end, multipage';
}

//Necessary plugin information for the ACP plugin manager.
function ougc_portalpagination_info()
{
	return array(
		'name'			=> 'OUGC Portal Pagination',
		'description'	=> 'Shows a pagination into the forum portal page.',
		'website'		=> 'http://mods.mybb.com/profile/25096',
		'author'		=> 'Omar Gonzalez',
		'authorsite'	=> 'http://community.mybb.com/user-25096.html',
		'version'		=> '1.0',
		'compatibility'	=> '16*',
		'guid'			=> ''
	);
}

// Add the multipage variable
function ougc_portalpagination_activate()
{
	ougc_portalpagination_deactivate();
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('portal', '#'.preg_quote('{$announcements}').'#', '{\$announcements}{\$multipage}');
}

// Remove the multipage variable
function ougc_portalpagination_deactivate()
{
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('portal', '#'.preg_quote('{$multipage}').'#', '',0);
}

// Do the proccess for the pagination and all.
function ougc_portalpagination()
{
	global $mybb, $db, $sip_active;

	$page = (isset($mybb->input['page']) ? (int)$mybb->input['page'] : 1);
	($page < 1) or ($page = 1);

	$numannouncements = (int)$mybb->settings['portal_numannouncements'];
	if($numannouncements < 1)
	{
		$mybb->settings['portal_numannouncements'] = $numannouncements = 10;
	}

	// Build a where clause
	$where = 'fid IN (\''.implode('\',\'', array_unique(array_map('intval', explode(',', $mybb->settings['portal_announcementsfid'])))).'\') AND visible=\'1\' AND closed NOT LIKE \'moved|%\'';

	if($unviewableforums = get_unviewable_forums(true))
	{
		$where .= ' AND t.fid NOT IN('.$unviewableforums.')';
	}

	/*if($inactiveforums = get_inactive_forums())
	{
		$where .= ' AND t.fid NOT IN('.$inactiveforums.')';
	}*/

	// OUGC Show In Portal
	if(!isset($sip_active))
	{
		$plugins = $mybb->cache->read('plugins');
		$sip_active = !empty($plugins['active']['ougc_showinportal']);
		unset($plugins);
	}
	if($sip_active)
	{
		$where .= ' AND t.showinportal=\'1\'';
	}

	// We need to query them to get the tid list and thread count
	$threads = $db->fetch_field($db->simple_select('threads', 'COUNT(tid) AS threads', $where), 'threads');

	global $multipage;

	$multipage = multipage($threads, $numannouncements, $page, $_SERVER['PHP_SELF']);
	$multipage or ($multipage = '');

	ougc_portalpagination_control_object($db, '
		function query($string, $hide_errors=0, $write_query=0)
		{
			if(!$write_query && strpos($string, \'SELECT p.pid, p.message, p.tid, p.smilieoff\') && strpos($string, \'LIMIT 0,\'))
			{
				$string = strtr($string, array(
					\'LIMIT 0,\' => \'LIMIT '.(($page-1)*$numannouncements).',\'
				));
			}
			return parent::query($string, $hide_errors, $write_query);
		}
	');
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com ), 1.62
function ougc_portalpagination_control_object(&$obj, $code)
{
	static $cnt = 0;
	$newname = '_objcont_'.(++$cnt);
	$objserial = serialize($obj);
	$classname = get_class($obj);
	$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
	$checkstr_len = strlen($checkstr);
	if(substr($objserial, 0, $checkstr_len) == $checkstr)
	{
		$vars = array();
		// grab resources/object etc, stripping scope info from keys
		foreach((array)$obj as $k => $v)
		{
			if($p = strrpos($k, "\0"))
			{
				$k = substr($k, $p+1);
			}
			$vars[$k] = $v;
		}
		if(!empty($vars))
		{
			$code .= '
				function ___setvars(&$a) {
					foreach($a as $k => &$v)
						$this->$k = $v;
				}
			';
		}
		eval('class '.$newname.' extends '.$classname.' {'.$code.'}');
		$obj = unserialize('O:'.strlen($newname).':"'.$newname.'":'.substr($objserial, $checkstr_len));
		if(!empty($vars))
		{
			$obj->___setvars($vars);
		}
	}
	// else not a valid object or PHP serialize has changed
}