<?php

class TicketUtilsTicketListHooks {
    public static function hide_public_track_id()
    {
        global $arrayfields;

        unset($arrayfields['t.track_id']);
    }
}