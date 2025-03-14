<?php
/* Copyright (C) 2013-2016  Jean-François FERRY     <hello@librethic.io>
 * Copyright (C) 2018       Frédéric France         <frederic.france@netlogic.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/public/ticket/view.php
 *       \ingroup    ticket
 *       \brief      Public file to show one ticket
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

// For MultiCompany module.
// Do not use GETPOST here, function is not defined and define must be done before including main.inc.php
$entity = (!empty($_GET['entity']) ? (int) $_GET['entity'] : (!empty($_POST['entity']) ? (int) $_POST['entity'] : 1));
if (is_numeric($entity))
{
	define("DOLENTITY", $entity);
}

// Load Dolibarr environment
require '../../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/ticket/class/actions_ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/ticket.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/payments.lib.php';

require_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/lib/ticketutils.lib.php';
require_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/class/ticket_extrafields.class.php';

require_once DOL_DOCUMENT_ROOT . '/custom/socilib/soci_lib.class.php';

/* echo '<pre>';
print_r($_POST);
echo '</pre>'; */

// Load translation files required by the page
$langs->loadLangs(array("companies", "other", "ticket", "ticketutils@ticketutils"));

SociLib::load_components([SOCILIB_MODAL]);

// Get parameters
$action   = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'aZ09');

$track_id = GETPOST('track_id', 'alpha');
$email    = GETPOST('email', 'email');
$suffix = "";

if (GETPOST('btn_view_ticket'))
{
	unset($_SESSION['email_customer']);
}
if (isset($_SESSION['email_customer']))
{
	$email = $_SESSION['email_customer'];
}

$object = new ActionsTicket($db);

if (!isModEnabled('ticket'))
{
	httponly_accessforbidden('Module Ticket not enabled');
}

$id_to_use = $conf->global->TICKETUTILS_ONLY_ONE_ID ? 'ref' : 'track_id';

/*
 * Actions
 */

if ($cancel)
{
	$action = 'view_ticket';
}

