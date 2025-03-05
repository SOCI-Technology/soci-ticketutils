<?php

require_once DOL_DOCUMENT_ROOT . '/custom/socilib/soci_lib.class.php';

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
}
