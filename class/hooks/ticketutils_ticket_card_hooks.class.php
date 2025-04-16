<?php

require_once DOL_DOCUMENT_ROOT . '/custom/socilib/soci_lib.class.php';

require_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/class/ticket_extrafields.class.php';

class TicketUtilsTicketCardHooks
{
    /**
     * @param Ticket $object
     */
    public static function confirm_change_status($object, $status)
    {
        global $langs;

        $langs->load('ticketutils@ticketutils');

        SociLib::load_components([SOCILIB_MODAL]);

        $form_action = DOL_URL_ROOT . '/custom/ticketutils/inc/ticket_card.inc.php?id=' . $object->id;

        $props = new SociModalProps();
        $props->default_display = 'flex';

        $c = '';

        $c .= '<div>';

        $c .= '<span>';
        $c .= $langs->trans('ChangeTicketStatusTo', $langs->transnoentitiesnoconv($object->statuts[$status]));
        $c .= '</span>';

        $c .= '<br>';
        $c .= '<br>';

        $c .= '<div style="display: flex; flex-direction: column; gap: 2px">';
        $c .= '<b>';
        $c .= $langs->trans('Observations') . ':';
        $c .= '</b>';
        $c .= '<textarea name="status_observations" required>';
        $c .= '</textarea>';
        $c .= '</div>';

        $c .= '<input type="hidden" name="id" value="' . $object->id . '">';
        $c .= '<input type="hidden" name="new_status" value="' . $status . '">';

        $c .= '</div>';

        echo SociModal::print(
            'change_status',
            $form_action,
            'change_status',
            $langs->trans('ChangeTicketStatus'),
            $c,
            $props
        );

        return 1;
    }

    public static function hide_public_track_id()
    {
        global $langs;

        echo '<input type="hidden" id="ticketutils_track_id_label" value="' . $langs->trans('TicketTrackId') . '">';

        echo '<script src="' . DOL_URL_ROOT . '/custom/ticketutils/js/hide_public_track_id.js"></script>';
    }

    /**
     * @param   Ticket  $object
     */
    public static function replace_assign_user($object)
    {
        global $user, $action, $langs, $permissiontoadd, $error;

        if (!(GETPOST('btn_assign_user', 'alpha') && $permissiontoadd))
        {
            return;
        }

        $object->fetch('', '', GETPOST("track_id", 'alpha'));
        $useroriginassign = $object->fk_user_assign;
        $usertoassign = GETPOST('fk_user_assign', 'int');

        $ret = TicketUtilsLib::replace_ticket_assign_user($object, $user, $usertoassign);

        if ($ret < 0)
        {
            $error++;
        }

        // Update list of contacts
        // Si déjà un user assigné on le supprime des contacts
        if (!$error && $useroriginassign > 0)
        {
            $internal_contacts = $object->listeContact(-1, 'internal', 0, 'SUPPORTTEC');
            foreach ($internal_contacts as $key => $contact)
            {
                if ($contact['id'] !== $usertoassign)
                {
                    $result = $object->delete_contact($contact['rowid']);
                    if ($result < 0)
                    {
                        $error++;
                        setEventMessages($object->error, $object->errors, 'errors');

                        return;
                    }
                }
            }
        }

        if (!$error && $usertoassign > 0 && $usertoassign !== $useroriginassign)
        {
            $result = $object->add_contact($usertoassign, "SUPPORTTEC", 'internal', $notrigger = 0);
            if ($result < 0)
            {
                $error++;
                setEventMessages($object->error, $object->errors, 'errors');
            }
        }


        if (!$error)
        {
            // Log action in ticket logs table
            $object->fetch_user($usertoassign);
            //$log_action = $langs->trans('TicketLogAssignedTo', $object->user->getFullName($langs));

            $message = $usertoassign > 0 ? $langs->trans('TicketAssigned') : $langs->trans('TicketUnassigned');

            setEventMessages($message, null, 'mesgs');
            header("Location: card.php?track_id=" . $object->track_id);
            exit;
        }
        else
        {
            array_push($object->errors, $object->error);
        }

        $action = 'view';

        return 1;
    }

