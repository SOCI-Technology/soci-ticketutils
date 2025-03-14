<?php

require_once '../../main.inc.php';

require_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/lib/ticketutils.lib.php';

$ticketutils_lib = new TicketUtilsLib();

/* foreach ($inactive_tickets as $ticket_info)
{
    echo $ticket_info['ticket']->ref;
    echo '<br>';

    echo 'alert_first_response: ' . $ticket_info['alert_first_response'];
    echo '<br>';

    echo 'alert_response_delay: ' . $ticket_info['alert_response_delay'];
    echo '<br>';

    echo 'alert_inactive_interventions: ' . $ticket_info['alert_inactive_interventions'];
    echo '<br>';

    echo '<br>';
} */

// exit();

$result = $ticketutils_lib->close_tickets_awaiting_validation();

echo '<pre>';
echo print_r(json_decode($ticketutils_lib->output, true));
echo '</pre>';
