<?php

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

class TicketUtilsLib
{
    /**
     * Prepare the tabs for the setup page of the module
     * 
     * @return string[][]   Array of tabs
     */
    public static function admin_prepare_head()
    {
        global $langs, $conf, $user;

        $h = 0;
        $head = array();

        $head[$h][0] = DOL_URL_ROOT . "/custom/ticketutils/admin/setup.php";
        $head[$h][1] = $langs->trans('Setup');
        $head[$h][2] = 'setup';

        $h++;

        return $head;
    }

    /**
     * @param Ticket $ticket
     * @param string $new_status
     */
    public static function change_ticket_status($ticket, $new_status, $user, $message = null)
    {
        global $db, $langs;

        $now = date('Y-m-d H:i:s', dol_now());

        $sql = "UPDATE " . MAIN_DB_PREFIX . "ticket";
        $sql .= " SET fk_statut = '" . $new_status . "'";

        if ($ticket->status == Ticket::STATUS_NOT_READ && $new_status != Ticket::STATUS_NOT_READ)
        {
            $sql .= ", date_read = '" . $now . "'";
        }

        $sql .= " WHERE rowid = '" . $ticket->id . "'";

        $db->begin();

        $resql = $db->query($sql);

        if (!$resql)
        {
            $db->rollback();
            return -1;
        }

        $label = $langs->trans('TicketStatusChangedTo', $ticket->ref, $langs->transnoentitiesnoconv('TicketStatus' . $ticket->statuts[$new_status]));
        $message = $message ?: $label;

        $res = self::create_ticket_event($ticket, $message, $label, $user);

        if (!$res)
        {
            $db->rollback();
            return -1;
        }

        $db->commit();

        return 1;
    }

    /**
     * @param Ticket $ticket
     */
    public static function create_ticket_event($ticket, $message, $label, $user)
    {
        global $db;

        $event = new ActionComm($db);

        $now = dol_now();

        $event->datep = $now;
        $event->datef = $now;
        $event->ref_ext = $ticket->ref;
        $event->socid = $ticket->fk_soc;
        $event->fk_project = $ticket->fk_project;
        $event->userownerid = $user->id;
        $event->note_private = $message;
        $event->label = $label;
        $event->percentage = '-1';
        $event->priority = '0';
        $event->fulldayevent = '0';
        $event->transparency = '1';
        $event->fk_element = $ticket->id;
        $event->elementtype = $ticket->element;
        $event->type_id = 'AC_OTH_AUTO';

        dol_syslog('TICKETUTILS: CREATING EVENT');

        $res = $event->create($user);

        return $res;
    }
}
