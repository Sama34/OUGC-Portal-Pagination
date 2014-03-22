<?php

/***************************************************************************
 *
 *   OUGC Portal Pagination plugin (/inc/plugins/ougc_portalpagination.php)
 *	 Author: Omar Gonzalez
 *   Copyright: © 2012-2014 Omar Gonzalez
 *   
 *   Website: http://omarg.me
 *
 *  Adds pagination to your forum portal page.
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

// Run/Add Hooks
if(THIS_SCRIPT == 'portal.php')
{
	global $templatelist, $settings;

	// All right, so what if fid = -1? Lest make that equal to all forums
	if($settings['portal_announcementsfid'] == '-1')
	{
		global $forum_cache;
		$forum_cache or cache_forums();

		$fids = array();
		foreach($forum_cache as $forum)
		{
			if($forum['type'] == 'f' && $forum['active'] == 1 && $forum['open'] == 1)
			{
				$fids[(int)$forum['fid']] = (int)$forum['fid'];
			}
		}
		$settings['portal_announcementsfid'] = implode(',', array_unique($fids));
	}

	$plugins->add_hook('portal_start', 'ougc_portalpagination_run');

	if(!isset($templatelist))
	{
		$templatelist = '';
	}
	else
	{
		$templatelist .= ',';
	}

	$templatelist .= 'multipage_page_current, multipage_page, multipage_nextpage, multipage_prevpage, multipage_start, multipage_end, multipage';
}

// PLUGINLIBRARY
defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');

// Plugin API
function ougc_portalpagination_info()
{
	global $lang;
	ougc_portalpagination_lang_load();

	return array(
		'name'			=> 'OUGC Portal Pagination',
		'description'	=> $lang->ougc_portalpagination_desc,
		'website'		=> 'http://omarg.me',
		'author'		=> 'Omar G.',
		'authorsite'	=> 'http://omarg.me',
		'version'		=> '1.1',
		'versioncode'	=> 1100,
		'compatibility'	=> '16*',
		'guid' 			=> '',
		'pl'			=> array(
			'version'	=> 12,
			'url'		=> 'http://mods.mybb.com/view/pluginlibrary'
		)
	);
}

// _activate
function ougc_portalpagination_activate()
{
	global $cache;
	ougc_portalpagination_deactivate();

	// Modify templates
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('portal', '#'.preg_quote('{$announcements}').'#', '{\$announcements}{\$multipage}');

	// Insert/update version into cache
	$plugins = $cache->read('ougc_plugins');
	if(!$plugins)
	{
		$plugins = array();
	}

	$info = ougc_portalpagination_info();

	if(!isset($plugins['portalpagination']))
	{
		$plugins['portalpagination'] = $info['versioncode'];
	}

	/*~*~* RUN UPDATES START *~*~*/

	/*~*~* RUN UPDATES END *~*~*/

	$plugins['portalpagination'] = $info['versioncode'];
	$cache->update('ougc_plugins', $plugins);
}

// _deactivate
function ougc_portalpagination_deactivate()
{
	ougc_portalpagination_pl_check();

	// Revert template edits
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('portal', '#'.preg_quote('{$multipage}').'#', '', 0);
}

// _is_installed() routine
function ougc_portalpagination_is_installed()
{
	global $cache;

	$plugins = (array)$cache->read('ougc_plugins');

	return !empty($plugins['portalpagination']);
}

// _uninstall() routine
function ougc_portalpagination_uninstall()
{
	global $PL, $cache;
	ougc_portalpagination_pl_check();

	// Delete version from cache
	$plugins = (array)$cache->read('ougc_plugins');

	if(isset($plugins['portalpagination']))
	{
		unset($plugins['portalpagination']);
	}

	if(!empty($plugins))
	{
		$cache->update('ougc_plugins', $plugins);
	}
	else
	{
		$PL->cache_delete('ougc_plugins');
	}
}

