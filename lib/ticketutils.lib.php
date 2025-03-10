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
        global $db, $langs, $conf;

        if ($conf->global->TICKETUTILS_ALTER_STATUS_LOGIC)
        {
            TicketUtilsLib::replace_ticket_status($ticket);
        }

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

        $label = $langs->trans('TicketStatusChangedTo', $ticket->ref, $langs->transnoentitiesnoconv($ticket->statuts[$new_status]));
        $message = $message ?: $label;

        $res = self::create_ticket_event($ticket, $message, $label, $user);

        if (!$res)
        {
            $db->rollback();
            return -1;
        }

        if ($conf->global->TICKETUTILS_VALIDATION_STATUS && $new_status == Ticket::STATUS_NEED_MORE_INFO)
        {
            try
            {
                self::notify_awaiting_validation($ticket);
            }
            catch (Exception $e)
            {
                dol_syslog('Error sending email: ' . $e->getMessage(), LOG_ERR);
            }
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

    /**
     * @param   Ticket  $ticket
     */
    public static function replace_ticket_status(&$ticket, $context = '')
    {
        global $langs;

        $langs->load('ticketutils@ticketutils');

        // print_r($ticket->statuts);

        $labels = [
            Ticket::STATUS_NOT_READ => 'TicketStatusNotAssigned',
            Ticket::STATUS_READ => 'TicketStatusRead',
            Ticket::STATUS_ASSIGNED => 'TicketStatusAssigned',
            Ticket::STATUS_IN_PROGRESS => 'TicketStatusInProgress',
            Ticket::STATUS_WAITING => 'TicketStatusWaiting',
            Ticket::STATUS_NEED_MORE_INFO => 'TicketStatusWaitingValidation',
            Ticket::STATUS_CLOSED => 'TicketStatusClosed',
            Ticket::STATUS_CANCELED => 'TicketStatusAbandoned'
        ];

        if ($context == 'ticketcard')
        {
            unset($labels[Ticket::STATUS_READ], $labels[Ticket::STATUS_ASSIGNED]);

            if ($ticket->status != Ticket::STATUS_NOT_READ)
            {
                unset($labels[Ticket::STATUS_NOT_READ]);
            }
            if ($ticket->status == Ticket::STATUS_NOT_READ)
            {
                unset($labels[Ticket::STATUS_IN_PROGRESS]);
            }

            if ($ticket->status == Ticket::STATUS_NEED_MORE_INFO)
            {
                unset($labels[Ticket::STATUS_IN_PROGRESS], $labels[Ticket::STATUS_WAITING]);
            }
        }

        $ticket->statuts = $labels;
        $ticket->statuts_short = $labels;
    }

    /**
     *    Mark a message as read
     *
     *    @param    Ticket	$ticket
     *    @param    User	$user				Object user
     *    @param    int 	$id_assign_user		ID of user assigned
     *    @param    int 	$notrigger        	Disable trigger
     *    @return   int							<0 if KO, 0=Nothing done, >0 if OK
     */
    public static function replace_ticket_assign_user($ticket, $user, $id_assign_user, $notrigger = 0)
    {
        global $conf, $langs;

        $error = 0;

        $ticket->oldcopy = dol_clone($ticket);

        $ticket->db->begin();

        $sql = "UPDATE " . MAIN_DB_PREFIX . "ticket";
        if ($id_assign_user > 0)
        {
            $sql .= " SET fk_user_assign=" . ((int) $id_assign_user) . ", fk_statut = " . Ticket::STATUS_IN_PROGRESS;
        }
        else
        {
            $sql .= " SET fk_user_assign=null, fk_statut = " . Ticket::STATUS_NOT_READ;
        }
        $sql .= " WHERE rowid = " . ((int) $ticket->id);

        dol_syslog(get_class($ticket) . "::assignUser sql=" . $sql);
        $resql = $ticket->db->query($sql);

        if (!$resql)
        {
            $ticket->db->rollback();
            $ticket->error = $ticket->db->lasterror();
            dol_syslog(get_class($ticket) . "::assignUser " . $ticket->error, LOG_ERR);
            return -1;
        }

        $ticket->fk_user_assign = $id_assign_user; // May be used by trigger

        if (!$notrigger)
        {
            // Call trigger
            $result = $ticket->call_trigger('TICKET_ASSIGNED', $user);
            if ($result < 0)
            {
                $error++;
            }
        }

        if ($error)
        {
            $ticket->db->rollback();
            $ticket->error = join(',', $ticket->errors);
            dol_syslog(get_class($ticket) . "::assignUser " . $ticket->error, LOG_ERR);
            return -1;
        }

        $ticket->db->commit();
        return 1;
    }

    /**
     * @param   Ticket  $ticket
     */
    public static function notify_awaiting_validation($ticket)
    {
        global $user;

        $to = $ticket->origin_email;

        if (!$to)
        {
            return;
        }

        $body = 'Esperando validación';

        $subject = 'Validación ticket';

        $trackid = 'ticket_' . $ticket->id;

        $mail = new CMailFile(
            $subject,
            $to,
            $user->email,
            $body,
            [],
            [],
            [],
            '',
            '',
            0,
            1,
            '',
            '',
            $trackid
        );

        $result = $mail->sendfile();

        if (!$result)
        {
            setEventMessages($mail->error, $mail->errors, 'warnings');
        }

        return $result;
    }

    public static function rating()
    {
        $w = '';

        $w .= '<div class="rating-container">';

        for ($i = 1; $i <= 5; $i++)
        {
            $w .= '<span class="rating-item" data-value="' . $i . '" data-active="0">';
            $w .= '</span>';
        }

        $w .= '</div>';

        $w .= '<input type="hidden" name="rating" id="rating" value="0">';

        $w .= '<script src="' . DOL_URL_ROOT . '/custom/ticketutils/js/rating.js"></script>';

        return $w;
    }
}
