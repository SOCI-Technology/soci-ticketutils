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
                    if (!$user->rights->ticketutils->ticket->reopen)
                    {
                        return 1;
                    }
                }
            case $langs->trans('CloseTicket'):
                {
                    return 1;
                }
        }
    }
}
