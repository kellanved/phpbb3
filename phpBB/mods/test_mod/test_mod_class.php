<?php

class test_mod
{
	function MOD_info()
	{
		return array(	'mod_name'		=> 'Test MOD',
						'mod_author'	=> 'Kellanved',
						'mod_version'	=> '0.0.1',
		);
	}
	
	function MOD_install()
	{
		global $hook_handler;
		
		$hook_handler->add_hook('start');
	}
	
	function test_mod_start()
	{
		global $config;
		$config['sitename'] = 'Hello World';
	}
}
?>