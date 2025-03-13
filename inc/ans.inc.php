<?php

/**
 * @var DoliDB $db
 */

require_once '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

// print_r($_POST);

if (isset($_POST['update_const']))
{
    $const_value = GETPOST('const_value', 'restricthtml');
    $const_name = GETPOST('const_name');

    $res = dolibarr_set_const($db, $const_name, $const_value);

    if (!($res > 0))
    {
        setEventMessage($langs->trans('ErrorSavingSetting'), 'errors');
    }
    else
    {
        setEventMessage($langs->trans('SettingSaved'));
    }

    header('Location: ' . DOL_URL_ROOT . '/custom/ticketutils/ans.php');

    exit();
}

if (isset($_POST['add_user_to_alert']))
{
    $user_id = GETPOST('user_id');

    $current_users = $conf->global->TICKETUTILS_USERS_TO_ALERT_WHEN_DELAY;

    $new_users = $current_users;

    if ($current_users)
    {
        $new_users .= ';';
    }

    $new_users .= $user_id;

    $res = dolibarr_set_const($db, 'TICKETUTILS_USERS_TO_ALERT_WHEN_DELAY', $new_users);

    if (!($res > 0))
    {
        setEventMessage($langs->trans('ErrorSavingSetting') . ': ' . $db->error(), 'errors');
    }
    else
    {
        setEventMessage($langs->trans('SettingSaved'));
    }

    header('Location: ' . DOL_URL_ROOT . '/custom/ticketutils/ans.php');

    exit();
}

if (isset($_POST['remove_user_to_alert']))
{
    $user_id = GETPOST('user_id');

    $current_user_ids = explode(';', $conf->global->TICKETUTILS_USERS_TO_ALERT_WHEN_DELAY);

    $new_users_list = [];
    
    foreach ($current_user_ids as $current_user_id)
    {
        if ($current_user_id != $user_id)
        {
            $new_users_list[] = $current_user_id;
        }
    }

    $new_users = join(';', $new_users_list);

    $res = dolibarr_set_const($db, 'TICKETUTILS_USERS_TO_ALERT_WHEN_DELAY', $new_users);

    if (!($res > 0))
    {
        setEventMessage($langs->trans('ErrorSavingSetting') . ': ' . $db->error(), 'errors');
    }
    else
    {
        setEventMessage($langs->trans('SettingSaved'));
    }

    header('Location: ' . DOL_URL_ROOT . '/custom/ticketutils/ans.php');

    exit();
}
