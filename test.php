<?php

require_once '../../main.inc.php';

require_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/lib/ticketutils.lib.php';

require_once DOL_DOCUMENT_ROOT . '/custom/socilib/soci_lib_strings.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/socilib/soci_lib_time.class.php';

$ticketutils_lib = new TicketUtilsLib();



// exit();

/* $time = SociLibTime::calculate_active_time(strtotime('2025-03-28 07:00:00'), time(), '07:00:00', '17:00:00', [6, 7]);

echo $time;
echo '<br>';
echo $time / 3600;
echo '<br>';

$time_string = SociLibStrings::get_time_string($time, true, false);
print_r($time_string); */

$result = $ticketutils_lib->get_inactive_tickets();

foreach ($result as $ticket_info)
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
}