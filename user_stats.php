<?php

/**
 * @var DoliDB  $db
 * @var User    $user
 */

require_once '../../main.inc.php';

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';

require_once DOL_DOCUMENT_ROOT . '/custom/socilib/soci_lib.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/socilib/soci_lib_strings.class.php';

$langs->loadLangs(['ticketutils@ticketutils']);

$title = $langs->trans('TicketUserStats');

$sort_by = GETPOST('sort_by') ?: 'total_tickets';
$order_by = GETPOST('order_by') ?: 'asc';

$can_see_all_tickets = $user->rights->ticketutils->ticket->all;

$url_string = '';

foreach ($_GET as $key => $value)
{
    if ($key != 'sort_by' && $key != 'order_by')
    {
        $url_string .= '&' . $key . '=' . urlencode($value);
    }
}

llxHeader('', $title);

echo load_fiche_titre($title, '', 'chart');

$ticket_example = new Ticket($db);

$date_start_day = GETPOST('date_startday');
$date_start_month = GETPOST('date_startmonth');
$date_start_year = GETPOST('date_startyear');

if ($date_start_day)
{
    $date_start = $date_start_year . '-' . $date_start_month . '-' . $date_start_day;
}


$date_end_day = GETPOST('date_endday');
$date_end_month = GETPOST('date_endmonth');
$date_end_year = GETPOST('date_endyear');

if ($date_end_day)
{
    $date_end = $date_end_year . '-' . $date_end_month . '-' . $date_end_day;
}

$user_id_list = GETPOST('userid');

/**
 * TICKET QUERY
 */
$sql .= "SELECT ";

foreach ($user->fields as $field => $field_info)
{
    if ($field == 'rowid')
    {
        $sql .= "u.rowid as u_rowid";
        continue;
    }

    $sql .= ", u." . $field . ' as u_' . $field;
}

foreach ($ticket_example->fields as $field => $field_info)
{
    $sql .= ", t." . $field . ' as t_' . $field;
}

//$sql .= " FROM " . MAIN_DB_PREFIX . "user as u";
$sql .= " FROM " . MAIN_DB_PREFIX . "ticket as t";

//$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "ticket as t ON t.fk_user_assign = u.rowid";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user as u ON t.fk_user_assign = u.rowid";

$sql .= " WHERE 1 = 1";

$sql .= " AND t.fk_user_assign > 0";

if ($date_start)
{
    $sql .= " AND t.datec >= '" . SociLibStrings::format_date('Y-m-d', $date_start) . "'";
}
if ($date_end)
{
    $sql .= " AND t.datec <= '" . SociLibStrings::format_date('Y-m-d', $date_end) . "'";
}

if ($user_id_list)
{
    $sql .= " AND u.rowid IN (" . join(', ', $user_id_list) . ")";
}

if (!$can_see_all_tickets)
{
    $sql .= " AND (t.fk_user_assign = '" . $user->id . "' OR t.fk_user_create = '" . $user->id . "')";
}

$ticket_resql = $db->query($sql);
$ticket_num = $db->num_rows($ticket_resql);
/**
 * END TICKET QUERY
 */

/**
 * WORK TIME QUERY
 */
$sql = "SELECT 
ee.fk_source as fk_source,
ee.sourcetype as sourcetype,
ee.fk_target as fk_target,
ee.targettype as targettype";

foreach ($ticket_example->fields as $field => $field_info)
{
    $sql .= ", t." . $field . ' as t_' . $field;
}

$sql .= "
,f.rowid as f_rowid,
f.ref as f_ref,
obs.rowid as obs_rowid,
obs.fk_user_creat as obs_fk_user_creat,
obs.duracion as obs_duracion";

foreach ($user->fields as $field => $field_info)
{
    $sql .= ", u." . $field . ' as u_' . $field;
}
$sql .= "
FROM llx_element_element as ee

INNER JOIN llx_ticket as t
ON t.rowid = CASE 
	WHEN ee.targettype = 'ticket' THEN ee.fk_target
    WHEN ee.sourcetype = 'ticket' THEN ee.fk_source
END

INNER JOIN llx_fichinter as f
ON f.rowid = CASE 
	WHEN ee.targettype = 'fichinter' THEN ee.fk_target
    WHEN ee.sourcetype = 'fichinter' THEN ee.fk_source
