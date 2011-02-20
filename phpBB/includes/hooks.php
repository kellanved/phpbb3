<?php
/**
*
* @package phpBB
* @version $Id$
* @copyright (c) 2010 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

class phpBB_hook_controller
{
	var $mods;
	var $mod_names;
	var $mod_ids;
	
	// These are to be replaced by config options.
	var $class_suffix = '_class';
	var $moddir = 'mods';
	
	function __construct()
	{
		if (defined('DISABLE_HOOKS'))
		{
			return;
		}
		else
		{
			$this->init();
		}
	}
	
	function init()
	{
		$this->get_mods();
		$this->instantiate_mods();
	}
	
	
	/**
	* Queries all active MODs for their hooking functions and stores the result
	* in the module_hook table. Call after hooks were added.
	* @param $mod String : If set, only the hooks for the MODule with the name $mod will be refreshed 
	*/
	function register_hooks($mod = false, $hook = false)
	{
		global $cache, $db;
		
		if ($mod)
		{
			$this->remove_mod_hooks($mod);
			$instances = array($mod => $this->mods[$mod]);
		}
		else
		{
			$instances = $this->mods;
			$sql = 'TRUNCATE TABLE ' . HOOK_MOD_TABLE;
			$db->sql_query($sql);
		}

		$hooks = $this->get_defined_hooks($hook);
		$data = array();
		foreach($hooks as $id => $hook)
		{
			foreach($instances as $name => $instance)
			{
				$info = $instance->MOD_info();
				if (method_exists($instance, $hook))
				{
					$priority = (isset($info['hooks'][$hook])) ? $info['hooks'][$hook] : DEFAULT_PRIORITY;
					$data[] = array('hook_id' => $id, 'mod_id' => $this->mod_ids[$name], 'priority' => $priority);
				}
			}
		}
		$db->sql_multi_insert(HOOK_MOD_TABLE, $data);
		$cache->purge();
	}
	
	
	/**
	* Adds a MOD to the mod table
	* @param $mod String : The name of the MOD
	*/
	function register_mod($mod)
	{
		global $db;
		
		if (isset($this->mods[$mod]))
		{
			return false;
		}
		
		$this->instantiate_mods($mod);
		$info = $this->mods[$mod]->MOD_info();
		$sql_array = array(	'mod_active'	=> 1,
							'mod_author'	=> $info['mod_author'],
							'mod_version'	=> $info['mod_version'],
							'mod_name'		=> $mod,
		);
		$sql = 'INSERT INTO ' . MOD_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_array);
		
		$db->sql_query($sql);
		$mod_id = $db->sql_nextid();
		
		$this->mod_names[$mod_id] = $info['mod_name']; 
		$this->mod_ids[$mod] = $mod_id; 
		$this->mods[$mod]->MOD_install();
		return $this->register_hooks($mod);
	}
	
	/**
	* Switches a MOD on
	* @param $mod String : The name of the MOD
	*/
	function activate_mod($mod)
	{
		global $cache, $db;
	
		$sql = 'UPDATE ' . MOD_TABLE . " SET mod_active = 1 WHERE mod_name = '" . $db->sql_escape($mod) . "'";
		$db->sql_query($sql);
		$sql = 'SELECT mod_id FROM ' . MOD_TABLE . " WHERE mod_name = '" . $db->sql_escape($mod) . "'";
		$result = $db->sql_query_limit($sql, 1);
		$id = (int) $db->sql_fetchfield('mod_id');
		$db->sql_freeresult($result);
		if (!$id)
		{
			return false;
		}
		
		$this->instantiate_mods($mod);
		$this->mod_names[$id] = $mod; 
		$this->mod_ids[$mod] = $id; 
		return $this->register_hooks($mod);
	}
	
	/**
	* Switches a MOD off
	* @param $mod String : The name of the MOD
	*/
	function deactivate_mod($mod)
	{
		global $cache, $db;	
		
		$sql = 'UPDATE ' . MOD_TABLE . " SET mod_active = 0 WHERE mod_name = '" . $db->sql_escape($mod) . "'";
		$db->sql_query($sql);
		return $this->remove_mod_hooks($mod);
	}
	
	/**
	* Removes a MODule from the hook registry.
	* @param $mod String : name of the MODule to be removed
	*/
	function remove_mod_hooks($mod)
	{
		global $cache, $db;
		
		if ($mod)
		{
			if (isset($this->mod_ids[$mod]) && $id = $this->mod_ids[$mod])
			{
				$sql = 'DELETE FROM ' . HOOK_MOD_TABLE . ' 
					WHERE mod_id = ' . (int) $id;
				$db->sql_query($sql);
				$cache->purge();
			}
			else
			{
				return false;
			}
		}
	}
	
	/**
	* Invokes the hook name with the parameters $args
	* @param $name String : The name of the hook
	* @param $args ...
	*/
	function invoke_hook()
	{
		if (defined('DISABLE_HOOKS'))
		{
			return;
		}
		$args = func_get_args();
		$name = $args[0];
		unset($args[0]);

		$mods = $this->get_hooks($name);
		
		$return_val = array();
		foreach ($mods as $mod)
		{
			$mod_result = call_user_func(array($this->mods[$mod], $name), $args);
			if ($mod_result && is_array($mod_result))
			{
				$return_val = array_merge_recursive($result);
			}
			else
			{
				$return_val[] = $mod_result;
			}
		}
		return $return_val;
	}
	
	
	/**
	* Invokes the hook name with the parameters $args in the MOD $mod
	* @param $name String : The name of the hook
	* @param $args Array: An array with the arguments for the hook.
	* @param $mod mixed : only the hook in the MODule(s) $mod will be invoked 
	*/
	function invoke_hook_mod()
	{
		if (defined('DISABLE_HOOKS'))
		{
			return;
		}
		$args = func_get_args();
		$name = $args[0];
		$mod = $args[1];
		unset($args[0]);
		unset($args[1]);

		$mods = array($mod);
		
		$return_val = array();

		$mod_result = call_user_func(array($this->mods[$mod], $name), $args);
		if ($mod_result && is_array($mod_result))
		{
			$return_val = array_merge_recursive($result);
		}
		else
		{
			$return_val[] = $mod_result;
		}
		
		return $return_val;
	}
	
	/**
	* Creates a new hook. Call it in the install routine of your MOD.
	* @param $name String : The name of the hook
	*/
	function add_hook($name)
	{
		global $db;
		
		if ($this->get_defined_hooks($name))
		{
			return false;
		}
		$sql_array = array('hook_name' => $name);
		$sql = 'INSERT INTO ' . HOOK_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_array);
 		$db->sql_query($sql);
		return $this->register_hooks($name);
	}
	
	/**
	* Retrieve all MODs that are hooking into a given hook.
	* @param $name String : The name of the hook
	*/
	function get_hooks($name)
	{
		global $cache, $db;
		
		if ($hooks = $cache->get("hooks_$name"))
		{
			return $cache->get("hooks_$name");
		}
		else
		{
			$sql = 'SELECT mod_name FROM ' . HOOK_TABLE . ' h 
				LEFT JOIN ' . HOOK_MOD_TABLE . ' hm ON (h.hook_id = hm.hook_id) 
				LEFT JOIN ' . MOD_TABLE . " m ON (hm.mod_id = m.mod_id) 
				WHERE h.hook_name = '" . $db->sql_escape($name) . "' 
					AND m.mod_active = 1 
				ORDER BY hm.priority DESC";
			
			$result = $db->sql_query($sql);
			$hooks = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$hooks[] = $row['mod_name'];
			}
			$db->sql_freeresult($result);
			$cache->put("hooks_$name", $hooks);
			return $hooks;
		}
	}
	
	
	/**
	* Private, sets the list of active MODs
	*/
	function get_mods()
	{
		global $db;
		
		$sql = 'SELECT mod_name, mod_id FROM ' . MOD_TABLE . ' 
			WHERE mod_active = 1';
		$result = $db->sql_query($sql, 7200);
		$this->mod_names = array();
		$this->mod_ids = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$this->mod_names[(int)$row['mod_id']] = $row['mod_name']; 
			$this->mod_ids[$row['mod_name']] = (int) $row['mod_id']; 
		}
		$db->sql_freeresult($result);
		// Consider placing all of this in cache.
	}
	
	/**
	* includes and instantiates all active MODs
	*/
	function instantiate_mods($mod = false)
	{
		global $phpBB_root_path, $phpEx;
		
		if (defined('DISABLE_HOOKS'))
		{
			return;
		}
		if ($mod)
		{
			$mods = array($mod);
		}
		else
		{
			$this->mods = array();
			$mods = $this->mod_names;
		}
		foreach ($mods as $name)
		{
			// oh autoloader, where art thou?
			require($phpBB_root_path . $this->moddir . "/$name/" . $name . $this->class_suffix . ".$phpEx");
			// seems that using a variable is about twice as fast as using reflections
			$this->mods[$name] = new $name();
		}
	}
	
	/**
	* Get a list of all known hook types
	*/
	function get_defined_hooks($name = false)
	{
		global $db;
		
		$where = '';
		if ($name)
		{
			$where = " WHERE hook_name = '" . $db->sql_escape($name) . "'";
		}
		$sql = 'SELECT hook_name, hook_id FROM ' . HOOK_TABLE . $where;
		$result = $db->sql_query($sql);
		$hooks = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$hooks[$row['hook_id']] = $row['hook_name']; 
		}
		return $hooks;
	}
}
