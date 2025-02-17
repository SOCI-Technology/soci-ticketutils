<?php

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/lib/ticketutils.lib.php';

require_once DOL_DOCUMENT_ROOT . '/custom/socilib/soci_lib.class.php';

$langs->load("admin");
$langs->load("install");
$langs->load("errors");
$langs->load("ticketutils@ticketutils");

if (!$user->admin) accessforbidden();

SociLib::load_components(['const_field']);

$value = GETPOST('value', 'alpha');
$action = GETPOST('action', 'alpha');

$title = $langs->trans("TicketUtilsSetup");
$helpurl = "ES:ticketutils";

$css = array('/custom/ticketutils/css/ticketutils.css');

llxHeader("", $title, $helpurl, '', 0, 0, '', $css);

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">' . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($title, $linkback, 'title_setup');
$head = TicketUtilsLib::admin_prepare_head();

print dol_get_fiche_head($head, 'setup', $title, -1, 'logo@ticketutils');

$back = DOL_URL_ROOT . '/custom/ticketutils/admin/setup.php';

$form = new Form($db);

$setup_action = DOL_URL_ROOT . '/custom/ticketutils/inc/setup.inc.php';

echo '<table class="noborder centpercent">';

/**
 * HEADERS
 */
echo '<tr class="liste_titre">';

echo '<th>';
echo $langs->trans('Name');
echo '</th>';

echo '<th>';
echo $langs->trans('Options');
echo '</th>';

echo '</tr>';
/**
 * END HEADERS
 */

/**
 * REQUIRE CHANGE STATUS NOTE
 */
echo '<tr>';

echo '<td>';
echo $langs->trans('SetupRequireChangeStatusNote');
echo '</td>';

echo '<td>';
echo SociConstField::print('TICKETUTILS_REQUIRE_CHANGE_STATUS_NOTE', 'boolean', $setup_action);
echo '</td>';

echo '</tr>';
/**
 * END REQUIRE CHANGE STATUS NOTE
 */

/**
 * TICKET MESSAGE SHOW CHARACTER COUNT
 */
echo '<tr>';

echo '<td>';
echo $langs->trans('SetupTicketMessageShowCharacterCount');
echo '</td>';

echo '<td>';
echo SociConstField::print('TICKETUTILS_TICKET_MESSAGE_SHOW_CHARACTER_COUNT', 'boolean', $setup_action);
echo '</td>';

echo '</tr>';
/**
 * END TICKET MESSAGE SHOW CHARACTER COUNT
 */

/**
 * INACTIVE TICKET NOTIFICATION
 */
echo '<tr>';

echo '<td>';
echo $langs->trans('SetupInactiveTicketNotification');
echo '</td>';

echo '<td>';
echo SociConstField::print('TICKETUTILS_INACTIVE_TICKET_NOTIFICATION', 'boolean', $setup_action);
echo '</td>';

echo '</tr>';
/**
 * END INACTIVE TICKET NOTIFICATION
 */

/**
 * HELP REQUEST
 */
echo '<tr>';

echo '<td>';
echo $langs->trans('SetupHelpRequest');
echo '</td>';

echo '<td>';
echo SociConstField::print('TICKETUTILS_HELP_REQUEST', 'boolean', $setup_action);
echo '</td>';

echo '</tr>';
/**
 * END HELP REQUEST
 */

/**
 * STATISTICS
 */
echo '<tr>';

echo '<td>';
echo $langs->trans('SetupStatistics');
echo '</td>';

echo '<td>';
echo SociConstField::print('TICKETUTILS_STATISTICS', 'boolean', $setup_action);
echo '</td>';

echo '</tr>';
/**
 * END STATISTICS
 */

/**
 * VALIDATION STATUS
 */
echo '<tr>';

echo '<td>';
echo $langs->trans('SetupValidationStatus');
echo '</td>';

echo '<td>';
echo SociConstField::print('TICKETUTILS_VALIDATION_STATUS', 'boolean', $setup_action);
echo '</td>';

echo '</tr>';
/**
 * END VALIDATION STATUS
 */

/**
 * VALIDATION STATUS CLOSING TIME HOURS
 */
if ($conf->global->TICKETUTILS_VALIDATION_STATUS)
{
    echo '<tr>';

    echo '<td>';
    echo $langs->trans('SetupValidationStatusClosingTimeHours');
    echo '</td>';

    echo '<td>';
    echo SociConstField::print('TICKETUTILS_VALIDATION_STATUS_CLOSING_TIME_HOURS', 'number', $setup_action);
    echo '</td>';

    echo '</tr>';
}
/**
 * END VALIDATION STATUS CLOSING TIME HOURS
 */

/**
 * SELECT COMPANY
 */
echo '<tr>';

echo '<td>';
echo $langs->trans('SetupSelectCompany');
echo '</td>';

echo '<td>';
echo SociConstField::print('TICKETUTILS_SELECT_COMPANY', 'boolean', $setup_action);
echo '</td>';

echo '</tr>';
/**
 * END SELECT COMPANY
 */

/**
 * ONLY ONE ID
 */
echo '<tr>';

echo '<td>';
echo $langs->trans('SetupOnlyOneId');
echo '</td>';

echo '<td>';
echo SociConstField::print('TICKETUTILS_ONLY_ONE_ID', 'boolean', $setup_action);
echo '</td>';

echo '</tr>';
/**
 * END ONLY ONE ID
 */

/**
 * ALTER STATUS LOGIC
 */
echo '<tr>';

echo '<td>';
echo $langs->trans('SetupAlterStatusLogic');
echo '</td>';

echo '<td>';
echo SociConstField::print('TICKETUTILS_ALTER_STATUS_LOGIC', 'boolean', $setup_action);
echo '</td>';

echo '</tr>';
/**
 * END ALTER STATUS LOGIC
 */

echo '</table>';

print dol_get_fiche_end();
$db->close();
llxFooter();
