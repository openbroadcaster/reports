<?php

class SxReportingModule extends OBFModule
{

	public $name = 'SX Reporting v1.0';
	public $description = 'Reporting module for SoundExchange.';

	public function callbacks()
	{

	}

	public function install()
	{
      // add permissions data for this module
      $this->db->insert('users_permissions', [
        'category'=>'administration',
        'description'=>'sx reporting module',
        'name'=>'sx_reporting_module'
      ]);
      
      return true;
	}

	public function uninstall()
	{
      // remove permissions data for this module
      $this->db->where('name','sx_reporting_module');
      $permission = $this->db->get_one('users_permissions');
      
      $this->db->where('permission_id',$permission['id']);
      $this->db->delete('users_permissions_to_groups');
      
      $this->db->where('id',$permission['id']);
      $this->db->delete('users_permissions');
      
      return true;
	}
}
