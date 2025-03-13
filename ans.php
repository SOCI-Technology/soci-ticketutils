<?php

/**
 * @param   DoliDB  $db
 */

require_once '../../main.inc.php';

require_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/lib/ticketutils.lib.php';

require_once DOL_DOCUMENT_ROOT . '/custom/socilib/soci_lib.class.php';

SociLib::load_components([SOCILIB_CONST_FIELD, SOCILIB_MAIN_BUTTON]);

$css = [
    '/custom/ticketutils/css/ans.css'
];

llxHeader('', $langs->trans('ANS'), '', '', 0, 0, '', $css);

$title = $langs->trans('AnsSetup');

echo load_fiche_titre($title, '', 'tools');

$head = TicketUtilsLib::ans_prepare_head();

echo dol_get_fiche_head($head, 'general', $title, -1, 'ticket');

$form_action = DOL_URL_ROOT . '/custom/ticketutils/inc/ans.inc.php';

$form = new Form($db);

echo '<table class="noborder centpercent">';

/**
 * Header
 */
echo '<thead>';
echo '<tr class="liste_titre">';

echo '<th>';
echo $langs->trans('Setting');
echo '</th>';

echo '<th>';
echo '</th>';

echo '</tr>';
echo '</thead>';
/**
 * End header
 */

/**
 * Body
 */
echo '<tbody>';

/**
 * Delay before first answer
 */
echo '<tr>';

echo '<td>';
echo $langs->trans('TicketsDelayBeforeFirstAnswer');
echo '</td>';

echo '<td>';
echo SociConstField::print('TICKET_DELAY_BEFORE_FIRST_RESPONSE', 'number', $setup_action);
echo '</td>';

echo '</tr>';
/**
 * End delay before first answer
 */

/**
 * Delay between answers
 */
echo '<tr>';

echo '<td>';
echo $langs->trans('TicketsDelayBetweenAnswers');
echo '</td>';

echo '<td>';
echo SociConstField::print('TICKET_DELAY_SINCE_LAST_RESPONSE', 'number', $setup_action);
echo '</td>';

echo '</tr>';
/**
 * End delay between answers
 */

/**
 * Users to alert when delay
 */
$users_to_alert_ids = explode(';', $conf->global->TICKETUTILS_USERS_TO_ALERT_WHEN_DELAY);

$users_to_alert = [];

foreach ($users_to_alert_ids as $user_id)
{
    $user_to_alert = new User($db);
    $user_to_alert->fetch($user_id);

    if ($user_to_alert->id > 0)
    {
        $users_to_alert[] = $user_to_alert;
    }
}

echo '<tr>';

echo '<td>';
echo $langs->trans('SetupUsersToAlertWhenDelay');
echo '</td>';

echo '<td>';

echo '<div>';

// Select
echo '<form method="POST" action="' . $form_action . '">';

echo $form->select_dolusers('', 'user_id', 0, $users_to_alert_ids);

echo '<button class="butAction" name="add_user_to_alert">';
echo '<i class="fas fa-plus small"></i>';
echo '</button>';

echo '</form>';
// End select

echo '<div class="user-to-alert-list">';
foreach ($users_to_alert as $user_to_alert)
{
    echo '<div class="user-to-alert-item">';

    echo $user_to_alert->getNomUrl(1);

    echo '<form method="POST" action=' . $form_action . '>';

    $props = new SociMainButtonProps();
    $props->name = 'remove_user_to_alert';

    echo SociMainButton::print('<i class="fas fa-trash"></i>', $props);

    echo '<input type="hidden" name="user_id" value="' . $user_to_alert->id . '">';

    echo '</form>';

    echo '</div>';
}
echo '</div>';

echo '</div>';

echo '</td>';

echo '</tr>';
/**
 * End users to alert when delay
 */

/**
 * Validation status
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
 * End validation status
 */

/**
 * Text of email after creating ticket
 */
$mail_mesg_new = getDolGlobalString("TICKET_MESSAGE_MAIL_NEW", $langs->trans('TicketNewEmailBody'));
echo '<tr>';

echo '<td>';
echo $form->textwithpicto($langs->trans("TicketNewEmailBodyLabel"), $langs->trans("TicketNewEmailBodyHelp"), 1, 'help');
echo '</label>';
echo '</td>';

echo '<td>';
require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
$doleditor = new DolEditor('const_value', $mail_mesg_new, '100%', 120, 'dolibarr_mailings', '', false, true, getDolGlobalInt('FCKEDITOR_ENABLE_MAIL'), ROWS_2, 70);

echo '<form 
method="POST"
action="' . $form_action . '"
style="display: flex; align-items: center; gap: 6px;"
>';
$doleditor->Create();

$props = new SociMainButtonProps();
$props->class = 'btn-green';
$props->name = 'update_const';
echo SociMainButton::print($langs->trans('Save'), $props);

echo '<input type="hidden" name="const_name" value="TICKET_MESSAGE_MAIL_NEW">';

echo '</form>';

echo '</td>';
echo '</tr>';
/**
 * End text of email after creating ticket
 */

echo '</tbody>';
/**
 * End body
 */

echo '</table>';

llxFooter();