    /**
     * @param   Ticket  $object
     */
    public static function replace_reopen_ticket($object)
    {
        global $user;

        $object->fetch(GETPOST('id', 'int'), '', GETPOST('track_id', 'alpha'));

        if (!($object->id > 0))
        {
            return;
        }

        // prevent browser refresh from reopening ticket several times
        if (!($object->status == Ticket::STATUS_CLOSED || $object->status == Ticket::STATUS_CANCELED))
        {
            return;
        }

        $res = TicketUtilsLib::change_ticket_status($object, Ticket::STATUS_IN_PROGRESS, $user);

        if ($res)
        {
            $url = DOL_URL_ROOT . '/ticket/card.php?track_id=' . $object->track_id;

            header("Location: " . $url);
            exit();
        }
        else
        {
            setEventMessages($object->error, $object->errors, 'errors');
        }

        return 1;
    }

    /**
     * @param   Ticket  $ticket
     */
    public static function add_rating_info($ticket)
    {
        if ($ticket->status != Ticket::STATUS_CLOSED)
        {
            return;
        }

        global $db, $langs;

        $langs->load('ticketutils@ticketutils');

        $ticket_extrafields = new TicketExtrafields($db);
        $ticket_extrafields->fetch(0, $ticket->id);

        $rating = $ticket_extrafields->rating;

        $w = '';

        $w .= '<tr>';
        $w .= '<td>';
        $w .= $langs->trans('Rating');
        $w .= '</td>';

        $w .= '<td>';

        if ($rating === null)
        {
            $w .= $langs->trans('NoRating');
        }
        else
        {
            $w .= '<div class="rating-container">';
            for ($i = 1; $i <= 5; $i++)
            {
                $active = $i <= $rating ? 'active' : '';

                $w .= '<i class="fas fa-star rating-item ' . $active . ' static">';
                $w .= '</i>';
            }
            $w .= '</div>';
        }

        $w .= '</td>';

        $w .= '</tr>';

        $w .= '<tr>';

        $w .= '<td>';
        $w .= $langs->trans('Comments');
        $w .= '</td>';

        $w .= '<td>';
        $w .= $ticket_extrafields->rating_comment;
        $w .= '</td>';

        $w .= '</tr>';

        return $w;
    }

    /**
     * @param   Ticket  $object
     * @param   array   $button_parameters
     */
    public static function hide_buttons($ticket, $button_parameters)
    {
        global $user, $langs;

        $content = $button_parameters['html'];

        switch ($content)
        {
            case $langs->trans('ReOpen'):
                {
                    if (empty($user->rights->ticketutils->ticket->reopen))
                    {
                        return 1;
                    }

                    break;
                }
            case $langs->trans('CloseTicket'):
                {
                    return 1;
                }
            case $langs->trans('AbandonTicket'):
                {
                    if (empty($user->rights->ticketutils->ticket->abandon))
                    {
                        return 1;
                    }

                    break;
                }
        }
    }

    /**
     * @param   Ticket  $ticket
     */
    public static function button_abandon_request($ticket)
    {
        global $user, $langs;

        if (!empty($user->rights->ticketutils->ticket->abandon))
        {
            return;
        }

        if ($ticket->status == TicketUtilsLib::TICKET_STATUS_ABANDON_REQUEST)
        {
            return;
        }

        SociLib::load_components([SOCILIB_MODAL]);

        $form_action = DOL_URL_ROOT . '/custom/ticketutils/inc/ticket_card.inc.php';

        $c = '';

        $c .= '<div style="display: flex; flex-direction: column; align-items: flex-start; text-align: left">';

        $c .= '<span>';
        $c .= $langs->trans('TicketAbandonRequestModalDescription');
        $c .= '</span>';

        $c .= '<b>';
        $c .= $langs->trans('Justification') . ': ';
        $c .= '</b>';

        $c .= '<textarea name="message" style="min-width: 300px; height: 100px" required>';
        $c .= '</textarea>';

        $c .= '<input type="hidden" name="id" value="' . $ticket->id . '">';

        $c .= '</div>';

        $w = '';

        $w .= SociModal::print(
            'abandon_request_modal',
            $form_action,
            'request_abandon',
            $langs->trans('AbandonTicket'),
            $c
        );


        $w .= '<a class="butAction toggle-modal" data-modal-id="abandon_request_modal">';
        $w .= $langs->trans('RequestAbandonTicket');
        $w .= '</a>';

        return $w;
    }

