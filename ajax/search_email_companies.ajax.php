<?php

if (!defined('NOTOKENRENEWAL'))
{
    define('NOTOKENRENEWAL', '1'); // Disables token renewal
}
if (!defined('NOREQUIREHTML'))
{
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX'))
{
    define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIRESOC'))
{
    define('NOREQUIRESOC', '1');
}
// You can get information if module "Agenda" has been enabled by reading the
if (!defined('NOREQUIREMENU'))
{
    define('NOREQUIREMENU', '1');
}
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

include_once '../../../main.inc.php';

$email = GETPOST('email', 'custom', 0, FILTER_VALIDATE_EMAIL);


if (!isModEnabled('ticket'))
{
    httponly_accessforbidden('Module Ticket not enabled');
}

if (empty($conf->global->TICKETUTILS_SELECT_COMPANY))
{
    httponly_accessforbidden('Option TICKETUTILS_SELECT_COMPANY of module ticket is not enabled');
}

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';

require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

$ticket = new Ticket($db);
/** @var Contact[] */
$contact_list = $ticket->searchContactByEmail($email);
$thirdparty_list = $ticket->searchSocidByEmail($origin_email, '0');

if (!is_array($contact_list))
{
    $contact_list = [];
}
if (!is_array($thirdparty_list))
{
    $thirdparty_list = [];
}

$result = [];

foreach ($thirdparty_list as $thirdparty)
{
    $data = [
        'name' => $thirdparty->getFullName($langs),
        'id' => $thirdparty->id
    ];

    $result[$thirdparty->id] = $data;
}

foreach ($contact_list as $contact)
{
    if (!($contact->socid > 0))
    {
        continue;
    }

    $contact->fetch_thirdparty();

    $thirdparty = $contact->thirdparty;

    $data = [
        'name' => $thirdparty->getFullName($langs),
        'id' => $thirdparty->id
    ];

    $result[$thirdparty->id] = $data;
}

$return = [
    'companies' => array_values($result)
];

echo json_encode($return);

exit();
