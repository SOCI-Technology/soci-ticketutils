<?php

/**
 * @var DoliDB $db
 */

if (!defined('NOREQUIREMENU'))
{
    define('NOREQUIREMENU', '1');
}
// If there is no need to load and show top and left menu
if (!defined("NOLOGIN"))
{
    define("NOLOGIN", '1');
}
if (!defined('NOIPCHECK'))
{
    define('NOIPCHECK', '1'); // Do not check IP defined into conf $dolibarr_main_restrict_ip
}
if (!defined('NOBROWSERNOTIF'))
{
    define('NOBROWSERNOTIF', '1');
}
// If this page is public (can be called outside logged session)

require_once '../../../main.inc.php';

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/class/ticket_extrafields.class.php';

/* echo '<pre>';
print_r($_POST);
echo '</pre>';

exit(); */

if (GETPOSTISSET('accept_ticket'))
{
    $track_id = GETPOST('track_id');
    $rating = GETPOST('rating');
    $rating_comment = GETPOST('rating_comment');
    $token = GETPOST('token');

    $id_to_use = $conf->global->TICKETUTILS_ONLY_ONE_ID ? 'ref' : 'track_id';

    $ticket = new Ticket($db);
    if ($id_to_use == 'ref')
    {
        $ticket->fetch(0, $track_id);
    }
    else
    {
        $ticket->fetch('', '', $track_id);
    }

    if (!($ticket->id > 0))
    {
        setEventMessage($langs->trans('NotFound'), 'warnings');
        header('Location: ' . DOL_URL_ROOT . '/public/ticket/index.php');

        exit();
    }

    $ticket_extrafields = new TicketExtrafields($db);

    $res = $ticket_extrafields->rate_ticket($ticket, $rating, $rating_comment);

    if (!($res > 0))
    {
        setEventMessage($langs->trans('ErrorRatingTicket') . ': ' . $ticket_extrafields->db->error(), 'errors');
    }
    else
    {
        setEventMessage($langs->trans('TicketRated'), 'mesgs');
    }

    header('Location: ' . DOL_URL_ROOT . '/custom/ticketutils/public/ticket/view.php?track_id=' . $ticket->$id_to_use . '&email=' . $ticket->origin_email . '&token=' . $token . '&action=view_ticket');

    exit();
}

if (GETPOSTISSET('reject_ticket'))
{
    $track_id = GETPOST('track_id');
    $token = GETPOST('token');
    $message = GETPOST('message');

    $id_to_use = $conf->global->TICKETUTILS_ONLY_ONE_ID ? 'ref' : 'track_id';

    $ticket = new Ticket($db);
    if ($id_to_use == 'ref')
    {
        $ticket->fetch(0, $track_id);
    }
    else
    {
        $ticket->fetch('', '', $track_id);
    }

    if (!($ticket->id > 0))
    {
        setEventMessage($langs->trans('NotFound'), 'warnings');
        header('Location: ' . DOL_URL_ROOT . '/public/ticket/index.php');

        exit();
    }

    $prov_user = new User($db);
    $prov_user->id = 0;
    
    $res = TicketUtilsLib::reject_ticket($ticket, $prov_user, $message);

    if (!($res > 0))
    {
        setEventMessage($langs->trans('ErrorRejectingTicket') . ': ' . $ticket->db->error(), 'errors');
    }
    else
    {
        setEventMessage($langs->trans('TicketRejected'), 'mesgs');
    }

    header('Location: ' . DOL_URL_ROOT . '/custom/ticketutils/public/ticket/view.php?track_id=' . $ticket->$id_to_use . '&email=' . $ticket->origin_email . '&token=' . $token . '&action=view_ticket');

    exit();
}