    /**
     * @param   Ticket  $object
     */
    public static function button_abandon($object)
    {
        global $langs, $user;

        // Abadon ticket if statut is read
        if (isset($object->status) && $object->status == TicketUtilsLib::TICKET_STATUS_ABANDON_REQUEST && !empty($user->rights->ticketutils->ticket->abandon))
        {
            return dolGetButtonAction('', $langs->trans('AbandonTicket'), 'default', $_SERVER["PHP_SELF"] . '?action=abandon&token=' . newToken() . '&track_id=' . $object->track_id, '');
        }
    }

    /**
     * @param   Ticket  $object
     */
    public static function button_reopen_abandon_request($object)
    {
        global $langs, $user;

        // Re-open ticket
        if (!$user->socid && (isset($object->status) && ($object->status == TicketUtilsLib::TICKET_STATUS_ABANDON_REQUEST)) && !$user->socid && $user->rights->tickeutils->ticket->reopen)
        {
            return dolGetButtonAction('', $langs->trans('ReOpen'), 'default', $_SERVER["PHP_SELF"] . '?action=reopen&token=' . newToken() . '&track_id=' . $object->track_id, '');
        }
    }

    public static function change_creation_form()
    {
        global $langs;

        $options = [
            "external",
            "internal"
        ];

        $w = '';

        $w .= '<table>';

        $w .= '<tr>';

        $w .= '<td class="fieldrequired">';
        $w .= $langs->trans('EntryType');
        $w .= '</td>';

        $w .= '<td>';
        $w .= '<select name="entry_type" id="entry_type">';
        foreach ($options as $option)
        {
            $w .= '<option value="' . $option . '">';
            $w .= $langs->trans('EntryType' . ucfirst($option));
            $w .= '</option>';
        }
        $w .= '</select>';

        //$w .= ajax_combobox('entry_type');

        $w .= '</td>';

        $w .= '</tr>';

        $w .= '</table>';

        $w .= '<script src="' . DOL_URL_ROOT . '/custom/ticketutils/js/change_creation_form.js?time=' . time() . '"></script>';

        return $w;
    }

