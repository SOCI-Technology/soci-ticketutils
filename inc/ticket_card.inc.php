<?php

/**
 * @var DoliDB $db
 * @var User $user
 */

require_once '../../../main.inc.php';

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';

require_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/lib/ticketutils.lib.php';

if (isset($_POST['change_status']))
{
    $id = GETPOST('id');
    $status_observations = GETPOST('status_observations');
    $new_status = GETPOST('new_status');

    $langs->load('ticketutils@ticketutils');

    $object = new Ticket($db);
    $object->fetch($id);

    $res = TicketUtilsLib::change_ticket_status($object, $new_status, $user, $status_observations);

    if (!($res > 0))
    {
        setEventMessage($langs->trans('ErrorChangingTicketStatus') . ': ' . $db->error(), 'errors');
    }
    else
    {
        setEventMessage($langs->trans('TicketStatusChanged'));
    }

    header('Location: ' . DOL_URL_ROOT . '/ticket/card.php?id=' . $object->id);
    exit();
}
