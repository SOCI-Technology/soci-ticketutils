<?php


$hooks_route = DOL_DOCUMENT_ROOT . '/custom/ticketutils/class/hooks';

require_once $hooks_route . '/ticketutils_ticket_card_hooks.class.php';
require_once $hooks_route . '/ticketutils_ticket_list_hooks.class.php';
require_once $hooks_route . '/ticketutils_create_ticket_hooks.class.php';

require_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/lib/ticketutils.lib.php';

require_once DOL_DOCUMENT_ROOT . '/custom/socilib/soci_lib_strings.class.php';

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
            $langs->load('ticketutils@ticketutils');
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

        /* echo '<pre>';
        print_r($_POST);
        echo '</pre>';
        
        exit(); */

        $param_context = explode(':', $parameters['context']);

        if (in_array('ticketcard', $param_context))
        {
            if ($conf->global->TICKETUTILS_ALTER_STATUS_LOGIC)
            {
                TicketUtilsLib::replace_ticket_status($object, 'ticketcard');

                if ($action == 'assign_user')
                {
                    return TicketUtilsTicketCardHooks::replace_assign_user($object);
                }

                if ($action == 'confirm_reopen')
                {
                    return TicketUtilsTicketCardHooks::replace_reopen_ticket($object);
                }

                if ($action == 'add' && GETPOST('save'))
                {
                    return TicketUtilsTicketCardHooks::replace_create_ticket($object, $action);
                }
            }

            if ($conf->global->TICKETUTILS_REQUIRE_CHANGE_STATUS_NOTE)
            {
                if (in_array($action, ['set_read', 'confirm_set_status', 'abandon', 'close', 'reopen']))
                {
                    $new_status = GETPOST('new_status');

                    switch ($action)
                    {
                        case 'set_read':
                            $new_status = Ticket::STATUS_READ;
                            break;
                        case 'abandon':
                            $new_status = Ticket::STATUS_CANCELED;
                            break;
                        case 'close':
                            $new_status = Ticket::STATUS_CLOSED;
                            break;
                        case 'reopen':
                            $new_status = Ticket::STATUS_IN_PROGRESS;
                            break;
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

        if (in_array('ticketlist', $param_context))
        {
            if ($conf->global->TICKETUTILS_ONLY_ONE_ID)
            {
                TicketUtilsTicketListHooks::hide_public_track_id();
            }

            TicketUtilsTicketListHooks::add_arrayfields();
        }

        if (in_array('publicnewticketcard', $param_context))
        {
            if ($action == 'create_ticket' && GETPOST('save', 'alpha'))
            {
                if (GETPOST('save', 'alpha'))
                {
                    if ($conf->global->TICKETUTILS_ONLY_ONE_ID)
                    {
                        TicketUtilsCreateTicketHooks::replace_create_ticket($object, $action);
                        return 1;
                    }
                }
            }

            if ($conf->global->TICKETUTILS_SELECT_COMPANY)
            {
                echo TicketUtilsCreateTicketHooks::add_select_company();
            }
        }

        if (in_array('ticketlist', $param_context) || in_array('projectticket', $param_context) || in_array('thirdpartyticket', $param_context))
        {
            global $user, $mode;

            if (!empty($user->rights->ticketutils->ticket->all))
            {
                return;
            }

            $mode = 'mine';
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

        if (in_array('ticketcard', $param_context))
        {
            echo '<link rel="stylesheet" href="' . DOL_URL_ROOT . '/custom/ticketutils/css/ticketutils.css">';

            if ($action != 'create')
            {
                $print = TicketUtilsTicketCardHooks::add_rating_info($object);

                $this->resprints = $print;
            }

            if ($action == 'create')
            {
                $print = TicketUtilsTicketCardHooks::change_creation_form();

                echo $print;
            }
        }
    }

    function setContentSecurityPolicy($parameters, &$object, &$action, $hookmanager)
    {
        $param_context = explode(':', $parameters['context']);

        $url_params = '';

        foreach ($_GET as $key => $value)
        {
            $url_params .= $key . '=' . $value . '&';
        }

        if (in_array('ticketpubliclist', $param_context))
        {
            header('Location: ' . DOL_URL_ROOT . '/custom/ticketutils/public/ticket/list.php?' . $url_params);

            exit();
        }

        if (in_array('ticketpublicview', $param_context))
        {
            header('Location: ' . DOL_URL_ROOT . '/custom/ticketutils/public/ticket/view.php?' . $url_params);

            exit();
        }
    }

    function LibStatut($parameters, &$object, &$action, $hookmanager)
    {
        global $conf;

        $param_context = explode(':', $parameters['context']);

        if (get_class($object) == Ticket::class)
        {
            if ($conf->global->TICKETUTILS_ALTER_STATUS_LOGIC)
            {
                $context = in_array('ticketcard', $param_context) ? 'ticketcard' : '';

                $w = TicketUtilsLib::replace_ticket_status($object, $context);

                if ($w)
                {
                    $this->resprints = $w;
                    return 1;
                }
            }
        }
    }

    function llxFooter($parameters, &$object, &$action, $hookmanager)
    {
        global $conf;

        $param_context = explode(':', $parameters['context']);

        if (in_array('ticketcard', $param_context))
        {
            if ($conf->global->TICKETUTILS_ONLY_ONE_ID)
            {
                TicketUtilsTicketCardHooks::hide_public_track_id();
            }
        }
    }

    function printFieldListSelect($parameters, &$object, &$action, $hookmanager)
    {
        $param_context = explode(':', $parameters['context']);

        if (in_array('ticketlist', $param_context))
        {
            $res = TicketUtilsTicketListHooks::add_list_select();

            $this->resprints = $res;
        }
    }

    function printFieldListFrom($parameters, &$object, &$action, $hookmanager)
    {
        $param_context = explode(':', $parameters['context']);

        if (in_array('ticketlist', $param_context))
        {
            $res = TicketUtilsTicketListHooks::add_list_from();

            $this->resprints = $res;
        }
    }

    function printFieldListOption($parameters, &$object, &$action, $hookmanager)
    {
        $param_context = explode(':', $parameters['context']);

        if (in_array('ticketlist', $param_context))
        {
            $res = TicketUtilsTicketListHooks::add_list_option();

            $this->resprints = $res;
        }
    }

    function printFieldListTitle($parameters, &$object, &$action, $hookmanager)
    {
        $param_context = explode(':', $parameters['context']);

        if (in_array('ticketlist', $param_context))
        {
            $res = TicketUtilsTicketListHooks::add_list_title();

            $this->resprints = $res;
        }
    }

    function printFieldListValue($parameters, &$object, &$action, $hookmanager)
    {
        $param_context = explode(':', $parameters['context']);

        if (in_array('ticketlist', $param_context))
        {
            $obj = $parameters['obj'];

            $res = TicketUtilsTicketListHooks::add_list_value($object, $obj);

            $this->resprints = $res;
        }
    }

    function dolGetButtonAction($parameters, &$object, &$action, $hookmanager)
    {
        $param_context = explode(':', $parameters['context']);

        if (in_array('ticketcard', $param_context))
        {
            $res = TicketUtilsTicketCardHooks::hide_buttons($object, $parameters);

            if ($res)
            {
                $this->resprints = '';
                return 1;
            }
        }
    }

    function menuLeftMenuItems($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user;

        $param_context = explode(':', $parameters['context']);

        /* echo '<pre>';
        print_r($object);
        echo '</pre>'; */

        $new_menu = [];

        $can_see_all_tickets = !empty($user->rights->ticketutils->ticket->all);

        /* echo '<pre>';
        print_r($object);
        echo '</pre>'; */

        $menu_changed = false;

        foreach ($object as &$menu)
        {
            if (!$can_see_all_tickets)
            {
                if ($menu['leftmenu'] == 'ticketlist')
                {
                    continue;
                }
            }

            $is_statistics = $menu['titre'] == $langs->trans('Statistics') && $menu['mainmenu'] == 'ticket';

            if ($is_statistics)
            {
                $menu['leftmenu'] = 'ticket_stats';
            }

            $new_menu[] = $menu;

            if ($is_statistics)
            {
                $menu_changed = true;
                $new_menu[] = [
                    'fk_menu' => 'fk_mainmenu=ticket,fk_leftmenu=ticket_stats',
                    'url' => '/custom/ticketutils/user_stats.php',
                    'titre' => $langs->trans('MenuUserStats'),
                    'level' => 2,
                    'enabled' => 1,
                    'target' => '',
                    'mainmenu' => 'ticket',
                    'leftmenu' => 'user_stats',
                    'position' => 1,
                    'classname' => '',
                    'prefix' => '',
                ];
            }
        }

        if (!$menu_changed)
        {
            return 0;
        }

        $this->results = $new_menu;

        return 1;
    }

    function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
        $param_context = explode(':', $parameters['context']);

        if (in_array('ticketcard', $param_context))
        {
            echo TicketUtilsTicketCardHooks::accept_reject_buttons($object, 'private');
            echo TicketUtilsTicketCardHooks::button_abandon_request($object);
            echo TicketUtilsTicketCardHooks::button_abandon($object);
            echo TicketUtilsTicketCardHooks::button_reopen_abandon_request($object);
        }
    }

    function addOpenElementsDashboardLine($parameters, &$object, &$action, $hookmanager)
    {
        $res = TicketUtilsLib::replace_ticket_board();

        $this->results = $res;

        return 0;
    }

    function addOpenElementsDashboardGroup($parameters, &$object, &$action, $hookmanager)
    {
        $res = [
            'ticket' => [
                'groupName' => 'Tickets',
                'globalStatsKey' => 'ticket',
                'stats' => ['ticket_opened', 'ticket_waiting']
            ]
        ];

        $this->results = $res;

        return 0;
    }

    function completeTabsHead($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user;

        $sent_object = $parameters['object'];

        if ($sent_object->element == 'ticket')
        {
            /** @var Ticket */
            $ticket = $sent_object;

            if (!empty($user->rights->ticketutils->ticket->all))
            {
                return 0;
            }

            if ($ticket->fk_user_assign == $user->id || $ticket->fk_user_create == $user->id)
            {
                return 0;
            }

            accessforbidden('', 0);
            exit();
        }
    }
}
