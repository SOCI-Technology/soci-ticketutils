<?php

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/lib/ticketutils.lib.php';

class TicketExtrafields
{
    /**	@var DoliDB Database object */
    public $db;

    /**	@var int Object ID */
    public $id;

    /** @var int ID of a related element */
    public $fk_ticket;

    /** @var Ticket Related element object */
    public $ticket;

    /** @var Societe|null Thirdparty */
    public $thirdparty;

    /** @var float rating */
    public $rating;

    /** @var string Rating date */
    public $rating_date;

    /** @var string Comment */
    public $rating_comment;

    /** @var int ID of user creator */
    public $fk_user_creator;

    /** @var int ID of user editor */
    public $fk_user_edit;

    /** @var string Date of creation */
    public $date_creation;

    /** @var string Date of last modification */
    public $tms;

    /** @var string Element name */
    public $element = 'ticket_extrafields';

    /** @var string Errors */
    public $error;

    /** @var array Errors */
    public $errors = array();

    /** @var string Origin */
    public $origin = '';

    /** @var int Origin ID */
    public $origin_id;

    /** @var array Context */
    public $context = [];

    /** @var array Linked objects */
    public $linkedObjects = [];

    /** @var array Linked objects ids */
    public $linkedObjectsIds = [];

    /** @var array Linked objects full loaded */
    public $linkedObjectsFullLoaded = [];

    /** @var array Fields */
    const FIELDS = array(
        'rowid',
        'fk_ticket',
        'rating',
        'rating_date',
        'rating_comment',
        'fk_user_creator',
        'fk_user_edit',
        'date_creation',
        'tms'
    );

    /** @var string Table name */
    const TABLE_NAME = 'ticketutils_ticket_extrafields';

    const TRIGGER_PREFIX = 'TICKET_EXTRAFIELDS';

    const ICON = 'fa-ticket';

    const DOCUMENT_DIR = DOL_DATA_ROOT . '/module/example';

    public function __construct($DB)
    {
        $this->db = $DB;
        return 1;
    }

    /**
     *	Fetch the object
     *
     *  @param		int		$id		Object ID
     * 
     *	@return		int		<0 if KO, >0 if OK
     */
    public function fetch($id = 0, $fk_ticket = 0)
    {
        $sql = "SELECT ";
        $sql .= join(', ', self::FIELDS);
        $sql .= " FROM " . MAIN_DB_PREFIX . self::TABLE_NAME . "";
        $sql .= " WHERE rowid = '" . $id . "' OR fk_ticket = '" . $fk_ticket . "'";

        $resql = $this->db->query($sql);

        if (!$resql)
        {
            return -1;
        }

        if ($this->db->num_rows($resql))
        {
            $obj = $this->db->fetch_object($resql);

            foreach (self::FIELDS as $field)
            {
                if ($field == 'rowid')
                {
                    $this->id = $obj->rowid;
                    continue;
                }

                $this->$field = $obj->$field;
            }
        }

        $this->db->free($resql);

        return 1;
    }

    /**
     *	Create object into database
     *
     *  @param		User    $user   User creator
     * 
     *	@return		int		<0 if KO, >0 if OK
     */
    public function create($user)
    {
        $error = 0;

        dol_syslog(get_class($this) . "::create");

        $now = date("Y-m-d H:i:s", dol_now());

        $this->db->begin();

        $create_fields = [];

        foreach (self::FIELDS as $field)
        {
            if ($field == 'rowid')
            {
                continue;
            }

            $create_fields[] = $field;
        }

        $this->fk_user_creator = $user->id;
        $this->fk_user_edit = $user->id;
        $this->tms = $now;
        $this->date_creation = $now;

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . self::TABLE_NAME . "(";
        $sql .= join(', ', $create_fields);
        $sql .= ") ";
        $sql .= " VALUES (";

        foreach ($create_fields as $index => $field)
        {
            if ($index > 0)
            {
                $sql .= ", ";
            }

            $value = $this->$field;
            $sql .= isset($value) ? "'" . $this->db->escape($value) . "'" : "NULL";
        }

        $sql .= ")";

        dol_syslog(get_class($this) . "::create", LOG_DEBUG);
        $result = $this->db->query($sql);

        if (!$result)
        {
            $this->error = $this->db->error();
            $this->db->rollback();
            return -1;
        }

        $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . self::TABLE_NAME);

