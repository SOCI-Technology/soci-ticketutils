<?php

require_once DOL_DOCUMENT_ROOT . '/custom/socilib/soci_lib_lists.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/ticketutils/class/ticket_extrafields.class.php';

class TicketUtilsTicketListHooks
{
    public static function columns()
    {
        global $langs;

        $columns = [
            "tte_rating" => [
                "label" => $langs->trans('Rating'),
                "input_type" => "number",
                "field" => "tte.rating",
                "checked" => 1,
                "enabled" => 1,
                "arrayfield" => "tte.rating",
            ],
            "tte_rating_comment" => [
                "label" => $langs->trans('Comments'),
                "input_type" => "text",
                "field" => "tte.rating_comment",
                "checked" => 1,
                "enabled" => 1,
                "arrayfield" => "tte.rating_comment",
            ]
        ];

        return $columns;
    }

    public static function hide_public_track_id()
    {
        global $arrayfields;

        unset($arrayfields['t.track_id']);
    }

    public static function add_arrayfields()
    {
        global $arrayfields, $langs;

        $columns = self::columns();

        SociLibLists::add_arrayfields($arrayfields, $columns);
    }

    public static function add_list_select()
    {
        $sql = "";

        foreach (TicketExtrafields::FIELDS as $field)
        {
            $sql .= ", tte." . $field . ' as tte_' . $field;
        }

        return $sql;
    }

    public static function add_list_from()
    {
        $sql = "";

        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . TicketExtrafields::TABLE_NAME . " as tte";
        $sql .= " ON tte.fk_ticket = t.rowid";

        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "element_contact as ec";
        $sql .= " ON ec.element_id = t.rowid";

        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "c_type_contact as ctc";
        $sql .= " ON ctc.rowid = ec.fk_c_type_contact";
        $sql .= " AND ctc.element = 'ticket'";
        $sql .= " AND ctc.source = 'internal'";

        return $sql;
    }

    public static function add_list_where()
    {
        global $user;

        $columns = self::columns();

        $mode = GETPOST('mode');

        $remove_filter = GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter.x', 'alpha');

        $sql = "";

        $sql .= SociLibLists::list_where($columns);

        $search_user_contact = $remove_filter ? [] : GETPOST('search_user_contact', 'array');

        if ($mode == 'mine')
        {
            if (!in_array($user->id, $search_user_contact))
            {
                $search_user_contact[] = $user->id;
            }
        }

        if (!empty($search_user_contact))
        {
            $sql .= ($mode == 'mine' ? " OR " : " AND ") . "ec.fk_socpeople IN (" . implode(',', $search_user_contact) . ")";
        }

        $sql .= " GROUP BY t.rowid, tte.rowid";

        return $sql;
    }

    public static function add_pre_list_title()
    {
        global $db, $langs, $user;
        /** @var DoliDB $db */

        $mode = GETPOST('mode');

        $remove_filter = GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter.x', 'alpha');

        $search_user_contact = $remove_filter ? [] : GETPOST('search_user_contact', 'array');

        $user_sql = "";

        $user_sql .= " SELECT rowid as id, firstname, lastname FROM " . MAIN_DB_PREFIX . "user as u";
        $user_sql .= " WHERE u.statut = " . User::STATUS_ENABLED;

        $resql = $db->query($user_sql);

        $user_options = [];

        if ($resql)
        {
            for ($i = 0; $i < $db->num_rows($resql); $i++)
            {
                $obj = $db->fetch_object($resql);

                $user_options[$obj->id] = $obj->firstname . ' ' . $obj->lastname;
            }
        }

        if ($mode == 'mine')
        {
            if (!in_array($user->id, $search_user_contact))
            {
                $search_user_contact[] = $user->id;
            }
        }

        $w = '';

        $w .= '<div class="divsearchfield">';

        $w .= img_picto('', 'user', 'class="pictofixedwidth"');
        $w .= Form::multiselectarray('search_user_contact', $user_options, $search_user_contact, 0, 0, '', 0, 0, '', '', $langs->trans('TicketsWithThisContacts'));

        $w .= '</div>';

        return $w;
    }

    public static function add_list_option()
    {
        global $arrayfields;

        $columns = self::columns();

        $w = '';

        $w .= SociLibLists::list_option($arrayfields, $columns);

        return $w;
    }

    public static function add_list_title()
    {
        global $langs, $arrayfields, $param, $sortfield, $sortorder;

        $columns = self::columns();

        $w = '';

        $w .= SociLibLists::list_title($arrayfields, $columns);

        return $w;
    }

    /**
     * @param   Ticket  $ticket
     */
    public static function add_list_value($ticket, $obj)
    {
        global $db, $arrayfields;

        $ticket_extrafields = new TicketExtrafields($db);

        foreach (TicketExtrafields::FIELDS as $field)
        {
            $field_key = 'tte_' . $field;

            if (isset($obj->$field_key))
            {
                if ($field == 'rowid')
                {
                    $ticket_extrafields->id = $obj->$field_key;
                    continue;
                }

                $ticket_extrafields->$field = $obj->$field_key;
            }
        }

        $rating = $ticket->status == Ticket::STATUS_CLOSED ? $ticket_extrafields->rating : null;
        $comments = $ticket->status == Ticket::STATUS_CLOSED ? $ticket_extrafields->rating_comment : null;

        $w = '';

        if ($arrayfields['tte.rating']['checked'])
        {
            $w .= '<td>';
            $w .= round($rating);
            $w .= '</td>';
        }

        if ($arrayfields['tte.rating_comment']['checked'])
        {
            $w .= '<td>';
            $w .= $comments;
            $w .= '</td>';
        }

        return $w;
    }
}