// Loads language strings
function ougc_portalpagination_lang_load()
{
	global $lang;

	isset($lang->ougc_portalpagination_desc) or $lang->load('ougc_portalpagination', false, true);

	if(!isset($lang->ougc_portalpagination_desc))
	{
		// Plugin API
		$lang->ougc_portalpagination_desc = 'Adds pagination to your forum portal page.';

		// PluginLibrary
		$lang->ougc_portalpagination_pl_required = 'This plugin requires <a href="{1}">PluginLibrary</a> version {2} or later to be uploaded to your forum.';
		$lang->ougc_portalpagination_pl_old = 'This plugin requires <a href="{1}">PluginLibrary</a> version {2} or later, whereas your current version is {3}.';
	}
}

// PluginLibrary dependency check & load
function ougc_portalpagination_pl_check()
{
	global $lang;
	ougc_portalpagination_lang_load();
	$info = ougc_portalpagination_info();

	if(!file_exists(PLUGINLIBRARY))
	{
		flash_message($lang->sprintf($lang->ougc_portalpagination_pl_required, $info['pl']['url'], $info['pl']['version']), 'error');
		admin_redirect('index.php?module=config-plugins');
		exit;
	}

	global $PL;

	$PL or require_once PLUGINLIBRARY;

	if($PL->version < $info['pl']['version'])
	{
		flash_message($lang->sprintf($lang->ougc_portalpagination_pl_old, $info['pl']['url'], $info['pl']['version'], $PL->version), 'error');
		admin_redirect('index.php?module=config-plugins');
		exit;
	}
}