END

LEFT JOIN llx_observacion as obs
ON obs.fk_intervention = f.rowid

LEFT JOIN llx_user as u
ON u.rowid = obs.fk_user_creat

WHERE ((ee.sourcetype = 'ticket' AND ee.targettype = 'fichinter')
OR (ee.sourcetype = 'fichinter' AND ee.targettype = 'ticket'))
";
//AND t.fk_user_assign > 0";

$work_time_resql = $db->query($sql);
/**
 * END WORK TIME QUERY
 */

$user_tickets = [];

for ($i = 0; $i < $db->num_rows($ticket_resql); $i++)
{
    $row = $db->fetch_object($ticket_resql);

    $row_user = new User($db);
    $ticket = new Ticket($db);

    foreach ($row_user->fields as $field => $field_info)
    {
        $field_name = "u_" . $field;

        if ($field == 'rowid')
        {
            $row_user->id = $row->$field_name;
            continue;
        }

        $row_user->$field = $row->$field_name;
    }

    foreach ($ticket->fields as $field => $field_info)
    {
        $field_name = "t_" . $field;

        if ($field == 'rowid')
        {
            $ticket->id = $row->$field_name;
            continue;
        }

        $ticket->$field = $row->$field_name;
    }

    if (!isset($user_tickets[$row_user->id]))
    {
        $user_tickets[$row_user->id] = [
            'user' => $row_user,
            'tickets' => [],
            'all_tickets' => [],
        ];
    }

    if (!($ticket->id > 0))
    {
        continue;
    }

    $user_tickets[$row_user->id]['tickets'][$ticket->id] = $ticket;
    $user_tickets[$row_user->id]['all_tickets'][$ticket->id] = $ticket;
}

$user_work_time = [];

for ($i = 0; $i < $db->num_rows($work_time_resql); $i++)
{
    $row = $db->fetch_object($work_time_resql);

    $row_user = new User($db);
    $ticket = new Ticket($db);

    foreach ($row_user->fields as $field => $field_info)
    {
        $field_name = "u_" . $field;

        if ($field == 'rowid')
        {
            $row_user->id = $row->$field_name;
            continue;
        }

        $row_user->$field = $row->$field_name;
    }

    foreach ($ticket->fields as $field => $field_info)
    {
        $field_name = "t_" . $field;

        if ($field == 'rowid')
        {
            $ticket->id = $row->$field_name;
            continue;
        }

        $ticket->$field = $row->$field_name;
    }

    if (!isset($user_work_time[$row->obs_fk_user_creat]))
    {
        $user_work_time[$row->obs_fk_user_creat] = 0;
    }

    if (!isset($user_tickets[$row->obs_fk_user_creat]))
    {
        $user_tickets[$row->obs_fk_user_creat] = [
            'user' => $row_user,
            'tickets' => []
        ];
    }

    $user_work_time[$row->obs_fk_user_creat] += $row->obs_duracion;

    $user_tickets[$row->obs_fk_user_creat]['all_tickets'][$ticket->id] = $ticket;
}

$user_stat_list = [];

$totals = [
    'total_tickets' => 0,
    'total_tickets_closed' => 0,
    'total_tickets_abandoned' => 0,
    'total_tickets_open' => 0,
    'total_close_time' => 0,
    'avg_close_time' => 0,
    'total_work_time' => 0,
    'avg_work_time' => 0
];

