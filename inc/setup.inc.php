<?php

require_once '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

print_r($_POST);

if (isset($_POST['update_const']))
{
    $const_value = GETPOST('const_value');
    $const_name = GETPOST('const_name');

    $res = dolibarr_set_const($db, $const_name, $const_value);

    header('Location: ' . DOL_URL_ROOT . '/custom/ticketutils/admin/setup.php?result=' . $res);

    exit();
}