<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
While logged in as a superadmin
to create new member, post the following: 
	username
	email_address
	screen_name
	password
	password_confirm
	group_id
	
to assign to existing member, post the following: 
	member_id


*/

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * CartThrob Member ID Override Extension
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Extension
 * @author		Chris Newton, Rob Sanchez
 * @link		http://cartthrob.com
 */

class Cartthrob_member_id_override_ext {
	
	public $settings 		= array();
	public $description		= 'Generic Description';
	public $docs_url		= 'http://cartthrob.com';
	public $name			= 'Generic Title';
    public $settings_exist = 'n';
	public $version			= '1.07';
	
	public $admin_group_ids = array('1'); 
	public $member_id 		= NULL; 
	public $profile_edit_channel = NULL; 
	private $EE;
	
 
	/**
	 * Constructor
	 *
	 * @param 	mixed	Settings array or empty string if none exist.
	 */
	public function __construct($settings = '')
	{
		$this->EE =& get_instance();
		$this->settings = $settings;
		$this->module_name = strtolower(str_replace(array('_ext', '_mcp', '_upd'), "", __CLASS__));
		
		$this->EE->lang->loadfile($this->module_name);
		
		$this->description = lang($this->module_name. "_description"); 


		$this->profile_edit_channel =$this->load_profile_edit(); 
		
	}// ----------------------------------------------------------------------
 
	
	/**
	 * Activate Extension
	 *
	 * This function enters the extension into the exp_extensions table
	 *
	 * @see http://codeigniter.com/user_guide/database/index.html for
	 * more information on the db class.
	 *
	 * @return void
	 */
	public function activate_extension()
	{
		// Setup custom settings in this array.
	    $this->settings = array();
			
		$data = array(
			'class'		=> __CLASS__,
			'method'	=> 'cartthrob_on_authorize',
			'hook'		=> 'cartthrob_on_authorize',
			'settings'	=> serialize($this->settings),
			'version'	=> $this->version,
			'enabled'	=> 'y'
		);
		$this->EE->db->insert('extensions', $data);			
		
		$data = array(
			'class'		=> __CLASS__,
			'method'	=> 'cartthrob_pre_process',
			'hook'		=> 'cartthrob_pre_process',
			'settings'	=> serialize($this->settings),
			'version'	=> $this->version,
			'enabled'	=> 'y'
		);
		$data = array(
			'class'		=> __CLASS__,
			'method'	=> 'form_builder_form_start',
			'hook'		=> 'form_builder_form_start',
			'settings'	=> serialize($this->settings),
			'version'	=> $this->version,
			'enabled'	=> 'y'
		);
		$this->EE->db->insert('extensions', $data);
		
 	}	

	// this method loads profile edit if it's available
	// ----------------------------------------------------------------------
	public function load_profile_edit()
	{
		static $loaded;
		
		if ( ! is_null($loaded))
		{
			return $loaded;
		}
		
		if ( ! isset($this->EE->extensions->extensions['safecracker_submit_entry_start'][10]['Profile_ext']))
		{
			return $loaded = FALSE;
		}
		
		// adding the profile edit package path. 
		$this->EE->load->add_package_path(PATH_THIRD.'profile/');
		
		// when the package path is loaded, you can load in files easily from the profile:edit addon
		$this->EE->load->model('profile_model');
		
		// it's loaded. delete the path. we don't need it. 
		$this->EE->load->remove_package_path(PATH_THIRD.'profile/');
		
		// the channel id is returned.
		return $loaded = $this->EE->profile_model->settings('channel_id');
	}
 
	public function get_member_id()
	{
		// member id is being manually set. 
		if (in_array($this->EE->session->userdata('group_id'), $this->admin_group_ids) && $this->EE->input->post('checkout_as_customer_id') ) //superadmins only
		{
			$this->member_id= NULL; 
			if (is_numeric($this->EE->input->post('checkout_as_customer_id')))
			{
				$this->member_id = $this->EE->db->select('member_id')->limit(1)->where('member_id', trim($this->EE->input->post('checkout_as_customer_id')) )->get('members')->row('member_id');
			}
 			return $this->member_id; 
		}
		// member id was set somewhere else
		elseif ($this->member_id)
		{
			return $this->member_id; 
		}
		return $this->EE->session->userdata('member_id'); 
	}
 
