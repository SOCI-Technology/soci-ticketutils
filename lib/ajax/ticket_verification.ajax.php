<?php

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
if (!defined('NOTOKENRENEWAL'))
{
	define('NOTOKENRENEWAL', '1');
}

require_once '../../../../main.inc.php';

require_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/lib/ticketutils.lib.php';

function send_response($message, $data, $status = 200)
{
    $response = [
        "message" => $message,
        "data" => $data
    ];

    header('Content-Type: application/json');
    http_response_code($status);

    $response = json_encode($response);
    echo $response;
    exit();
}

$action = GETPOST('action');

if ($action == 'create_verification')
{
    $email = GETPOST('email', 'alpha');
    
    $res = TicketUtilsLib::create_ticket_verification($email);

    $response = $res > 0 ? 'Success' : 'Error';
    $status = $res > 0 ? 200 : 400;

    send_response($response, [], $status);
}

if ($action == 'check_verification')
{
    $email = GETPOST('email', 'alpha');
    $code = GETPOST('code', 'alpha');

    $res = TicketUtilsLib::check_ticket_verification($code, $email);

    $response = $res > 0 ? 'Success' : 'Error';
    $status = $res > 0 ? 200 : 400;
    $data = [
        "result" => $res
    ];

    send_response($response, $data, $status);
}

send_response($action, []);