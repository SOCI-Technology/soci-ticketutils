<?php
// Include Dolibarr environment
include_once(DOL_DOCUMENT_ROOT . "/core/modules/DolibarrModules.class.php");

include_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/class/wty_cv.class.php';
include_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/class/wty_warranty.class.php';

/**
 * @class modWarranty
 * @brief Descripcion del modulo de garantias
 */
class modTicketutils extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 * @param DoliDB $DB Database handler
	 */
	function __construct($DB)
	{
		global $langs, $conf;
		$this->db = $DB;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 20931152;

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'ticketutils';

		// Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
		// It is used to group modules in module setup page
		$this->family = "crm";
		$this->module_position = 50;
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		// Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		$this->description = "Managing CVs for products and their warranties";
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = '1.0.0';
		// Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 0;
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		$this->picto = 'logo@ticketutils';

		// Defined if the directory /mymodule/inc/triggers/ contains triggers or not

		$this->module_parts = array('triggers' => 0);
		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/mymodule/temp");
		$this->dirs = array();
		$r = 0;

		// Relative path to module style sheet if exists. Example: '/mymodule/css/mycss.css'.
		//$this->style_sheet = '/aiu/css/aiu.css';
		$this->module_parts = array(
			'css' => 0,
			'triggers' => 1,
			'hooks' => array(
				'ticketcard',
				'thirdpartyticket',
				'projectticket',
				'ticketlist',
				'publicnewticketcard',
				'ticketpubliclist',
				'ticketpublicview'
			)
		);
		// Config pages. Put here list of php page names stored in admmin directory used to setup module.
		$this->config_page_url = array('setup.php@ticketutils');

		// Dependencies
		// List of modules id that must be enabled if this module is enabled
		$this->depends = array();
		// List of modules id to disable if this one is disabled		
		$this->requiredby = array();

		$this->langfiles = array("ticketutils@ticketutils");

		// Constants

		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 0 or 'allentities')
		$this->const = array();

		// Array to add new pages in new tabs
		$this->tabs = array();

		// dictionnarys
		if (!isset($conf->ticketutils->enabled))
		{
			$conf->ticketutils = new stdClass();
			$conf->ticketutils->enabled = 0;
		}
		$this->dictionaries = array();

		// Boxes
		$this->boxes = array();			// List of boxes
		$r = 0;

		// Permissions
		$this->rights = array();		// Permission array used by this module
		$r = 0;

		/* $this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = 'PermCvRead';
		$this->rights[$r][4] = 'cv';
		$this->rights[$r][5] = 'read';
		$r++;*/


		// Main menu entries
		$this->menus = array();			// List of menus to add
		$r = 0;

		/* $this->menu[$r++] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => 'Warranties',
			'prefix' => '',
			'mainmenu' => 'ticketutils',
			'leftmenu' => '',
			'url' => '/custom/ticketutils/index.php',
			'langs' => 'ticketutils@ticketutils',
			'position' => 1000 + $r,
			'enabled' => '$conf->ticketutils->enabled',
			'perms' => '$user->rights->ticketutils->cv->read',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=ticketutils',
			'type' => 'left',
			'titre' => 'CVs',
			'prefix' => '<span class="fas fa-file-medical paddingright pictofixedwith" style="color: var(--cv-color)"></span>',
			'mainmenu' => 'ticketutils',
			'leftmenu' => 'cvs',
			'url' => '/custom/ticketutils/cv/index.php',
			'langs' => 'ticketutils@ticketutils',
			'position' => 1100 + $r,
			'enabled' => '1',
			'perms' => '',
			'target' => '',
			'user' => 2,
		); */
	}
	/**
	 * Function to initialize module permissions
	 */
	private function initPermissions()
	{
		// Define module permissions
		$this->rights = array();
	}

	/**
	 * Function called when module is enabled.
	 * @return int 1 if OK, 0 if KO
	 */
	function init($options = '')
	{
		global $conf;

		$sql = array();

		// Load necessary tables
		$result = $this->load_tables();

		// Perform other initialization tasks

		return $this->_init($sql);
	}

	/**
	 * Function called when module is disabled.
	 * @return int 1 if OK, 0 if KO
	 */
	function remove($options = '')
	{
		$sql = array();

		// Perform removal tasks

		return $this->_remove($sql);
	}

	/**
	 * Create tables, keys, and data required by the module.
	 * @return int <=0 if KO, >0 if OK
	 */
	function load_tables()
	{
		try
		{
			return $this->_load_tables('/ticketutils/sql/');
		}
		catch (Exception $ex)
		{
			dol_print_error($ex->getMessage());
			return -1;
		}
	}
}