	public function cartthrob_pre_process()
	{
		if ($this->EE->input->post('membrer_id') && !($this->EE->input->post('checkout_as_customer_id')))
		{
			$_POST['checkout_as_customer_id'] = $this->EE->input->post('membrer_id'); 
		}
		$member = NULL; 

		if (in_array($this->EE->session->userdata('group_id'), $this->admin_group_ids)  && $this->EE->input->post('username') ) //superadmins only
		{
			$validate_member = $this->validate_new_member(
				$this->EE->input->post('username'), 
				$this->EE->input->post('screen_name'), 
				$this->EE->input->post('password'), 
				$this->EE->input->post('password_confirm'), 
				$this->EE->input->post('email')
				); 
			
			if (is_array($validate_member))
			{
				return $this->EE->form_builder->add_error($validate_member)
							->action_complete();
				
			}
			// turning off member data saving so we don't overwrite admin's information
			return; 
		}
		elseif (in_array($this->EE->session->userdata('group_id'), $this->admin_group_ids)  && $this->EE->input->post('checkout_as_customer_id') ) //superadmins only
		{
			if ($this->EE->input->post('checkout_as_customer_id') && !$this->get_member_id())
			{
 				$this->EE->output->show_user_error('general', $member.lang('cartthrob_member_id_override_member_not_found')); 
				return; 
			}
			
		}
	}
	
