<?php

class TicketUtilsCreateTicketHooks
{
    public static function fix_show_errors($object)
    {
        if (!($object->errors) && !($object->error))
        {
            return;
        }

        setEventMessages($object->error, $object->errors, 'errors', '', 1);
    }

    public static function add_message_character_count()
    {
        global $langs;

        $langs->load('ticketutils@ticketutils');

        echo '<div id="character_count">';

        echo '<span id="current_count" data-value="0">';
        echo '0';
        echo '</span>';

        echo '<span>';
        echo '/';
        echo '</span>';

        echo '<span id="max_count" data-value="65000">';
        echo '65000';
        echo '</span>';

        echo '<span class="paddingleft">';
        echo $langs->trans('Characters');
        echo '</span>';

        echo '</div>';

        echo '<script src="' . DOL_URL_ROOT . '/custom/ticketutils/js/ticket_message_character_count.js?time=' . time() . '"></script>';
    }

    /**
     * @param Ticket $object
     */
    public static function replace_create_ticket(&$object, &$action)
    {
        global $langs, $conf, $db, $user, $with_contact, $extrafields;

        /* echo '<pre>';
        print_r($_POST);
        echo '</pre>';

        exit(); */

        $error = 0;
        $origin_email = GETPOST('email', 'alpha');
        if (empty($origin_email))
        {
            $error++;
            array_push($object->errors, $langs->trans("ErrorFieldRequired", $langs->transnoentities("Email")));
            $action = '';
        }
        else
        {
            // Search company saved with email
            $searched_companies = $object->searchSocidByEmail($origin_email, '0');

            // Chercher un contact existant avec cette adresse email
            // Le premier contact trouvé est utilisé pour déterminer le contact suivi
            $contacts = $object->searchContactByEmail($origin_email);

            // Ensure that contact is active and select first active contact
            $cid = -1;
            foreach ($contacts as $key => $contact)
            {
                if ((int) $contact->statut == 1)
                {
                    $cid = $key;
                    break;
                }
            }

            // Option to require email exists to create ticket
            if (!empty($conf->global->TICKET_EMAIL_MUST_EXISTS) && ($cid < 0 || empty($contacts[$cid]->socid)))
            {
                $error++;
                array_push($object->errors, $langs->trans("ErrorEmailMustExistToCreateTicket"));
                $action = '';
            }
        }

        $contact_lastname = '';
        $contact_firstname = '';
        $company_name = '';
        $contact_phone = '';
        if ($with_contact)
        {
            // set linked contact to add in form
            if (is_array($contacts) && count($contacts) == 1)
            {
                $with_contact = current($contacts);
            }

            // check mandatory fields on contact
            $contact_lastname = trim(GETPOST('contact_lastname', 'alphanohtml'));
            $contact_firstname = trim(GETPOST('contact_firstname', 'alphanohtml'));
            $company_name = trim(GETPOST('company_name', 'alphanohtml'));
            $contact_phone = trim(GETPOST('contact_phone', 'alphanohtml'));
            if (!($with_contact->id > 0))
            {
                // check lastname
                if (empty($contact_lastname))
                {
                    $error++;
                    array_push($object->errors, $langs->trans('ErrorFieldRequired', $langs->transnoentities('Lastname')));
                    $action = '';
                }
                // check firstname
                if (empty($contact_firstname))
                {
                    $error++;
                    array_push($object->errors, $langs->trans('ErrorFieldRequired', $langs->transnoentities('Firstname')));
                    $action = '';
                }
            }
        }

        if (!GETPOST("subject", "restricthtml"))
        {
            $error++;
            array_push($object->errors, $langs->trans("ErrorFieldRequired", $langs->transnoentities("Subject")));
            $action = '';
        }
        if (!GETPOST("message", "restricthtml"))
        {
            $error++;
            array_push($object->errors, $langs->trans("ErrorFieldRequired", $langs->transnoentities("Message")));
            $action = '';
        }

        // Check email address
        if (!empty($origin_email) && !isValidEmail($origin_email))
        {
            $error++;
            array_push($object->errors, $langs->trans("ErrorBadEmailAddress", $langs->transnoentities("email")));
            $action = '';
        }

        // Check Captcha code if is enabled
        if (!empty($conf->global->MAIN_SECURITY_ENABLECAPTCHA_TICKET))
        {
            $sessionkey = 'dol_antispam_value';
            $ok = (array_key_exists($sessionkey, $_SESSION) === true && (strtolower($_SESSION[$sessionkey]) === strtolower(GETPOST('code', 'restricthtml'))));
            if (!$ok)
            {
                $error++;
                array_push($object->errors, $langs->trans("ErrorBadValueForCode"));
                $action = '';
            }
        }

        if ($error)
        {
            return;
        }

        $object->type_code = GETPOST("type_code", 'aZ09');
        $object->category_code = GETPOST("category_code", 'aZ09');
        $object->severity_code = GETPOST("severity_code", 'aZ09');
        $object->ip = getUserRemoteIP();

        $nb_post_max = getDolGlobalInt("MAIN_SECURITY_MAX_POST_ON_PUBLIC_PAGES_BY_IP_ADDRESS", 200);
        $now = dol_now();
        $minmonthpost = dol_time_plus_duree($now, -1, "m");

        // Calculate nb of post for IP
        $nb_post_ip = 0;
        if ($nb_post_max > 0)
        {    // Calculate only if there is a limit to check
            $sql = "SELECT COUNT(ref) as nb_tickets";
            $sql .= " FROM " . MAIN_DB_PREFIX . "ticket";
            $sql .= " WHERE ip = '" . $db->escape($object->ip) . "'";
            $sql .= " AND datec > '" . $db->idate($minmonthpost) . "'";
            $resql = $db->query($sql);
            if ($resql)
            {
                $num = $db->num_rows($resql);
                $i = 0;
                while ($i < $num)
                {
                    $i++;
                    $obj = $db->fetch_object($resql);
                    $nb_post_ip = $obj->nb_tickets;
                }
            }
        }

        $object->track_id = generate_random_id(16);

        $object->db->begin();

        $object->subject = GETPOST("subject", "restricthtml");
        $object->message = GETPOST("message", "restricthtml");
        $object->origin_email = $origin_email;

        $object->type_code = GETPOST("type_code", 'aZ09');
        $object->category_code = GETPOST("category_code", 'aZ09');
        $object->severity_code = GETPOST("severity_code", 'aZ09');

        if (!is_object($user))
        {
            $user = new User($db);
        }

        // create third-party with contact
        $usertoassign = 0;
        if ($with_contact && !($with_contact->id > 0))
        {
            $company = new Societe($db);
            if (!empty($company_name))
            {
                $company->name = $company_name;
            }
            else
            {
                $company->particulier = 1;
                $company->name = dolGetFirstLastname($contact_firstname, $contact_lastname);
            }
            $result = $company->create($user);
            if ($result < 0)
            {
                $error++;
                $errors = ($company->error ? array($company->error) : $company->errors);
                array_push($object->errors, $errors);
                $action = 'create_ticket';
            }

            // create contact and link to this new company
            if (!$error)
            {
                $with_contact->email = $origin_email;
                $with_contact->lastname = $contact_lastname;
                $with_contact->firstname = $contact_firstname;
                $with_contact->socid = $company->id;
                $with_contact->phone_pro = $contact_phone;
                $result = $with_contact->create($user);
                if ($result < 0)
                {
                    $error++;
                    $errors = ($with_contact->error ? array($with_contact->error) : $with_contact->errors);
                    array_push($object->errors, $errors);
                    $action = 'create_ticket';
                }
                else
                {
                    $contacts = array($with_contact);
                }
            }
        }

        if (!empty($searched_companies) && is_array($searched_companies))
        {
            $object->fk_soc = $searched_companies[0]->id;
        }

        if (is_array($contacts) && count($contacts) > 0 && $cid >= 0)
        {
            $object->fk_soc = $contacts[$cid]->socid;
            $usertoassign = $contacts[$cid]->id;
        }

        if (GETPOST('socid') > 0)
        {
            $object->fk_soc = GETPOST('socid');
        }

        $ret = $extrafields->setOptionalsFromPost(null, $object);

        // Generate new ref
        $object->ref = $object->getDefaultRef();

        $object->context['disableticketemail'] = 1; // Disable emails sent by ticket trigger when creation is done from this page, emails are already sent later

        if ($nb_post_max > 0 && $nb_post_ip >= $nb_post_max)
        {
            $error++;
            $errors = array($langs->trans("AlreadyTooMuchPostOnThisIPAdress"));
            array_push($object->errors, $langs->trans("AlreadyTooMuchPostOnThisIPAdress"));
            $action = 'create_ticket';
        }

        if (!$error)
        {
            // Creation of the ticket
            $id = $object->create($user);
            if ($id <= 0)
            {
                $error++;
                $errors = ($object->error ? array($object->error) : $object->errors);
                array_push($object->errors, $object->error ? array($object->error) : $object->errors);
                $action = 'create_ticket';
            }
        }

        if (!(!$error && $id > 0))
        {
            setEventMessages($object->error, $object->errors, 'errors');
            return;
        }

        if ($usertoassign > 0)
        {
            $object->add_contact($usertoassign, "SUPPORTCLI", 'external', 0);
        }

        if (!$error)
        {
            $object->db->commit();
            $action = "infos_success";
        }
        else
        {
            $object->db->rollback();
            setEventMessages($object->error, $object->errors, 'errors');
            $action = 'create_ticket';
        }

        if ($error)
        {
            return;
        }

        $res = $object->fetch($id);
        if ($res)
        {
            // Create form object
            include_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
            include_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
            $formmail = new FormMail($db);

            // Init to avoid errors
            $filepath = array();
            $filename = array();
            $mimetype = array();

            $attachedfiles = $formmail->get_attached_files();
            $filepath = $attachedfiles['paths'];
            $filename = $attachedfiles['names'];
            $mimetype = $attachedfiles['mimes'];

            // Send email to customer

            $subject = '[' . $conf->global->MAIN_INFO_SOCIETE_NOM . '] ' . $langs->transnoentities('TicketNewEmailSubject', $object->ref, $object->ref);
            $message  = ($conf->global->TICKET_MESSAGE_MAIL_NEW ? $conf->global->TICKET_MESSAGE_MAIL_NEW : $langs->transnoentities('TicketNewEmailBody')) . '<br><br>';
            $message .= $langs->transnoentities('TicketNewEmailBodyInfosTicket') . '<br>';

            $url_public_ticket = ($conf->global->TICKET_URL_PUBLIC_INTERFACE ? $conf->global->TICKET_URL_PUBLIC_INTERFACE . '/view.php' : dol_buildpath('/public/ticket/view.php', 2)) . '?track_id=' . $object->ref;
            $infos_new_ticket = $langs->transnoentities('TicketNewEmailBodyInfosTrackId', '<a href="' . $url_public_ticket . '" rel="nofollow noopener">' . $object->ref . '</a>') . '<br>';
            $infos_new_ticket .= $langs->transnoentities('TicketNewEmailBodyInfosTrackUrl') . '<br><br>';

            $message .= $infos_new_ticket;
            $message .= getDolGlobalString('TICKET_MESSAGE_MAIL_SIGNATURE', $langs->transnoentities('TicketMessageMailSignatureText', $mysoc->name));

            $sendto = GETPOST('email', 'alpha');

            $from = $conf->global->MAIN_INFO_SOCIETE_NOM . ' <' . getDolGlobalString('TICKET_NOTIFICATION_EMAIL_FROM') . '>';
            $replyto = $from;
            $sendtocc = '';
            $deliveryreceipt = 0;

            if (!empty($conf->global->TICKET_DISABLE_MAIL_AUTOCOPY_TO))
            {
                $old_MAIN_MAIL_AUTOCOPY_TO = $conf->global->MAIN_MAIL_AUTOCOPY_TO;
                $conf->global->MAIN_MAIL_AUTOCOPY_TO = '';
            }
            include_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
            $mailfile = new CMailFile($subject, $sendto, $from, $message, $filepath, $mimetype, $filename, $sendtocc, '', $deliveryreceipt, -1, '', '', 'tic' . $object->id, '', 'ticket');
            if ($mailfile->error || !empty($mailfile->errors))
            {
                setEventMessages($mailfile->error, $mailfile->errors, 'errors');
            }
            else
            {
                $result = $mailfile->sendfile();
            }
            if (!empty($conf->global->TICKET_DISABLE_MAIL_AUTOCOPY_TO))
            {
                $conf->global->MAIN_MAIL_AUTOCOPY_TO = $old_MAIN_MAIL_AUTOCOPY_TO;
            }

            // Send email to TICKET_NOTIFICATION_EMAIL_TO
            $sendto = $conf->global->TICKET_NOTIFICATION_EMAIL_TO;
            if ($sendto)
            {
                $subject = '[' . $conf->global->MAIN_INFO_SOCIETE_NOM . '] ' . $langs->transnoentities('TicketNewEmailSubjectAdmin', $object->ref, $object->ref);
                $message_admin = $langs->transnoentities('TicketNewEmailBodyAdmin', $object->ref) . '<br><br>';
                $message_admin .= '<ul><li>' . $langs->trans('Title') . ' : ' . $object->subject . '</li>';
                $message_admin .= '<li>' . $langs->trans('Type') . ' : ' . $object->type_label . '</li>';
                $message_admin .= '<li>' . $langs->trans('Category') . ' : ' . $object->category_label . '</li>';
                $message_admin .= '<li>' . $langs->trans('Severity') . ' : ' . $object->severity_label . '</li>';
                $message_admin .= '<li>' . $langs->trans('From') . ' : ' . $object->origin_email . '</li>';
                // Extrafields
                $extrafields->fetch_name_optionals_label($object->table_element);
                if (is_array($object->array_options) && count($object->array_options) > 0)
                {
                    foreach ($object->array_options as $key => $value)
                    {
                        $key = substr($key, 8); // remove "options_"
                        $message_admin .= '<li>' . $langs->trans($extrafields->attributes[$object->table_element]['label'][$key]) . ' : ' . $extrafields->showOutputField($key, $value, '', $object->table_element) . '</li>';
                    }
                }
                $message_admin .= '</ul>';

                $message_admin .= '<p>' . $langs->trans('Message') . ' : <br>' . $object->message . '</p>';
                $message_admin .= '<p><a href="' . dol_buildpath('/ticket/card.php', 2) . '?track_id=' . $object->track_id . '" rel="nofollow noopener">' . $langs->trans('SeeThisTicketIntomanagementInterface') . '</a></p>';

                $from = $conf->global->MAIN_INFO_SOCIETE_NOM . ' <' . $conf->global->TICKET_NOTIFICATION_EMAIL_FROM . '>';
                $replyto = $from;

                if (!empty($conf->global->TICKET_DISABLE_MAIL_AUTOCOPY_TO))
                {
                    $old_MAIN_MAIL_AUTOCOPY_TO = $conf->global->MAIN_MAIL_AUTOCOPY_TO;
                    $conf->global->MAIN_MAIL_AUTOCOPY_TO = '';
                }
                include_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
                $mailfile = new CMailFile($subject, $sendto, $from, $message_admin, $filepath, $mimetype, $filename, $sendtocc, '', $deliveryreceipt, -1, '', '', 'tic' . $object->id, '', 'ticket');
                if ($mailfile->error || !empty($mailfile->errors))
                {
                    setEventMessages($mailfile->error, $mailfile->errors, 'errors');
                }
                else
                {
                    $result = $mailfile->sendfile();
                }
                if (!empty($conf->global->TICKET_DISABLE_MAIL_AUTOCOPY_TO))
                {
                    $conf->global->MAIN_MAIL_AUTOCOPY_TO = $old_MAIN_MAIL_AUTOCOPY_TO;
                }
            }
        }

        // Copy files into ticket directory
        $object->copyFilesForTicket('');

        //setEventMessages($langs->trans('YourTicketSuccessfullySaved'), null, 'mesgs');

        // Make a redirect to avoid to have ticket submitted twice if we make back
        $messagetoshow = $langs->trans('MesgInfosPublicTicketCreatedWithTrackId', '{s1}', '{s2}');
        $messagetoshow = str_replace(array('{s1}', '{s2}'), array('<strong>' . $object->ref . '</strong>', '<strong>' . $object->ref . '</strong>'), $messagetoshow);
        setEventMessages($messagetoshow, null, 'warnings');
        setEventMessages($langs->trans('PleaseRememberThisId'), null, 'warnings');

        header("Location: index.php" . (!empty($entity) && isModEnabled('multicompany') ? '?entity=' . $entity : ''));
        exit;
    }

    public static function add_select_company()
    {
        global $langs;
        
        $w = '';

        $w .= '<div id="select_company_container" style="display: none">';

        $w .= '<table>';
        $w .= '<tr id="select_company_row" style="display: none">';

        $w .= '<td>';
        $w .= '<span class="fieldrequired">';
        $w .= $langs->trans('ThirdParty');
        $w .= '</span>';
        $w .= '</td>';

        $w .= '<td>';
        $w .= '<select id="select_company" name="socid">';
        $w .= '</select>';
        $w .= '</td>';

        $w .= '</tr>';
        $w .= '</table>';

        $w .= '</div>';

        $w .= '<input type="hidden" id="URL_ROOT" value="' . DOL_URL_ROOT . '">';

        $w .= '<script src="' . DOL_URL_ROOT . '/custom/ticketutils/js/select_company.js?time=' . time() . '    "></script>';

        return $w;
    }
}
