<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
//
// By: Spicer Matthews <spicer@cloudmanic.com>
// Date: 1/12/2011
//
class MY_Session extends CI_Session 
{
	//
	// Construct .....
	//
	function __construct()
	{
		$this->CI =& get_instance();
		
		// If we are using the database build it (or at least make sure it is built).
		if($this->CI->config->item('sess_use_database'))
		{
			$this->_build_db();
		}
			
		// Call parent constructor last.
		parent::__construct();
	}
	
	//
	// Update an existing session
	//
	function sess_update()
	{
		// skip the session update if this is an AJAX call!
		if(! IS_AJAX)
		{
			parent::sess_update();
		}
	} 
	
	//
	// It seems to only make sense that we create the database if it does not exist.
	//
	function _build_db()
	{
		$table = $this->CI->config->item('sess_table_name');
		
		if(! $this->CI->db->table_exists($table)) 
		{
			$this->CI->load->dbforge();
			
			$cols = array(
				'session_id' => array('type' => 'VARCHAR', 'constraint' => '40', 'null' => FALSE, 'default' => 0),
				'ip_address' => array('type' => 'VARCHAR', 'constraint' => '16', 'null' => FALSE, 'default' => 0),
				'user_agent' => array('type' => 'VARCHAR', 'constraint' => '120', 'null' => FALSE),
				'last_activity' => array('type' => 'INT', 'constraint' => '10', 'null' => FALSE),
				'user_data' => array('type' => 'TEXT', 'null' => FALSE)
			);
			$this->CI->dbforge->add_key('session_id', TRUE);
			$this->CI->dbforge->add_field($cols);
    	$this->CI->dbforge->create_table($table, TRUE);
		}
	}
}

/* End File */