 	public function validate_new_member($username, $screen_name, $password, $password_confirm, $email)
	{
		/** -------------------------------------
		/**  Instantiate validation class
		/** -------------------------------------*/
		if ( ! class_exists('EE_Validate'))
		{
			require APPPATH.'libraries/Validate'.EXT;
		}

		$VAL = new EE_Validate(array(
			'member_id' => '',
			'val_type' => 'new', // new or update
			'fetch_lang' => TRUE,
			'require_cpw' => FALSE,
			'enable_log' => FALSE,
			'username' => $username,
			'cur_username' => '',
			'screen_name' => $screen_name,
			'cur_screen_name' => '',
			'password' => $password,
			'password_confirm' => $password_confirm,
			'cur_password' => '',
			'email' => $email,
			'cur_email' => ''
		));
		
		
		$VAL->validate_username();
		$VAL->validate_screen_name();
		$VAL->validate_password();
		$VAL->validate_email();

		if (count($VAL->errors) > 0)
		{
			// return the array of errors. 
			return $VAL->errors;
 		}
		return NULL; 
	}
	public function form_builder_form_start($module, $method)
	{
		if ($module === 'cartthrob' && $method === 'checkout_form')
		{
 			if ( in_array($this->EE->session->userdata('group_id'), $this->admin_group_ids) &&   $this->EE->TMPL->fetch_param('checkout_as_customer_id')  )
			{
 				$this->EE->cartthrob->cart->set_config('save_member_data', 0);
				$this->EE->cartthrob->cart->save();
			}
		}
	}
	public function update_profile($member_id, $custom_data)
	{
		if ( ! $member_id)
		{
			return;
		}
		
		$custom_profile_data = array();
		
		if ($this->profile_edit_channel)
		{
			foreach ($this->EE->cartthrob_field_model->get_fields_by_channel($this->profile_edit_channel) as $field)
			{
				if (isset($custom_data[$field['field_name']]))
				{
					$custom_profile_data['field_id_'.$field['field_id']] = $custom_data[$field['field_name']];
				}
			}

			if ($custom_profile_data)
			{
				$entry_id = $this->EE->profile_model->get_profile_id($member_id);

				if ($entry_id)
				{
					$this->EE->db->update('channel_data', $custom_profile_data, array('entry_id' => $entry_id));
				}
			}
		}

	}
	public function create_member()
	{
		// The person publishing this stuff is an admin, and they've specified a username (therefore a new mmeber)
		// the member must be created. 
		if ( in_array($this->EE->session->userdata('group_id'), $this->admin_group_ids)  && $this->EE->input->post('username') ) //superadmins only
		{
			$this->EE->load->model('cartthrob_members_model');

			$group_id = $this->EE->input->post('group_id'); 
			if (!$group_id || $group_id < 5)
			{
				$group_id = 5; 
			}
			$this->member_id = $this->EE->cartthrob_members_model->create_member(
				$this->EE->input->post('username'),  
				$this->EE->input->post('email_address'),  
				$this->EE->input->post('screen_name', TRUE), 
				$this->EE->input->post('password', TRUE), 
				$this->EE->input->post('password_confirm', TRUE), 
				$group_id,
				$this->EE->cartthrob->cart->customer_info('language'),
				FALSE//do we want to avoid sending the registration email. currently not sending it using this method. 
			);

			// @TODO create a profile edit entry and get the ID. 
			
			// should only be an array if errors are returned
			if (is_array($this->member_id))
			{
 				return $this->EE->form_builder->add_error($this->member_id)
							->action_complete();
			}
		}
	}
	public function get_member_data($member_id)
	{
		$saved_order_data = array(); 
		$member_data_loaded= FALSE; 
		if ($this->profile_edit_channel)
		{
			$profile_edit_entry_id = $this->EE->profile_model->get_profile_id( $member_id );
			$this->EE->load->model('cartthrob_entries_model');

			if (!$profile_edit_entry_id)
			{
				$member_data_loaded=FALSE; 
				continue; 
			}
			else
			{
				if ($member_data = $this->EE->cartthrob_entries_model->entry($profile_edit_entry_id))
				{
					foreach ($this->EE->cartthrob->customer_info_defaults as $key => $value)
					{
						if ($member_field = $this->EE->cartthrob->store->config('member_'.$key.'_field'))
						{
							if (isset($member_data['field_id_'.$member_field]))
							{
								$saved_order_data[$key] = $member_data['field_id_'.$member_field];
							}
						}
					}
					$member_data_loaded = TRUE;
				}
				
				$pe_data = $this->EE->profile_model->get_member_data($member_id);
				$saved_order_data['group_id'] = $pe_data['group_id'];
			}
		}

		if ($member_data_loaded === FALSE)
		{
			$this->EE->load->model('member_model');

			$member_data = $this->EE->member_model->get_all_member_data($member_id)->row_array();

			foreach ($this->EE->cartthrob->customer_info_defaults as $key => $value)
			{
				if ($member_field = $this->EE->cartthrob->store->config('member_'.$key.'_field'))
				{
					if (!empty($member_data['m_field_id_'.$member_field]))
					{
						$saved_order_data[$key] = $member_data['m_field_id_'.$member_field];
					}
				}
			}
		}
		if (!isset($saved_order_data['group_id']))
		{
			$saved_order_data['group_id'] = NULL; 
		}
		return $saved_order_data; 
	}
	public function update_member_data($member_id)
	{
		// saving the member data now.
		$member = array();
		$member_data = array();

		foreach (array_keys($this->EE->cartthrob->cart->customer_info()) as $field)
		{
			// setting an alternate variable because we may be changing where the data's going in a second.
			$orig_field = $field; 

			if (bool_string($this->EE->cartthrob->cart->customer_info('use_billing_info')) && strpos($field, 'shipping_') !== FALSE)
			{
				// we're going to get the data from the billing field
				$field = str_replace('shipping_', '', $field); 
			}

			// saving the data.
			if ($this->EE->input->post($field) !== FALSE && $field_id = $this->EE->cartthrob->store->config('member_'.$orig_field.'_field'))
			{
				if (is_numeric($field_id))
				{
					if ($this->profile_edit_channel)
					{
						$member_data['field_id_'.$field_id] = $this->EE->cartthrob->cart->customer_info($field);
					}
					else
					{
						$member_data['m_field_id_'.$field_id] = $this->EE->cartthrob->cart->customer_info($field);
					}
				}
				else
				{
					$member[$field_id] = $this->EE->cartthrob->cart->customer_info($field);
				}
			}
		}

		$this->EE->load->model('member_model');

		if ( ! empty($member_data))
		{
			if ($this->profile_edit_channel)
			{
				$this->EE->load->model('cartthrob_entries_model');

				$member_data['channel_id'] = $this->profile_edit_channel;

				$this->EE->cartthrob_entries_model->update_entry($this->EE->profile_model->get_profile_id($this->member_id), $member_data);
			}
			else
			{
				$this->EE->member_model->update_member_data($this->member_id, $member_data);
			}
		}
		if ( ! empty($member))
		{
			$this->EE->member_model->update_member($member_id, $member);
		}
	}
	