        $this->db->commit();
        return $this->id;
    }

    /**
     *	Update object into database
     *
     *  @param		User    $user   User updater
     *
     *	@return		int		<0 if KO, >0 if OK
     */
    public function update($user = null)
    {
        $now = date('Y-m-d H:i:s', dol_now());

        $error = 0;

        $update_fields = [];

        foreach (self::FIELDS as $field)
        {
            if ($field == 'rowid')
            {
                continue;
            }

            $update_fields[] = $field;
        }

        $this->tms = $now;
        $this->fk_user_edit = $user ? $user->id : $this->fk_user_edit;

        $sql = "UPDATE " . MAIN_DB_PREFIX . self::TABLE_NAME . " SET ";

        foreach ($update_fields as $index => $field)
        {
            if ($index > 0)
            {
                $sql .= ", ";
            }

            $sql .= $field . " = ";

            $value = $this->$field;
            $sql .= isset($value) ? "'" . $value . "'" : "NULL";
        }

        $sql .= " WHERE rowid = " . $this->id;

        $this->db->begin();

        dol_syslog(get_class($this) . "::update sql=" . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);

        if (!$resql)
        {
            $this->db->rollback();
            $this->error = "Error " . $this->db->lasterror();
            return -1;
        }

        $this->db->commit();
        return 1;
    }

    /**
     *	Delete object from database
     * 
     *	@return		int		<0 if KO, >0 if OK
     */
    public function delete()
    {
        $error = 0;

        $this->db->begin();

        $sql = "DELETE FROM " . MAIN_DB_PREFIX . self::TABLE_NAME;
        $sql .= " WHERE rowid = " . $this->id;

        dol_syslog(self::class . "::delete", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if (!$resql)
        {
            $this->db->rollback();
            return -1;
        }

        $this->db->commit();
        return 1;
    }

    /**
     * Get a list of objects
     * 
     * @param   DoliDB      $db 	        Database instance
     * @param   string[]    $conditions 	List of extra SQL conditions
     * @param	string      $order      	Column to sort the result
     * 
     * @return	TicketExtrafields[]		List of objects
     * 
     */
    public static function get_all($db, $conditions = array(), $order = '')
    {
        $result = array();

        $sql = "SELECT ";
        $sql .= join(', ', self::FIELDS);
        $sql .= " FROM " . MAIN_DB_PREFIX . self::TABLE_NAME . "";

        if (!empty($conditions))
        {
            $sql .= " WHERE";

            for ($i = 0; $i < count($conditions); $i++)
            {

                $sql .= " (";

                $sql .= $conditions[$i];

                $sql .= ")";

                if ($i + 1 < count($conditions))
                {
                    $sql .= " AND";
                }
            }
        }

        if ($order)
        {
            $sql .= " ORDER BY" . $order;
        }
        $resql = $db->query($sql);

        if (!$resql)
        {
            return [];
        }

        $num = $db->num_rows($resql);
        $i = 0;
        while ($i < $num)
        {
            $obj = $db->fetch_object($resql);

            $line = new self($db);

            foreach (self::FIELDS as $field)
            {
                if ($field == 'rowid')
                {
                    $line->id = $obj->rowid;
                    continue;
                }

                $line->$field = $obj->$field;
            }

            $result[$i] = $line;

            $i++;
        }
        $db->free($resql);

        return $result;
    }

    public function fetch_ticket($force = false)
    {
        if (!$force && $this->ticket->id > 0)
        {
            return $this->ticket;
        }

        $ticket = new Ticket($this->db);
        $ticket->fetch($this->fk_ticket);

        $this->ticket = $ticket;

        return $this->ticket;
    }

    public function fetch_thirdparty($force = false)
    {
        $this->fetch_ticket($force);

        $this->ticket->fetch_thirdparty();

        $this->thirdparty = $this->ticket->thirdparty;
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
    /**
     * Call trigger based on this instance.
     * Some context information may also be provided into array property this->context.
     * NB:  Error from trigger are stacked in interface->errors
     * NB2: If return code of triggers are < 0, action calling trigger should cancel all transaction.
     *
     * @param   string    $triggerName   trigger's name to execute
     * @param   User      $user           Object user
     * @return  int                       Result of run_triggers
     */
    public function call_trigger($triggerName, $user)
    {
        // phpcs:enable
        global $langs, $conf;
        if (!empty(self::TRIGGER_PREFIX) && strpos($triggerName, self::TRIGGER_PREFIX . '_') !== 0)
        {
            dol_print_error('', 'The trigger "' . $triggerName . '" does not start with "' . self::TRIGGER_PREFIX . '_" as required.');
            exit;
        }
        if (!is_object($langs))
        {    // If lang was not defined, we set it. It is required by run_triggers.
            include_once DOL_DOCUMENT_ROOT . '/core/class/translate.class.php';
            $langs = new Translate('', $conf);
        }

        include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
        $interface = new Interfaces($this->db);
        $result = $interface->run_triggers($triggerName, $this, $user, $langs, $conf);

        if ($result < 0)
        {
            if (!empty($this->errors))
            {
                $this->errors = array_unique(array_merge($this->errors, $interface->errors)); // We use array_unique because when a trigger call another trigger on same object, this->errors is added twice.
            }
            else
            {
                $this->errors = $interface->errors;
            }
        }
        return $result;
    }


    /**
     * @param   Ticket  $ticket
     */
    public function rate_ticket($ticket, $rating, $rating_comment = null)
    {
        global $db, $langs, $conf, $mysoc;

        $langs->load('ticketutils@ticketutils');

        $this->fetch(0, $ticket->id);

        $this->fk_ticket = $ticket->id;
        $this->rating = $rating;
        $this->rating_comment = $rating_comment;
        $this->rating_date = date('Y-m-d H:i:s', dol_now());

        $this->db->begin();

        $prov_user = new User($this->db);
        $prov_user->id = $ticket->fk_user_create ? $ticket->fk_user_create : 0;

        if ($this->id > 0)
        {
            $res = $this->update($prov_user);
        }
        else
        {
            $res = $this->create($prov_user);
        }

        if (!($res > 0))
        {
            dol_syslog('TICKETUTILS: ERROR RATING TICKET', LOG_ERR);
            $this->db->rollback();
            return -1;
        }

        $label = $langs->trans('TicketRefAccepted', $ticket->ref);

        $res = TicketUtilsLib::change_ticket_status($ticket, Ticket::STATUS_CLOSED, $prov_user, $rating_comment, $label);

        try
        {
            $assigned_user = new User($db);
            $assigned_user->fetch($ticket->fk_user_assign);

            $to = $assigned_user->email;
            $from = $conf->global->TICKET_NOTIFICATION_EMAIL_FROM;

            if (!$to || !$from)
            {
                throw new Exception('No email defined');
            }

            $subject = '[' . $mysoc->getFullName($langs) . '] - ' .  $langs->transnoentitiesnoconv('TicketAcceptedSubject', $ticket->ref);

            $message = '';

            $message .= $langs->transnoentitiesnoconv('TicketAcceptedMessage');

            $message .= '<br>';
            $message .= '<br>';

            $message .= '<b>' . $langs->transnoentitiesnoconv('Rating') . ':</b> ' . $rating . '/5';

            if ($rating_comment)
            {
                $message .= '<br>';
                $message .= '<b>' . $langs->transnoentitiesnoconv('Comments') . ':</b> ' . $rating_comment;
            }

            $message .= '<br>';
            $message .= '<br>';

            $message .= $langs->transnoentitiesnoconv('LinkToTicket') . ': ' . DOL_MAIN_URL_ROOT . '/ticket/card.php?id=' . $ticket->id;

            $mail = new CMailFile(
                $subject,
                $to,
                $from,
                $message,
                [],
                [],
                [],
                '',
                '',
                0,
                1
            );

            $mail->sendfile();
        }
        catch (Exception $e)
        {
            dol_syslog('TICKETUTILS: ERROR SENDING EMAIL: ' . $e->getMessage());
        }

        if (!($res > 0))
        {
            dol_syslog('TICKETUTILS: ERROR CHANGING TICKET STATUS TICKET', LOG_ERR);
            $this->db->rollback();
            return -1;
        }

        $this->db->commit();

        return 1;
    }
}
