<?php

class TicketUtilsTicketListHooks
{
    public static function hide_public_track_id()
    {
        global $arrayfields;

        unset($arrayfields['t.track_id']);
    }

    public static function add_arrayfields()
    {
        global $arrayfields, $langs;

        $arrayfields['tte.rating'] = [
            'label' => $langs->trans('Rating'),
            'checked' => 0,
            'position' => 2000,
            'enabled' => 1
        ];

        $arrayfields['tte.rating_comment'] = [
            'label' => $langs->trans('Comments'),
            'checked' => 0,
            'position' => 2000,
            'enabled' => 1
        ];
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

        return $sql;
    }

    public static function add_list_where()
    {
    }

    public static function add_pre_list_title()
    {
    }

    public static function add_list_option()
    {
        global $arrayfields;

        $w = '';

        if ($arrayfields['tte.rating']['checked'])
        {
            $w .= '<td class="liste_titre center">';
            $w .= '<input>';
            $w .= '</td>';
        }

        if ($arrayfields['tte.rating_comment']['checked'])
        {
            $w .= '<td class="liste_titre center">';
            $w .= '<input>';
            $w .= '</td>';
        }

        return $w;
    }

    public static function add_list_title()
    {
        global $langs, $arrayfields;

        $w = '';

        if ($arrayfields['tte.rating']['checked'])
        {
            $w .= '<th>';
            $w .= $langs->trans('Rating');
            $w .= '</th>';
        }

        if ($arrayfields['tte.rating_comment']['checked'])
        {
            $w .= '<th>';
            $w .= $langs->trans('Comments');
            $w .= '</th>';
        }

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
            $w .= $rating;
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