foreach ($user_tickets as $user_id => $user_tickets_info)
{
    $current_user = $user_tickets_info['user'];
    $ticket_list = $user_tickets_info['tickets'];
    $all_ticket_list = $user_tickets_info['all_tickets'];

    $total_tickets = count($ticket_list);
    $all_total_tickets = count($all_ticket_list);
    $total_tickets_closed = 0;
    $total_tickets_abandoned = 0;
    $total_tickets_open = 0;

    $total_close_time = 0;
    $total_work_time = $user_work_time[$current_user->id] ?? 0;

    foreach ($ticket_list as $ticket)
    {
        $ticket->fetchObjectLinked();

        if (in_array($ticket->fk_statut, [Ticket::STATUS_CLOSED]))
        {
            $total_tickets_closed++;

            $start_time = strtotime($ticket->datec);
            $end_time = strtotime($ticket->date_close);

            $total_close_time += ($end_time - $start_time);

            continue;
        }

        if (in_array($ticket->fk_statut, [Ticket::STATUS_CANCELED]))
        {
            $total_tickets_abandoned++;
            continue;
        }

        $total_tickets_open++;
    }
    
    $avg_close_time = $total_tickets_closed > 0 ? $total_close_time / $total_tickets_closed : 0;
    $avg_work_time = $all_total_tickets > 0 ? $total_work_time / $all_total_tickets : 0;

    $user_stat_list[$user_id] = [
        'user' => $current_user,
        'all_total_tickets' => $all_total_tickets,
        'total_tickets' => $total_tickets,
        'total_tickets_closed' => $total_tickets_closed,
        'total_tickets_abandoned' => $total_tickets_abandoned,
        'total_tickets_open' => $total_tickets_open,
        'avg_close_time' => $avg_close_time,
        'total_close_time' => $total_close_time,
        'avg_work_time' => $avg_work_time,
        'total_work_time' => $total_work_time
    ];

    $totals['total_tickets'] += $total_tickets;
    $totals['total_tickets_closed'] += $total_tickets_closed;
    $totals['total_tickets_abandoned'] += $total_tickets_abandoned;
    $totals['total_tickets_open'] += $total_tickets_open;
    $totals['total_close_time'] += $total_close_time;
    $totals['total_work_time'] += $total_work_time;
}

$totals['avg_close_time'] = $totals['total_tickets_closed'] > 0 ? $totals['total_close_time'] / $totals['total_tickets_closed'] : 0;
$totals['avg_work_time'] = $totals['total_tickets'] > 0 ? $totals['total_work_time'] / $totals['total_tickets'] : 0;

$headers = [
    'user' => $langs->trans('User'),
    'all_total_tickets' => $langs->trans('TotalTicketsLinked'),
    'total_tickets' => $langs->trans('TotalTicketsAssigned'),
    'total_tickets_open' => $langs->trans('TotalTicketsOpen'),
    'total_tickets_closed' => $langs->trans('TotalTicketsClosed'),
    'total_tickets_abandoned' => $langs->trans('TotalTicketsAbandoned'),
    'avg_close_time' => $langs->trans('AvgCloseTime'),
    'total_work_time' => $langs->trans('TotalWorkTime'),
    'avg_work_time' => $langs->trans('AvgWorkTime'),
    'latest_ticket' => $langs->trans('LatestTicket'),
    'subject' => $langs->trans('Subject')
];

/**
 * TABLE
 */
#region table
echo '<form method="GET">';

echo '<input type="hidden" name="sort_by" value="' . $sort_by . '">';
echo '<input type="hidden" name="order_by" value="' . $order_by . '">';

echo '<table class="noborder centpercent">';

/**
 * Header
 */
#region header
$form = new Form($db);

echo '<thead>';

/**
 * FILTERS
 */
#region filters
echo '<tr class="liste_titre">';

/**
 * Date range
 */
echo '<th colspan="4">';
echo '<span>';
echo $langs->trans('From') . ' / ' . $langs->trans('Until') . ' ';
echo '</span>';

echo $form->selectDateToDate($date_start, $date_end, 'date');

echo '</th>';
/**
 * END Date range
 */

echo '<th colspan="6">';

echo '<i class="fas fa-user paddingright"></i>';

echo $form->select_dolusers(
    $user_id_list,
    'userid',
    0,
    null,
    0,
    '',
    '',
    '0',
    0,
    0,
    '',
    0,
    '',
    '',
    0,
    0,
    true
);
echo '</th>';

echo '<th class="right">';

echo '<button class="liste_titre button_search reposition">';
echo '<i class="fas fa-search"></i>';
echo '</button>';

echo '<a class="liste_titre button_removefilter reposition" href="' . DOL_URL_ROOT . '/custom/ticketutils/user_stats.php">';
echo '<i class="fas fa-times"></i>';
echo '</a>';

echo '</th>';

echo '</tr>';
#endregion filters
/**
 * END FILTERS
 */

echo '<tr class="liste_titre">';

