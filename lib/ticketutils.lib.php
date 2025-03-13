<?php

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

require_once DOL_DOCUMENT_ROOT . '/custom/observaciones/class/observacion.class.php';

class TicketUtilsLib
{
    public $output;

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

    public static function ans_prepare_head()
    {
        global $langs;

        $h = 0;
        $head = array();

        $head[$h][0] = DOL_URL_ROOT . "/custom/ticketutils/ans.php";
        $head[$h][1] = $langs->trans('General');
        $head[$h][2] = 'general';

        $h++;

        $head[$h][0] = DOL_URL_ROOT . "/custom/ticketutils/ans_dicts.php?dict=types";
        $head[$h][1] = $langs->trans('Types');
        $head[$h][2] = 'types';

        $h++;

        $head[$h][0] = DOL_URL_ROOT . "/custom/ticketutils/ans_dicts.php?dict=groups";
        $head[$h][1] = $langs->trans('Groups');
        $head[$h][2] = 'groups';

        $h++;

        $head[$h][0] = DOL_URL_ROOT . "/custom/ticketutils/ans_dicts.php?dict=severity";
        $head[$h][1] = $langs->trans('SeverityLevels');
        $head[$h][2] = 'severity';

        $h++;

        return $head;
    }

    /**
     * @param Ticket $ticket
     * @param string $new_status
     */
    public static function change_ticket_status($ticket, $new_status, $user, $message = null, $label = null)
    {
        global $db, $langs, $conf;

        if ($conf->global->TICKETUTILS_ALTER_STATUS_LOGIC)
        {
            TicketUtilsLib::replace_ticket_status($ticket);
        }

        $now = date('Y-m-d H:i:s', dol_now());

        $sql = "UPDATE " . MAIN_DB_PREFIX . "ticket";
        $sql .= " SET fk_statut = '" . $new_status . "'";

        $is_setting_read = $ticket->status == Ticket::STATUS_NOT_READ && $new_status != Ticket::STATUS_NOT_READ;
        $is_not_read_and_setting_read = empty($ticket->date_read) && $new_status != Ticket::STATUS_NOT_READ;

        if ($is_setting_read || $is_not_read_and_setting_read)
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

        $label = $label ?: $langs->trans('TicketStatusChangedTo', $ticket->ref, $langs->transnoentitiesnoconv($ticket->statuts[$new_status]));
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

        if ($ticket->status == Ticket::STATUS_NOT_READ || empty($ticket->date_read))
        {
            $sql .= " , date_read = '" . date('Y-m-d H:i:s', dol_now()) . "'";
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
        global $db, $user, $conf, $mysoc, $langs;

        $id_to_use = $conf->global->TICKETUTILS_ONLY_ONE_ID ? 'ref' : 'track_id';

        try
        {
            $assigned_user = new User($db);
            $assigned_user->fetch($ticket->fk_user_assign);

            $to = $ticket->origin_email;
            $from = $conf->global->TICKET_NOTIFICATION_EMAIL_FROM;

            if (!$to || !$from)
            {
                throw new Exception('No email defined');
            }

            $subject = '[' . $mysoc->getFullName($langs) . '] - ' .  $langs->transnoentitiesnoconv('AwaitingValidationSubject', $ticket->ref);

            $msg = '';

            $msg .= $langs->trans('AwaitingValidationMessage');

            $msg .= '<br>';
            $msg .= '<br>';

            $msg .= $langs->trans('LinkToTicket') . ': ' . DOL_MAIN_URL_ROOT . '/public/ticket/view.php?track_id=' . $ticket->$id_to_use . '&email=' . $ticket->origin_email;

            $trackid = 'ticket_' . $ticket->id;

            $mail = new CMailFile(
                $subject,
                $to,
                $user->email,
                $msg,
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
        }
        catch (Exception $e)
        {
            dol_syslog('TICKETUTILS: ERROR SENDING EMAIL: ' . $e->getMessage());
        }

        if (!$result)
        {
            setEventMessage($mail->error, 'warnings');
        }

        return $result;
    }

    public static function rating()
    {
        $w = '';

        $w .= '<div class="rating-container">';

        $w .= '<span>';
        $w .= '1';
        $w .= '</span>';

        for ($i = 1; $i <= 5; $i++)
        {
            $w .= '<i class="fas fa-star rating-item" data-value="' . $i . '"></i>';
        }

        $w .= '<span>';
        $w .= '5';
        $w .= '</span>';

        $w .= '</div>';

        $w .= '<input type="hidden" name="rating" id="rating" value="0">';

        $w .= '<script src="' . DOL_URL_ROOT . '/custom/ticketutils/js/rating.js"></script>';

        return $w;
    }

    /**
     * @param   Ticket  $ticket
     * @param   User    $user
     * @param   string  $message
     */
    public static function reject_ticket($ticket, $user, $message)
    {
        global $db, $langs, $conf, $mysoc;

        $langs->load('ticketutils@ticketutils');

        $label = $langs->trans('TicketRefRejected', $ticket->ref);

        $res = TicketUtilsLib::change_ticket_status($ticket, Ticket::STATUS_IN_PROGRESS, $user, $message, $label);

        if (!($res > 0))
        {
            dol_syslog('TICKETUTILS: ERROR REJECTING TICKET', LOG_ERR);
            return -1;
        }

        try
        {
            $assigned_user = new User($db);
            $assigned_user->fetch($ticket->fk_user_assign);

            $to = $assigned_user->email;
            $from = $conf->global->TICKET_NOTIFICATION_EMAIL_FROM;

            if (!$to || !$from)
            {
                throw new Exception('No email defined');
            }

            $subject = '[' . $mysoc->getFullName($langs) . '] - ' .  $langs->transnoentitiesnoconv('TicketRejectionSubject', $ticket->ref);

            $msg = '';

            $msg = $langs->transnoentitiesnoconv('TicketRejectionMessage');

            $msg .= '<br>';
            $msg .= '<br>';

            $msg .= '<b>' . $langs->transnoentitiesnoconv('Comments') . ':</b> ' . $message;

            $msg .= '<br>';
            $msg .= '<br>';

            $msg .= $langs->transnoentitiesnoconv('LinkToTicket') . ': ' . DOL_MAIN_URL_ROOT . '/ticket/card.php?id=' . $ticket->id;

            $mail = new CMailFile(
                $subject,
                $to,
                $from,
                $msg,
                [],
                [],
                [],
                '',
                '',
                0,
                1
            );

            $mail->sendfile();
        }
        catch (Exception $e)
        {
            dol_syslog('TICKETUTILS: ERROR SENDING EMAIL: ' . $e->getMessage(), LOG_ERR);
        }

        return 1;
    }

    public static function get_inactive_tickets()
    {
        /** @var Conf $conf */
        global $db, $conf;

        $ticket_example = new Ticket($db);

        $DELAY_BEFORE_FIRST_RESPONSE = $conf->global->TICKET_DELAY_BEFORE_FIRST_RESPONSE * 3600;
        $DELAY_SINCE_LAST_RESPONSE = $conf->global->TICKET_DELAY_SINCE_LAST_RESPONSE * 3600;

        $sql = "SELECT ";
        foreach ($ticket_example->fields as $field => $field_info)
        {
            if ($field == 'rowid')
            {
                $sql .= "t.rowid";
                continue;
            }

            $sql .= ", t." . $field;
        }

        $sql .= " FROM " . MAIN_DB_PREFIX . "ticket as t";
        $sql .= " WHERE t.fk_statut NOT IN (" . join(',', [Ticket::STATUS_CANCELED, Ticket::STATUS_CLOSED, Ticket::STATUS_NEED_MORE_INFO, Ticket::STATUS_WAITING]) . ")";

        $resql = $db->query($sql);

        if (!$resql)
        {
            return;
        }

        $inactive_tickets = [];

        for ($i = 0; $i < $db->num_rows($resql); $i++)
        {
            $obj = $db->fetch_object($resql);

            $ticket = new Ticket($db);

            foreach ($ticket->fields as $field => $field_info)
            {
                if ($field == 'rowid')
                {
                    $ticket->id = $obj->rowid;
                    continue;
                }

                $ticket->$field = $obj->$field;
            }

            $actioncomm = new ActionComm($db);
            /** @var ActionComm[] */
            $linked_actions = $actioncomm->getActions(0, $ticket->id, $ticket->element);

            /** @var ActionComm|null */
            $last_message = null;

            foreach ($linked_actions as $action)
            {
                $last_message_id = $last_message->id ?? 0;

                if ($action->code == 'TICKET_MSG_SENTBYMAIL' && $action->id > $last_message_id)
                {
                    $last_message = $action;
                }
            }

            $ticket_creation_time = strtotime($ticket->datec);

            $alert_first_response = false;
            $alert_response_delay = false;

            if (!$last_message)
            {
                $alert_first_response = (time() - $ticket_creation_time) > $DELAY_BEFORE_FIRST_RESPONSE;
            }
            else
            {
                $last_message_time = $last_message->datec;

                echo $last_message_time;
                echo '<br>';

                $alert_response_delay = time() - $last_message_time > $DELAY_SINCE_LAST_RESPONSE;
            }

            $ticket->fetchObjectLinked();

            $linked_objects = $ticket->linkedObjects;

            /** @var Fichinter[] $linked_interventions */
            $linked_interventions = $linked_objects['fichinter'] ?? [];

            $alert_inactive_interventions = false;

            if ($conf->observaciones && $conf->observaciones->enabled)
            {
                foreach ($linked_interventions as $intervention)
                {
                    if ($intervention->status == Fichinter::STATUS_CLOSED)
                    {
                        continue;
                    }

                    $observaciones = Observacion::get_all($db, ["fk_intervention = '" . $intervention->id . "'"]);

                    $last_observacion_time = null;

                    foreach ($observaciones as $observacion)
                    {
                        $observacion_time = strtotime($observacion->fecha);

                        if ($observacion_time > $last_observacion_time)
                        {
                            $last_observacion_time = $observacion_time;
                        }
                    }

                    if (!$last_observacion_time)
                    {
                        $intervention_creation_time = $intervention->datec;

                        $alert_inactive_interventions = time() - $intervention_creation_time > $DELAY_SINCE_LAST_RESPONSE;
                    }
                    else
                    {
                        $alert_inactive_interventions = time() - $last_observacion_time > $DELAY_SINCE_LAST_RESPONSE;
                    }

                    if ($alert_inactive_interventions)
                    {
                        break;
                    }
                }
            }

            if (!$alert_first_response && !$alert_response_delay && !$alert_inactive_interventions)
            {
                continue;
            }

            $inactive_tickets[$ticket->id] = [
                "ticket" => $ticket,
                "alert_first_response" => $alert_first_response,
                "alert_response_delay" => $alert_response_delay,
                "alert_inactive_interventions" => $alert_inactive_interventions
            ];
        }

        return $inactive_tickets;
    }

    public function inactive_tickets_notification()
    {
        global $conf, $db;

        if (empty($conf->global->TICKETUTILS_SEND_EMAIL_NOTIFICATIONS_WHEN_DELAY))
        {
            dol_syslog('TICKETUTILS: DELAY NOTIFICATIONS DISABLED');
            $this->output = 'Delay notifications disabled';
            return 0;
        }

        $from = $conf->global->TICKET_NOTIFICATION_EMAIL_FROM;

        if (!$from)
        {
            dol_syslog('TICKETUTILS: NO FROM EMAIL', LOG_ERR);
            $this->output = 'No from email';

            return -1;
        }

        $inactive_tickets = self::get_inactive_tickets();

        if (empty($inactive_tickets))
        {
            dol_syslog('TICKETUTILS: NO INACTIVE TICKETS');
            $this->output = 'No inactive tickets';
            return 0;
        }

        $users_to_alert_ids = explode(';', $conf->global->TICKETUTILS_USERS_TO_ALERT_WHEN_DELAY);

        $users_to_alert = [];

        foreach ($users_to_alert_ids as $user_id)
        {
            $user_to_alert = new User($db);
            $user_to_alert->fetch($user_id);

            if ($user_to_alert->id > 0)
            {
                $users_to_alert[$user_to_alert->id] = $user_to_alert;
            }
        }

        $tickets_for_users = [];

        foreach ($inactive_tickets as $ticket_info)
        {
            $ticket = $ticket_info['ticket'];

            if (!$ticket->fk_user_assign)
            {
                continue;
            }

            $tickets_for_users[$ticket->fk_user_assign][$ticket->id] = $ticket_info;
        }

        $general_result = self::general_delay_notification($users_to_alert, $inactive_tickets, true);

        $users_results = [];

        foreach ($tickets_for_users as $user_id => $user_tickets)
        {
            $ticket_user = new User($db);

            if (isset($found_users[$user_id]))
            {
                $ticket_user = $found_users[$user_id];
            }
            else
            {
                $ticket_user->fetch($user_id);
            }

            $user_result = self::general_delay_notification([$ticket_user], $user_tickets);

            $users_results[$user_id] = $user_result;
        }

        $this->output = json_encode([
            'general' => $general_result,
            'users' => $users_results
        ]);

        return 0;
    }

    /**
     * @param User[] $users
     * @param array{ticket:Ticket,alert_first_response:bool,alert_response_delay:bool,alert_inactive_interventions:bool}[] $inactive_tickets
     */
    public static function general_delay_notification($users, $inactive_tickets, $show_assigned_user = false)
    {
        global $db, $langs, $conf, $mysoc;

        $with_alert_first_response = [];
        $with_alert_response_delay = [];
        $with_alert_inactive_interventions = [];

        foreach ($inactive_tickets as $ticket_info)
        {
            if ($ticket_info['alert_first_response'])
            {
                $with_alert_first_response[] = $ticket_info['ticket'];
            }
            if ($ticket_info['alert_response_delay'])
            {
                $with_alert_response_delay[] = $ticket_info['ticket'];
            }
            if ($ticket_info['alert_inactive_interventions'])
            {
                $with_alert_inactive_interventions[] = $ticket_info['ticket'];
            }
        }

        /** @var User[] $found_users */
        $found_users = [];

        $msg = '';

        $msg .= $langs->trans('GeneralTicketDelayIntroduction');

        $msg .= '<br>';
        $msg .= '<br>';

        if (!empty($with_alert_first_response))
        {
            $msg .= self::print_tickets_with_delay(
                $langs->trans('TicketsWithDelayInFirstResponse'),
                $with_alert_first_response,
                $show_assigned_user
            );

            $msg .= '<br>';
        }

        if (!empty($with_alert_response_delay))
        {
            $msg .= self::print_tickets_with_delay(
                $langs->trans('TicketsWithDelayInResponse'),
                $with_alert_response_delay,
                $show_assigned_user
            );

            $msg .= '<br>';
        }

        if (!empty($with_alert_inactive_interventions))
        {
            $msg .= self::print_tickets_with_delay(
                $langs->trans('TicketsWithInactiveInterventions'),
                $with_alert_inactive_interventions,
                $show_assigned_user
            );

            $msg .= '<br>';
        }

        $subject = '[' . $mysoc->getFullName($langs) . '] - ' . $langs->trans('DelayInNTickets', count($inactive_tickets));

        $from = $conf->global->TICKET_NOTIFICATION_EMAIL_FROM;

        $sent = 0;
        $not_sent = 0;

        foreach ($users as $user)
        {
            $to = $user->email;

            if (!$user->email || !$from)
            {
                $not_sent++;
                continue;
            }

            $mail = new CMailFile(
                $subject,
                $to,
                $from,
                $msg,
                [],
                [],
                [],
                '',
                '',
                0,
                1
            );

            try
            {
                $res = $mail->sendfile();
            }
            catch (Exception $e)
            {
                dol_syslog('TICKETUTILS: ERROR SENDING EMAIL: ' . $e->getMessage(), LOG_ERR);
            }

            if (!$res)
            {
                $not_sent++;
                continue;
            }

            $sent++;
        }

        return ['sent' => $sent, 'not_sent' => $not_sent];
    }

    /**
     * @param   string      $title
     * @param   Ticket[]    $tickets
     */
    public static function print_tickets_with_delay($title, $tickets, $show_assigned_user = false)
    {
        global $langs, $db, $found_users;

        $msg = '';

        $msg .= '<b>';
        $msg .= $title . ':';
        $msg .= '</b>';

        $msg .= '<br>';
        $msg .= '<br>';

        /* $msg .= '<ul>';
        foreach ($tickets as $ticket)
        {
            $msg .= '<li>';

            $msg .= '<a href="' . DOL_MAIN_URL_ROOT . '/ticket/card.php?id=' . $ticket->id . '">';
            $msg .= $ticket->ref;
            $msg .= '</a>';

            $msg .= '<span>';
            $msg .= ' - ' . $langs->trans('CreatedAt') . ': ' . $ticket->datec;
            $msg .= '</span>';

            if ($show_assigned_user)
            {
                $assigned_user = new User($db);

                if (isset($found_users[$ticket->fk_user_assign]))
                {
                    $assigned_user = $found_users[$ticket->fk_user_assign];
                }
                else
                {
                    $assigned_user->fetch($ticket->fk_user_assign);
                }

                $msg .= '<span>';
                $msg .= ' - ';
                if ($assigned_user->id > 0)
                {
                    $msg .= $langs->trans('AssignedTo') . ': ' . $assigned_user->getFullName($langs);
                }
                else
                {
                    $msg .= '<b>' . $langs->trans('NotAssigned') . '</b>';
                }
                $msg .= '</span>';
            }

            $msg .= '</li>';
        }
        $msg .= '</ul>'; */

        $msg .= '<table border="1" style="text-align: center; border-collapse: collapse" cellpadding="5">';

        $msg .= '<thead>';
        $msg .= '<tr>';

        $msg .= '<th>';
        $msg .= $langs->trans('Ticket');
        $msg .= '</th>';

        $msg .= '<th>';
        $msg .= $langs->trans('Title');
        $msg .= '</th>';

        $msg .= '<th>';
        $msg .= $langs->trans('CreationDate');
        $msg .= '</th>';

        if ($show_assigned_user)
        {
            $msg .= '<th>';
            $msg .= $langs->trans('AssignedTo');
            $msg .= '</th>';
        }

        $msg .= '</tr>';
        $msg .= '</thead>';

        $msg .= '<tbody>';
        
        foreach ($tickets as $ticket)
        {
            $msg .= '<tr>';

            $msg .= '<td>';
            $msg .= '<a href="' . DOL_MAIN_URL_ROOT . '/ticket/card.php?id=' . $ticket->id . '">';
            $msg .= $ticket->ref;
            $msg .= '</a>';
            $msg .= '</td>';

            $msg .= '<td>';
            $msg .= $ticket->subject;
            $msg .= '</td>';

            $msg .= '<td>';
            $msg .= $ticket->datec;
            $msg .= '</td>';

            if ($show_assigned_user)
            {
                $assigned_user = new User($db);

                if (isset($found_users[$ticket->fk_user_assign]))
                {
                    $assigned_user = $found_users[$ticket->fk_user_assign];
                }
                else
                {
                    $assigned_user->fetch($ticket->fk_user_assign);
                }

                $msg .= '<td>';
                if ($assigned_user->id > 0)
                {
                    $msg .= $assigned_user->getFullName($langs);
                }
                else
                {
                    $msg .= '<b>' . $langs->trans('NotAssigned') . '</b>';
                }
                $msg .= '</td>';
            }

            $msg .= '</tr>';
        }
        $msg .= '</tbody>';
        
        $msg .= '</table>';

        return $msg;
    }
}