// Add the pagination
function ougc_portalpagination_run()
{
	global $mybb, $db, $multipage, $PL;
	$PL or require_once PLUGINLIBRARY;

	$page = (isset($mybb->input['page']) && $mybb->input['page'] > 0 ? (int)$mybb->input['page'] : 1);

	if(($numannouncements = &$mybb->settings['portal_numannouncements']) < 1)
	{
		$numannouncements = 10;
	}

	// Build a where clause
	$where = array();
	$where[] = 'visible=\'1\'';
	$where[] = 'closed NOT LIKE \'moved|%\'';

	if($unviewableforums = get_unviewable_forums(true))
	{
		$where[] = 'fid NOT IN('.$unviewableforums.')';
	}

	// START: OUGC Show In Portal
	if(function_exists('ougc_showinportal_info'))
	{
		$where[] = 'showinportal=\'1\'';
	}
	// END: OUGC Show In Portal

	$input = $options = array();
	foreach($mybb->input as $key => &$val)
	{
		switch($key)
		{
			case 'limit':
				$input[$key] = $numannouncements = (int)$val;
				break;
			/*case 'uid':
			case 'username':*/
			case 'author':
				if(!is_numeric($val))
				{
					$query = $db->simple_select('users', 'uid', 'LOWER(username)=\''.$db->escape_string(my_strtolower($val)).'\'', array('limit' => 1));

					$where[] = 'uid=\''.(int)$db->fetch_field($query, 'uid').'\'';

					$input[$key] = (string)$val;
					break;
				}

				$where[] = 'uid=\''.(int)$val.'\'';

				$input[$key] = (int)$val;
				break;
			case 'prefix':
				if(!is_numeric($val))
				{
					$val = my_strtolower($mybb->input[$key]);
					$prefixes = (array)$mybb->cache->read('threadprefixes');
					foreach($prefixes as $prefix)
					{
						if($val == my_strtolower($prefix['prefix']))
						{
							$where[] = 'prefix=\''.(int)$prefix['pid'].'\'';

							$input[$key] = (string)$val;
							break;
						}
					}
					break;
				}

				$where[] = 'prefix=\''.(int)$val.'\'';

				$input[$key] = (int)$val;
				break;
			case 'forum':
				// Google SEO URL support
				// Code from Starpaul20's Move Posts plugin
				if(!is_numeric($val))
				{
					if(!$db->table_exists('google_seo'))
					{
						break;
					}

					// Build regexp to match URL.
					$regexp = $mybb->settings['bburl'].'/'.$mybb->settings['google_seo_url_forums'];

					if($regexp)
					{
						$regexp = preg_quote($regexp, '#');
						$regexp = str_replace('\\{\\$url\\}', '([^./]+)', $regexp);
						$regexp = str_replace('\\{url\\}', '([^./]+)', $regexp);
						$regexp = '#^'.$regexp.'$#u';
					}

					// Fetch the (presumably) Google SEO URL:
					$url = $input[$key] = $val = (string)$val;

					// $url can be either 'http://host/Thread-foobar' or just 'foobar'.

					// Kill anchors and parameters.
					$url = preg_replace('/^([^#?]*)[#?].*$/u', '\\1', $url);

					// Extract the name part of the URL.
					$url = preg_replace($regexp, '\\1', $url);

					// Unquote the URL.
					$url = urldecode($url);

					// If $url was 'http://host/Thread-foobar', it is just 'foobar' now.

					// Look up the ID for this item.
					$query = $db->simple_select('google_seo', 'id', 'idtype=\'3\' AND url=\''.$db->escape_string($url).'\'');

					$mybb->settings['portal_announcementsfid'] = (int)$db->fetch_field($query, 'id');
					break;
				}

				$input[$key] = (int)$val;
				$mybb->settings['portal_announcementsfid'] = (int)$val;
				break;
			case 'poll':
			case 'sticky':
				$where[] = $key.($val == 1 ? '!' : '').'=\'0\'';

				$input[$key] = (int)$val;
				break;
			case 'order_by':
				$val = my_strtolower($val);
				if(in_array($val, array('dateline', 'lastpost', 'replies')))
				{
					$options[$key] = $val;
				}
				$input[$key] = $val;
				break;
			case 'order_dir':
				$options[$key] = (my_strtolower($val) == 'asc' ? 'ASC' : 'DESC');
				$input[$key] = $val;
				break;
		}
	}

	$where[] = 'fid IN (\''.implode('\',\'', array_map('intval', explode(',', $mybb->settings['portal_announcementsfid']))).'\')';

	// Query to get the thread count
	$query = $db->simple_select('threads', 'COUNT(tid) AS threads', implode(' AND ', $where), $options);
	$threadscount = $db->fetch_field($query, 'threads');

	$multipage = (string)multipage($threadscount, $numannouncements, $page, $PL->url_append($_SERVER['PHP_SELF'], $input));

	#unset($where['visible'], $where['closed'], $where['forum']);
	$where = str_replace('\'', '\\\'', 't.'.implode(' AND t.', $where).' AND ');

	$order_by = '';
	if($options)
	{
		if(!isset($options['order_by']))
		{
			$options['order_by'] = 'dateline';
		}
		if(!isset($options['order_dir']))
		{
			$options['order_dir'] = 'DESC';
		}
		$order_by = 't.'.$options['order_by'].' '.$options['order_dir'].', ';
	}

	control_object($db, '
		function query($string, $hide_errors=0, $write_query=0)
		{
			if(!$write_query && strpos($string, \'SELECT p.pid, p.message, p.tid, p.smilieoff\') && strpos($string, \'LIMIT 0,\'))
			{
				$string = strtr($string, array(
					\'WHERE \' => \'WHERE '.$where.'\',
					\'ORDER BY \' => \'ORDER BY '.$order_by.'\',
					\'LIMIT 0,\' => \'LIMIT '.($page-1)*$numannouncements.',\',
				));
			}
			return parent::query($string, $hide_errors, $write_query);
		}
	');

	global $OUGC;

	$OUGC['portal_filtering_done'] = true;
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com ), 1.62
if(!function_exists('control_object'))
{
	function control_object(&$obj, $code)
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
}