foreach ($headers as $key => $header)
{
    $is_current_sort = $key == $sort_by;
    $direction = $is_current_sort ? ($order_by == 'desc' ? 'asc' : 'desc') : 'desc';

    $icon = $is_current_sort ? ('<i class="fas fa-caret-' . ($order_by == 'desc' ? 'down' : 'up') . '"></i>') : '';

    echo '<th class="nowrap">';
    echo '<a href="' . DOL_URL_ROOT . '/custom/ticketutils/user_stats.php?sort_by=' . urlencode($key) . '&order_by=' . urlencode($direction) . $url_string . '">';
    echo $icon;
    echo ' ';
    echo $header;
    echo '</a>';
    echo '</th>';
}


echo '</tr>';
echo '</thead>';
#endregion
/**
 * End header
 */

/**
 * Totals
 */
#region Totals
echo '<tr class="liste_total">';

echo '<td>';
echo $langs->trans('Total');
echo '</td>';

echo '<td>';
echo $totals['total_tickets'];
echo '</td>';

echo '<td>';
echo $totals['total_tickets_open'];
echo '</td>';

echo '<td>';
echo $totals['total_tickets_closed'];
echo '</td>';

echo '<td>';
echo $totals['total_tickets_abandoned'];
echo '</td>';

echo '<td>';
echo SociLibStrings::get_time_string($totals['avg_close_time'], true, false);
echo '</td>';

echo '<td>';
echo SociLibStrings::get_time_string($totals['total_work_time'], true, false);
echo '</td>';

echo '<td>';
echo SociLibStrings::get_time_string($totals['avg_work_time'], true, false);
echo '</td>';

echo '<td>';
echo '</td>';

echo '<td>';
echo '</td>';

echo '<td>';
echo '</td>';

echo '</tr>';
#endregion
/**
 * End totals
 */

/**
 * Body
 */
#region body
echo '<tbody>';

uasort($user_stat_list, function ($a, $b) use ($sort_by, $order_by)
{
    if ($a[$sort_by] == $b[$sort_by])
    {
        return 0;
    }

    if ($order_by == 'asc')
    {
        return $a[$sort_by] > $b[$sort_by] ? 1 : -1;
    }

    return $a[$sort_by] < $b[$sort_by] ? 1 : -1;
});

if (!($ticket_num > 0))
{
    echo '<tr class="liste_total">';
    echo '<td colspan="11">';
    echo $langs->trans('NoResults');
    echo '</td>';
    echo '</tr>';
}

foreach ($user_stat_list as $user_stats)
{
    $current_user = $user_stats['user'];

    $tickets = $user_tickets[$current_user->id]['all_tickets'];

    /** @var Ticket|null */
    $last_ticket = null;

    foreach ($tickets as $ticket)
    {
        if ($ticket->id > $last_ticket->id || !$last_ticket)
        {
            $last_ticket = $ticket;
        }
    }

    $time_string = SociLibStrings::get_time_string($user_stats['avg_close_time'], false, false);
    $total_work_time_string = SociLibStrings::get_time_string($user_stats['total_work_time'], false, false);
    $avg_work_time_string = SociLibStrings::get_time_string($user_stats['avg_work_time'], false, false);

    echo '<tr>';
    echo '<td>';
    echo $current_user->getNomUrl(-1);
    echo '</td>';

    echo '<td>';
    echo $user_stats['all_total_tickets'];
    echo '</td>';

    echo '<td>';
    echo $user_stats['total_tickets'];
    echo '</td>';

    echo '<td>';
    echo $user_stats['total_tickets_open'];
    echo '</td>';

    echo '<td>';
    echo $user_stats['total_tickets_closed'];
    echo '</td>';

    echo '<td>';
    echo $user_stats['total_tickets_abandoned'];
    echo '</td>';

    echo '<td>';
    echo $time_string['string'] ?: $langs->trans('NA');
    echo '</td>';

    echo '<td>';
    echo $total_work_time_string['string'] ?: $langs->trans('NA');
    echo '</td>';

    echo '<td>';
    echo $avg_work_time_string['string'] ?: $langs->trans('NA');
    echo '</td>';

    echo '<td>';
    echo $last_ticket ? $last_ticket->getNomUrl(1) : $langs->trans('NA');
    echo '</td>';

    echo '<td>';
    echo $last_ticket ? $last_ticket->subject : $langs->trans('NA');
    echo '</td>';

    echo '</tr>';
}

echo '</tbody>';
#endregion
/**
 * End body
 */

echo '</table>';

echo '</form>';
#endregion
/**
 * END TABLE
 */

llxFooter();
