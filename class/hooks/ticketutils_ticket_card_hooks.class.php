<?php

require_once DOL_DOCUMENT_ROOT . '/custom/socilib/soci_lib.class.php';

class TicketUtilsTicketCardHooks
{
    /**
     * @param Ticket $object
     */
    public static function confirm_change_status($object, $status)
    {
        global $langs;

        $langs->load('ticketutils@ticketutils');

        SociLib::load_components([SOCILIB_MODAL]);

        $form_action = DOL_URL_ROOT . '/custom/ticketutils/inc/ticket_card.inc.php?id=' . $object->id;

        $props = new SociModalProps();
        $props->default_display = 'flex';

        $c = '';

        $c .= '<div>';

        $c .= '<span>';
        $c .= $langs->trans('ChangeTicketStatusTo', $langs->transnoentitiesnoconv('TicketStatus' . $object->statuts[$status]));
        $c .= '</span>';

        $c .= '<br>';
        $c .= '<br>';

        $c .= '<div style="display: flex; flex-direction: column; gap: 2px">';
        $c .= '<b>';
        $c .= $langs->trans('Observations') . ':';
        $c .= '</b>';
        $c .= '<textarea name="status_observations" required>';
        $c .= '</textarea>';
        $c .= '</div>';

        $c .= '<input type="hidden" name="id" value="' . $object->id . '">';
        $c .= '<input type="hidden" name="new_status" value="' . $status . '">';

        $c .= '</div>';

        echo SociModal::print(
            'change_status',
            $form_action,
            'change_status',
            $langs->trans('ChangeTicketStatus'),
            $c,
            $props
        );

        return 1;
    }
}