if ($action == "view_ticket" || $action == "presend" || $action == "close" || $action == "confirm_public_close" || $action == "add_message")
{
	$error = 0;
	$display_ticket = false;
	if (!strlen($track_id))
	{
		$error++;
		array_push($object->errors, $langs->trans("ErrorFieldRequired", $langs->transnoentities("TicketTrackId")));
		$action = '';
	}
	if (!strlen($email))
	{
		$error++;
		array_push($object->errors, $langs->trans("ErrorFieldRequired", $langs->transnoentities("Email")));
		$action = '';
	}
	else
	{
		if (!isValidEmail($email))
		{
			$error++;
			array_push($object->errors, $langs->trans("ErrorEmailInvalid"));
			$action = '';
		}
	}

	if (!$error)
	{
		$ret = ($id_to_use == 'ref' ? $object->fetch('', $track_id, '') : $object->fetch('', '', $track_id));

		if ($ret && $object->dao->id > 0)
		{
			// Check if emails provided is the one of author
			$emailofticket = CMailFile::getValidAddress($object->dao->origin_email, 2);
			if (strtolower($emailofticket) == strtolower($email))
			{
				$display_ticket = true;
				$_SESSION['email_customer'] = $email;
			}
			else
			{
				// Check if emails provided is inside list of contacts
				$contacts = $object->dao->liste_contact(-1, 'external');
				foreach ($contacts as $contact)
				{
					if (strtolower($contact['email']) == strtolower($email))
					{
						$display_ticket = true;
						$_SESSION['email_customer'] = $email;
						break;
					}
					else
					{
						$display_ticket = false;
					}
				}
			}
			// Check email of thirdparty of ticket
			if ($object->dao->fk_soc > 0 || $object->dao->socid > 0)
			{
				$object->dao->fetch_thirdparty();
				if ($email == $object->dao->thirdparty->email)
				{
					$display_ticket = true;
					$_SESSION['email_customer'] = $email;
				}
			}
			// Check if email is email of creator
			if ($object->dao->fk_user_create > 0)
			{
				$tmpuser = new User($db);
				$tmpuser->fetch($object->dao->fk_user_create);
				if (strtolower($email) == strtolower($tmpuser->email))
				{
					$display_ticket = true;
					$_SESSION['email_customer'] = $email;
				}
			}
			// Check if email is email of creator
			if ($object->dao->fk_user_assign > 0 && $object->dao->fk_user_assign != $object->dao->fk_user_create)
			{
				$tmpuser = new User($db);
				$tmpuser->fetch($object->dao->fk_user_assign);
				if (strtolower($email) == strtolower($tmpuser->email))
				{
					$display_ticket = true;
					$_SESSION['email_customer'] = $email;
				}
			}
		}
		else
		{
			$error++;
			array_push($object->errors, $langs->trans("ErrorTicketNotFound", $track_id));
			$action = '';
		}
	}

	if (!$error && $action == 'confirm_public_close' && $display_ticket)
	{
		if ($object->dao->close($user))
		{
			setEventMessages($langs->trans('TicketMarkedAsClosed'), null, 'mesgs');

			$url = 'view.php?action=view_ticket&track_id=' . GETPOST('track_id', 'alpha') . (!empty($entity) && isModEnabled('multicompany') ? '&entity=' . $entity : '') . '&token=' . newToken();
			header("Location: " . $url);
			exit;
		}
		else
		{
			$action = '';
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	if (!$error && $action == "add_message" && $display_ticket && GETPOSTISSET('btn_add_message'))
	{
		$_GET['track_id'] = $object->dao->track_id;

		// TODO Add message...
		$ret = $object->dao->newMessage($user, $action, 0, 1);

		if (!$error)
		{
			header('Location: ' . $_SERVER["PHP_SELF"] . '?action=view_ticket&track_id=' . $object->dao->$id_to_use);
			exit();
		}
	}

	if ($error || $errors)
	{
		setEventMessages($object->error, $object->errors, 'errors');
		if ($action == "add_message")
		{
			$action = 'presend';
		}
		else
		{
			$action = '';
		}
	}
}
//var_dump($action);
//$object->doActions($action);

// Actions to send emails (for ticket, we need to manage the addfile and removefile only)
$triggersendname = 'TICKET_SENTBYMAIL';
$paramname = 'id';
$autocopy = 'MAIN_MAIL_AUTOCOPY_TICKET_TO'; // used to know the automatic BCC to add
if (!empty($object->dao->id)) $trackid = 'tic' . $object->dao->id;
include DOL_DOCUMENT_ROOT . '/core/actions_sendmails.inc.php';



/*
 * View
 */

$form = new Form($db);
$formticket = new FormTicket($db);

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('ticketutils_ticketpublicview', 'globalcard'));

if (!$conf->global->TICKET_ENABLE_PUBLIC_INTERFACE)
{
	echo '<div class="error">' . $langs->trans('TicketPublicInterfaceForbidden') . '</div>';
	$db->close();
	exit();
}

$arrayofjs = array();
$arrayofcss = array(
	'/ticket/css/styles.css.php',
	'/custom/ticketutils/css/ticketutils.css'
);

llxHeaderTicket($langs->trans("Tickets"), "", 0, 0, $arrayofjs, $arrayofcss);

echo '<div class="ticketpublicarea ticketlargemargin centpercent">';

if ($action == "view_ticket" || $action == "presend" || $action == "close" || $action == "confirm_public_close")
{
	if ($display_ticket)
	{
		$ticket = $object->dao;

		$ticket_extrafields = new TicketExtrafields($db);
		$ticket_extrafields->fetch(0, $ticket->id);

		// Confirmation close
		if ($action == 'close')
		{
			echo $form->formconfirm($_SERVER["PHP_SELF"] . "?track_id=" . $track_id . (!empty($entity) && isModEnabled('multicompany') ? '&entity=' . $entity : ''), $langs->trans("CloseATicket"), $langs->trans("ConfirmCloseAticket"), "confirm_public_close", '', '', 1);
		}

		echo '<div id="form_view_ticket" class="margintoponly">';

		echo '<table class="ticketpublictable centpercent tableforfield">';

		// Ref
		echo '<tr><td class="titlefield">' . $langs->trans("Ref") . '</td><td>';
		echo img_picto('', 'ticket', 'class="pictofixedwidth"');
		echo dol_escape_htmltag($object->dao->ref);
		echo '</td></tr>';

		// Tracking ID
		if ($id_to_use == 'track_id')
		{
			echo '<tr><td>' . $langs->trans("TicketTrackId") . '</td><td>';
			echo dol_escape_htmltag($object->dao->track_id);
			echo '</td></tr>';
		}

		// Subject
		echo '<tr><td>' . $langs->trans("Subject") . '</td><td>';
		echo '<span class="bold">';
		echo dol_escape_htmltag($object->dao->subject);
		echo '</span>';
		echo '</td></tr>';

		// Statut
		echo '<tr>';
		echo '<td>';
		echo $langs->trans("Status");
		echo '</td>';

		echo '<td>';
		echo $object->dao->getLibStatut(2);
		echo '</td>';

		echo '</tr>';

		if ($ticket->status == Ticket::STATUS_CLOSED)
		{
			$rating = $ticket_extrafields->rating;

			echo '<tr>';

			echo '<td>';
			echo $langs->trans('Rating');
			echo '</td>';

			echo '<td>';

			if ($rating === null)
			{
				echo $langs->trans('NoRating');
			}
			else
			{
				echo '<div class="rating-container">';
				for ($i = 1; $i <= 5; $i++)
				{
					$active = $i <= $rating ? 'active' : '';
	
					echo '<i class="fas fa-star rating-item ' . $active . ' static">';
					echo '</i>';
				}
				echo '</div>';
			}
			
			echo '</td>';

			echo '</tr>';

			echo '<tr>';

			echo '<td>';
			echo $langs->trans('Comments');
			echo '</td>';

			echo '<td>';
			echo $ticket_extrafields->rating_comment ?: $langs->trans('NoComments');
			echo '</td>';

			echo '</tr>';
		}

		// Type
		echo '<tr><td>' . $langs->trans("Type") . '</><td>';
		echo dol_escape_htmltag($object->dao->type_label);
		echo '</td></tr>';

		// Category
		echo '<tr><td>' . $langs->trans("Category") . '</td><td>';
		if ($object->dao->category_label)
		{
			echo img_picto('', 'category', 'class="pictofixedwidth"');
			echo dol_escape_htmltag($object->dao->category_label);
		}
		echo '</td></tr>';

		// Severity
		echo '<tr><td>' . $langs->trans("Severity") . '</td><td>';
		echo dol_escape_htmltag($object->dao->severity_label);
		echo '</td></tr>';

		// Creation date
		echo '<tr><td>' . $langs->trans("DateCreation") . '</td><td>';
		echo dol_print_date($object->dao->datec, 'dayhour');
		echo '</td></tr>';

		// Author
		echo '<tr><td>' . $langs->trans("Author") . '</td><td>';
		if ($object->dao->fk_user_create > 0)
		{
			$langs->load("users");
			$fuser = new User($db);
			$fuser->fetch($object->dao->fk_user_create);
			echo img_picto('', 'user', 'class="pictofixedwidth"');
			echo $fuser->getFullName($langs);
		}
		else
		{
			echo img_picto('', 'email', 'class="pictofixedwidth"');
			echo dol_escape_htmltag($object->dao->origin_email);
		}

		echo '</td></tr>';

		// Read date
		if (!empty($object->dao->date_read))
		{
			echo '<tr><td>' . $langs->trans("TicketReadOn") . '</td><td>';
			echo dol_print_date($object->dao->date_read, 'dayhour');
			echo '</td></tr>';
		}

		// Close date
		if (!empty($object->dao->date_close))
		{
			echo '<tr><td>' . $langs->trans("TicketCloseOn") . '</td><td>';
			echo dol_print_date($object->dao->date_close, 'dayhour');
			echo '</td></tr>';
		}

		// User assigned
		echo '<tr><td>' . $langs->trans("AssignedTo") . '</td><td>';
		if ($object->dao->fk_user_assign > 0)
		{
			$fuser = new User($db);
			$fuser->fetch($object->dao->fk_user_assign);
			echo img_picto('', 'user', 'class="pictofixedwidth"');
			echo $fuser->getFullName($langs, 1);
		}
		echo '</td></tr>';

		// Progression
		if (!empty($conf->global->TICKET_SHOW_PROGRESSION))
		{
			echo '<tr><td>' . $langs->trans("Progression") . '</td><td>';
			echo ($object->dao->progress > 0 ? dol_escape_htmltag($object->dao->progress) : '0') . '%';
			echo '</td></tr>';
		}

		// Other attributes
		include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';

		echo '</table>';

		echo '</div>';

		echo '<div style="clear: both; margin-top: 1.5em;"></div>';

		if ($action == 'presend')
		{
			echo load_fiche_titre($langs->trans('TicketAddMessage'), '', 'conversation');

			$formticket = new FormTicket($db);

			$formticket->action = "add_message";
			$formticket->track_id = $object->dao->$id_to_use;
			$formticket->trackid = 'tic' . $object->dao->id;

			$formticket->param = array(
				'track_id' => $object->dao->$id_to_use,
				'fk_user_create' => '-1',
				'returnurl' => DOL_URL_ROOT . '/custom/ticketutils/public/ticket/view.php?' . (!empty($entity) && isModEnabled('multicompany') ? '&entity=' . $entity : '')
			);

			$formticket->withfile = 2;
			$formticket->withcancel = 1;

			$formticket->showMessageForm('100%');
		}

		if ($action != 'presend')
		{
			echo '<form method="post" id="form_view_ticket_list" name="form_view_ticket_list" action="' . DOL_URL_ROOT . '/custom/ticketutils/public/ticket/list.php' . (!empty($entity) && isModEnabled('multicompany') ? '?entity=' . $entity : '') . '">';
			echo '<input type="hidden" name="token" value="' . newToken() . '">';
			echo '<input type="hidden" name="action" value="view_ticketlist">';
			echo '<input type="hidden" name="track_id" value="' . $object->dao->track_id . '">';
			echo '<input type="hidden" name="email" value="' . $_SESSION['email_customer'] . '">';
			//echo '<input type="hidden" name="search_fk_status" value="non_closed">';
			echo "</form>\n";

			echo '<div class="tabsAction">';

			// List ticket
			echo '<div class="inline-block divButAction"><a class="left" style="padding-right: 50px" href="javascript:$(\'#form_view_ticket_list\').submit();">' . $langs->trans('ViewMyTicketList') . '</a></div>';

			if ($ticket->status < Ticket::STATUS_CLOSED)
			{
				$accept_modal = '';

				$accept_modal .= '<div class="accept-ticket-modal">';

				$accept_modal .= '<span>';
				$accept_modal .= $langs->trans('AcceptTicketDescription');
				$accept_modal .= '</span>';

				$accept_modal .= TicketUtilsLib::rating();

				$accept_modal .= '<div style="text-align: center">';

				$accept_modal .= '<b>';
				$accept_modal .= $langs->trans('Comments') . ':';
				$accept_modal .= '</b>';

				$accept_modal .= '<br>';

				$accept_modal .= '<textarea name="rating_comment">';
				$accept_modal .= '</textarea>';
				$accept_modal .= '</div>';

				$accept_modal .= '<input type="hidden" name="track_id" value="' . $ticket->$id_to_use . '">';
				$accept_modal .= '<input type="hidden" name="token" value="' . newToken() . '">';

				$accept_modal .= '</div>';

				$modal_action = DOL_URL_ROOT . '/custom/ticketutils/inc/public_ticket_view.inc.php';

				$props = new SociModalProps();
				$props->save_button_label = $langs->trans('Confirm');
				$props->cancel_button_label = $langs->trans('Cancel');

				echo SociModal::print(
					'modal_accept',
					$modal_action,
					'accept_ticket',
					$langs->trans('AcceptSolution'),
					$accept_modal,
					$props
				);

				$reject_modal = '';

				$reject_modal .= '<div class="reject-ticket-modal">';

				$reject_modal .= '<span>';
				$reject_modal .= $langs->trans('RejectTicketDescription');
				$reject_modal .= '</span>';

				$reject_modal .= '<div style="text-align: center">';

				$reject_modal .= '<textarea name="message" required>';
				$reject_modal .= '</textarea>';
				$reject_modal .= '</div>';

				$reject_modal .= '<input type="hidden" name="track_id" value="' . $ticket->$id_to_use . '">';
				$reject_modal .= '<input type="hidden" name="token" value="' . newToken() . '">';

				$reject_modal .= '</div>';

				$modal_action = DOL_URL_ROOT . '/custom/ticketutils/inc/public_ticket_view.inc.php';

				$props = new SociModalProps();
				$props->save_button_label = $langs->trans('Confirm');
				$props->cancel_button_label = $langs->trans('Cancel');

				echo SociModal::print(
					'modal_reject',
					$modal_action,
					'reject_ticket',
					$langs->trans('RejectSolution'),
					$reject_modal,
					$props
				);

				// New message
				echo '<div class="inline-block divButAction">';
				echo '<a  class="butAction" href="' . $_SERVER['PHP_SELF'] . '?action=presend&mode=init&track_id=' . $object->dao->$id_to_use . (!empty($entity) && isModEnabled('multicompany') ? '&entity=' . $entity : '') . '&token=' . newToken() . '">';
				echo $langs->trans('TicketAddMessage');
				echo '</a>';
				echo '</div>';

				// Close ticket
				if ($conf->global->TICKETUTILS_VALIDATION_STATUS)
				{
					if ($ticket->status == Ticket::STATUS_NEED_MORE_INFO)
					{
						echo '<div class="inline-block divButAction toggle-modal" data-modal-id="modal_accept">';
						echo '<a class="butAction accept">';
						echo $langs->trans('AcceptSolution');
						echo '</a>';
						echo '</div>';

						echo '<div class="inline-block divButAction">';
						echo '<a class="butAction reject toggle-modal" data-modal-id="modal_reject">';
						echo $langs->trans('RejectSolution');
						echo '</a>';
						echo '</div>';
					}
				}
				else
				{
					if ($ticket->status >= Ticket::STATUS_NOT_READ && $ticket->status < Ticket::STATUS_CLOSED)
					{
						echo '<div class="inline-block divButAction"><a  class="butAction" href="' . $_SERVER['PHP_SELF'] . '?action=close&track_id=' . $object->dao->$id_to_use . (!empty($entity) && isModEnabled('multicompany') ? '&entity=' . $entity : '') . '&token=' . newToken() . '">' . $langs->trans('CloseTicket') . '</a></div>';
					}
				}
			}

			echo '</div>';
		}

		// Message list
		echo load_fiche_titre($langs->trans('TicketMessagesList'), '', 'conversation');
		$object->viewTicketMessages(false, true, $object->dao);
	}
	else
	{
		echo '<div class="error">Not Allowed<br><a href="' . $_SERVER['PHP_SELF'] . '?track_id=' . $object->dao->track_id . (!empty($entity) && isModEnabled('multicompany') ? '?entity=' . $entity : '') . '" rel="nofollow noopener">' . $langs->trans('Back') . '</a></div>';
	}
}
else
{
	echo '<div class="center opacitymedium margintoponly marginbottomonly ticketlargemargin">' . $langs->trans("TicketPublicMsgViewLogIn") . '</div>';

	echo '<div id="form_view_ticket">';
	echo '<form method="post" name="form_view_ticket" action="' . $_SERVER['PHP_SELF'] . (!empty($entity) && isModEnabled('multicompany') ? '?entity=' . $entity : '') . '">';

	echo '<input type="hidden" name="token" value="' . newToken() . '">';
	echo '<input type="hidden" name="action" value="view_ticket">';

	echo '<p><label for="track_id" style="display: inline-block; width: 30%; "><span class="fieldrequired">' . $langs->trans("TicketTrackId") . '</span></label>';
	echo '<input size="30" id="track_id" name="track_id" value="' . (GETPOST('track_id', 'alpha') ? GETPOST('track_id', 'alpha') : '') . '" />';
	echo '</p>';

	echo '<p><label for="email" style="display: inline-block; width: 30%; "><span class="fieldrequired">' . $langs->trans('Email') . '</span></label>';
	echo '<input size="30" id="email" name="email" value="' . (GETPOST('email', 'alpha') ? GETPOST('email', 'alpha') : (!empty($_SESSION['customer_email']) ? $_SESSION['customer_email'] : "")) . '" />';
	echo '</p>';

	echo '<p style="text-align: center; margin-top: 1.5em;">';
	echo '<input type="submit" class="button" name="btn_view_ticket" value="' . $langs->trans('ViewTicket') . '" />';
	echo ' &nbsp; ';
	echo '<input type="submit" class="button button-cancel" name="cancel" value="' . $langs->trans("Cancel") . '">';
	echo "</p>\n";

	echo "</form>\n";
	echo "</div>\n";
}

echo "</div>";

// End of page
htmlPrintOnlineFooter($mysoc, $langs, 0, $suffix, $object);

llxFooter('', 'public');

$db->close();
