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
}
