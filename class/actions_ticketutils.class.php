<?php


$hooks_route = DOL_DOCUMENT_ROOT . '/custom/ticketutils/class/hooks';

require_once $hooks_route . '/ticketutils_ticket_card_hooks.class.php';
require_once $hooks_route . '/ticketutils_create_ticket_hooks.class.php';

require_once DOL_DOCUMENT_ROOT . '/custom/socilib/soci_lib_strings.class.php';

if ($user->rights->debugbar->read)
{
    //ini_set('display_errors', '1');
    //ini_set('display_startup_errors', '1');
    //error_reporting(E_ALL);
}

class ActionsTicketUtils
{

    /**
     * @var DoliDB Database handler.
     */
    public $db;

    /**
     * @var string Error code (or message)
     */
    public $error = '';

    /**
     * @var array Errors
     */
    public $errors = array();


    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = array();

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;

    /**
     * @var int		Priority of hook (50 is used if value is not defined)
     */
    public $priority;

    /**
     * @var string[]    List of available contexts
     */
    public $context_list = array('');

    /**
     * Constructor
     *
     *  @param		DoliDB		$db      Database handler
     */
    public function __construct($db)
    {
        global $langs;

        if ($langs)
        {
            $langs->load('warranty@warranty');
        }

        $this->db = $db;
    }



    /**
     * Overriding the doActions function : replacing the parent function with the one below
     *
     * @param   array           $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          &$action        Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    function doActions($parameters, &$object, &$action, $hookmanager)
    {
        dol_syslog(self::class . 'doActions');

        global $langs, $user, $conf;

        $param_context = explode(':', $parameters['context']);

        if (in_array('ticketcard', $param_context))
        {
            if ($conf->global->TICKETUTILS_REQUIRE_CHANGE_STATUS_NOTE)
            {
                if ($action == 'set_read' || $action == 'confirm_set_status')
                {
                    $new_status = GETPOST('new_status');

                    if ($action == 'set_read')
                    {
                        $new_status = Ticket::STATUS_READ;
                    }

                    $res = TicketUtilsTicketCardHooks::confirm_change_status($object, $new_status);

                    if ($res > 0)
                    {
                        $action = '';
                    }

                    return $res;
                }
            }
        }
    }

    function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $object;
        
        $param_context = explode(':', $parameters['context']);

        if (in_array('publicnewticketcard', $param_context))
        {
            TicketUtilsCreateTicketHooks::fix_show_errors($object);
            TicketUtilsCreateTicketHooks::add_message_character_count();
        }
    }
}