    /**
     * @param   Ticket  $object
     * @param   string  $action
     */
    public static function replace_create_ticket(&$object, &$action)
    {
        global $user, $langs, $extrafields, $db, $conf;
        /**
         * @var DoliDB $db
         */

        $permissiontoadd = $user->hasRight('ticket', 'write');

        if (!$permissiontoadd)
        {
            return 1;
        }

        $projectid = GETPOST('projectid', 'int');

        $error = 0;

        if (!GETPOST("type_code", 'alpha'))
        {
            $error++;
            setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("TicketTypeRequest")), null, 'errors');
            $action = 'create';
        }
        elseif (!GETPOST("category_code", 'alpha'))
        {
            $error++;
            setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("TicketCategory")), null, 'errors');
            $action = 'create';
        }
        elseif (!GETPOST("severity_code", 'alpha'))
        {
            $error++;
            setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("TicketSeverity")), null, 'errors');
            $action = 'create';
        }
        elseif (!GETPOST("subject", 'alphanohtml'))
        {
            $error++;
            setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Subject")), null, 'errors');
            $action = 'create';
        }
        elseif (!GETPOST("message", 'restricthtml'))
        {
            $error++;
            setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Message")), null, 'errors');
            $action = 'create';
        }
        $ret = $extrafields->setOptionalsFromPost(null, $object);
        if ($ret < 0)
        {
            $error++;
        }

        if ($error)
        {
            setEventMessages($object->error, $object->errors, 'errors');
            $action = 'create';
            return 1;
        }

        $db->begin();

        $getRef = GETPOST("ref", 'alphanohtml');
        if ($object->fetch('', $getRef) > 0)
        {
            $object->ref = $object->getDefaultRef();
            $object->track_id = null;
            setEventMessage($langs->trans('TicketRefAlreadyUsed', $getRef, $object->ref));
        }
        else
        {
            $object->ref = $getRef;
        }

        $object->fk_soc = GETPOST("socid", 'int') > 0 ? GETPOST("socid", 'int') : 0;
        $object->subject = GETPOST("subject", 'alphanohtml');
        $object->message = GETPOST("message", 'restricthtml');

        $object->type_code = GETPOST("type_code", 'alpha');
        $object->type_label = $langs->trans($langs->getLabelFromKey($db, $object->type_code, 'c_ticket_type', 'code', 'label'));
        $object->category_code = GETPOST("category_code", 'alpha');
        $object->category_label = $langs->trans($langs->getLabelFromKey($db, $object->category_code, 'c_ticket_category', 'code', 'label'));
        $object->severity_code = GETPOST("severity_code", 'alpha');
        $object->severity_label = $langs->trans($langs->getLabelFromKey($db, $object->severity_code, 'c_ticket_severity', 'code', 'label'));
        $object->email_from = $user->email;
        $notifyTiers = GETPOST("notify_tiers_at_create", 'alpha');
        $object->notify_tiers_at_create = empty($notifyTiers) ? 0 : 1;

        $object->fk_project = $projectid;

        $id = $object->create($user);

        if ($id <= 0)
        {
            $error++;
            setEventMessages($object->error, $object->errors, 'errors');
            $action = 'create';
            return 1;
        }

        if ($error)
        {
            setEventMessages($object->error, $object->errors, 'errors');
            $action = 'create';
            return 1;
        }


        // Add contact
        $contactid = GETPOST('contactid', 'int');
        $type_contact = GETPOST("type", 'alpha');

        // Category association
        $categories = GETPOST('categories', 'array');
        $object->setCategories($categories);

        if ($contactid > 0 && $type_contact)
        {
            $typeid = (GETPOST('typecontact') ? GETPOST('typecontact') : GETPOST('type'));
            $result = $object->add_contact($contactid, $typeid, 'external');
        }

        // Link ticket to project
        if (GETPOST('origin', 'alpha') == 'projet')
        {
            $projectid = GETPOST('originid', 'int');
        }
        else
        {
            $projectid = GETPOST('projectid', 'int');
        }

        if ($projectid > 0)
        {
            $object->setProject($projectid);
        }

        // Auto mark as read if created from backend
        if (!empty($conf->global->TICKET_AUTO_READ_WHEN_CREATED_FROM_BACKEND) && $user->rights->ticket->write)
        {
            if (! $object->markAsRead($user) > 0)
            {
                setEventMessages($object->error, $object->errors, 'errors');
            }
        }

        // Auto assign user
        /* if (!empty($conf->global->TICKET_AUTO_ASSIGN_USER_CREATE))
        {
            $result = $object->assignUser($user, $user->id, 1);
            $object->add_contact($user->id, "SUPPORTTEC", 'internal');
        } */

        $fk_user_assign = GETPOST("fk_user_assign", 'int');
        if ($fk_user_assign > 0)
        {
            if ($conf->global->TICKETUTILS_ALTER_STATUS_LOGIC)
            {
                $res = TicketUtilsLib::replace_ticket_assign_user($object, $user, $fk_user_assign);
            }
            else
            {
                $res = $object->assignUser($user, $fk_user_assign);
            }

            if (!($res > 0))
            {
                $error++;
            }
        }

        if ($error)
        {
            $db->rollback();
            setEventMessages($object->error, $object->errors, 'errors');
            $action = 'create';
            return 1;
        }

        $object->copyFilesForTicket('');        // trackid is forced to '' because files were uploaded when no id for ticket exists yet and trackid was ''

        $db->commit();

        if (!empty($backtopage))
        {
            if (empty($id))
            {
                $url = $backtopage;
            }
            else
            {
                $url = 'card.php?track_id=' . urlencode($object->track_id);
            }
        }
        else
        {
            $url = 'card.php?track_id=' . urlencode($object->track_id);
        }

        header("Location: " . $url);
        exit;
    }

    /**
     * @param   Ticket  $ticket 
     */
    public static function accept_reject_buttons($ticket, $view = 'public')
    {
        global $langs, $user;

        if ($ticket->status != Ticket::STATUS_NEED_MORE_INFO)
        {
            return;
        }

        if (!$ticket->email_from)
        {
            if ($ticket->fk_user_create != $user->id)
            {
                return;
            }
        }

        $w = '';

        $w .= TicketUtilsLib::accept_modal($ticket, $view);

        $w .= TicketUtilsLib::reject_modal($ticket, $view);

        $w .= '<div class="inline-block divButAction toggle-modal" data-modal-id="modal_accept">';
        $w .= '<a class="butAction accept">';
        $w .= $langs->trans('AcceptSolution');
        $w .= '</a>';
        $w .= '</div>';

        $w .= '<div class="inline-block divButAction">';
        $w .= '<a class="butAction reject toggle-modal" data-modal-id="modal_reject">';
        $w .= $langs->trans('RejectSolution');
        $w .= '</a>';
        $w .= '</div>';

        return $w;
    }
}