	public function update_order_customer_data($member_id)
	{
		$saved_order_data = $this->get_member_data( $member_id ); 
		
		foreach ($saved_order_data as $key => $value)
		{
			switch ($key)
			{
				case "first_name": 
				case "last_name": 
				case "company": 
				case "address": 
				case "address2": 
				case "city": 
				case "state": 
				case "zip":
				case "country_code":
				case "country": 
					$key = "billing_".$key; 
					break; 
				case "phone": 
				case "email_address":
					$key = "customer_". $key; 
					break;
				default: 
			}
			$saved_order_data[$key] = $value; 
		}

		$order_data = array(
			'shipping_city'             => $this->EE->cartthrob->cart->order('shipping_city'),
			'shipping_state'            => $this->EE->cartthrob->cart->order('shipping_state'),
			'shipping_country_code'     => $this->EE->cartthrob->cart->order('shipping_country_code'),
			'shipping_zip'              => $this->EE->cartthrob->cart->order('shipping_zip'),
			'shipping_address2'         => $this->EE->cartthrob->cart->order('shipping_address2'),
			'shipping_address'          => $this->EE->cartthrob->cart->order('shipping_address'),
			'shipping_first_name'		=> $this->EE->cartthrob->cart->order('shipping_first_name'),
			'shipping_last_name'		=> $this->EE->cartthrob->cart->order('shipping_last_name'),
			'billing_country_code'      => $this->EE->cartthrob->cart->order('country_code'),
			'billing_zip'               => $this->EE->cartthrob->cart->order('zip'),
			'billing_city'              => $this->EE->cartthrob->cart->order('city'),
			'billing_state'             => $this->EE->cartthrob->cart->order('state'),
			'billing_address'           => $this->EE->cartthrob->cart->order('address'),
			'billing_address2'          => $this->EE->cartthrob->cart->order('address2'),
			'billing_zip'				=> $this->EE->cartthrob->cart->order('zip'),
			'billing_last_name'         => $this->EE->cartthrob->cart->order('last_name'),
			'billing_first_name'        => $this->EE->cartthrob->cart->order('first_name'),
			); 

		$order_data = array_merge($saved_order_data, $order_data); 
			$order_data['customer_full_name'] = $order_data["billing_first_name"]. ' '. $order_data['billing_last_name'];
		$order_data['full_billing_address']= $order_data['billing_address']."\r\n".
				(  !empty($order_data['billing_address2'])  ? $order_data['billing_address2']."\r\n" : '').
				$order_data['billing_city'].', '.$order_data['billing_state'].' '.$order_data['billing_zip'] . 
				(!empty($order_data['billing_country_code']) ? "\r\n". $order_data['billing_country_code'] : ""); 
		$order_data['full_shipping_address']= $order_data['shipping_address']."\r\n".
				(  !empty($order_data['shipping_address2'])  ? $order_data['shipping_address2']."\r\n" : '').
				$order_data['shipping_city'].', '.$order_data['shipping_state'].' '.$order_data['shipping_zip'] . 
				(!empty($order_data['shipping_country_code']) ? "\r\n". $order_data['shipping_country_code'] : "");

		$this->EE->load->model('order_model');

		$order_data = array_filter($order_data); 
		$this->EE->order_model->update_order($this->EE->cartthrob->cart->order('order_id'), $order_data); 
		// update order author
		$this->EE->db->update('channel_titles', 
			array('author_id' => $member_id), 
			array('entry_id' => $this->EE->cartthrob->cart->order('order_id')));
	}
	
	public function update_purchased_item_data($member_id)
	{
		// update purchased items author
		foreach ($this->EE->cartthrob->cart->order('purchased_items') as $key=>$entry_id)
		{
 			$this->EE->db->update('channel_titles', 
				array('author_id' => $member_id), 
				array('entry_id' => $entry_id));
		}
	}
	/**
	 * cartthrob_on_authorize.
	 *
	 * @param 
	 * @return 
	 */
	public function cartthrob_on_authorize()
	{
		if (in_array($this->EE->session->userdata('group_id'), $this->admin_group_ids)  && ($this->EE->input->post('checkout_as_customer_id') || $this->EE->input->post('username'))) //superadmins only
		{
			$this->create_member();

			$member_id = $this->get_member_id();

			if ( $member_id != $this->EE->session->userdata('member_id'))
			{
				$this->update_member_data( $member_id);

				$this->update_order_customer_data($member_id ); 

				$this->update_purchased_item_data($member_id ); 
			}
		}
		// turn member data saving back on. 
		$this->EE->cartthrob->cart->set_config('save_member_data', 1);
		$this->EE->cartthrob->cart->save();

		$this->update_profile($this->get_member_id(), $this->EE->input->post('custom_data'));
	}

	// ----------------------------------------------------------------------

	/**
	 * Disable Extension
	 *
	 * This method removes information from the exp_extensions table
	 *
	 * @return void
	 */
	function disable_extension()
	{
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('extensions');
	}

	// ----------------------------------------------------------------------

	/**
	 * Update Extension
	 *
	 * This function performs any necessary db updates when the extension
	 * page is visited
	 *
	 * @return 	mixed	void on update / false if none
	 */
	function update_extension($current = '')
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
	}	
	
	// ----------------------------------------------------------------------
}

/* End of file ext.cartthrob_member_id_override.php */
/* Location: /system/expressionengine/third_party/cartthrob_member_id_override/ext.cartthrob_member_id_override.php */