<?php
/**
* ODT file collab session deletion action
*
* @package odt_collabeditor
*/

// Get variables
$file_guid = (int) get_input('file_guid');

// load original file object
// TODO: what if somebody deleted the file while the session was running?
$file = new ElggFile($file_guid);
if (!$file) {
    register_error(elgg_echo('file:cannotload'));
    forward(REFERER);
}

// user must be able to edit file
if (!$file->canEdit()) {
    register_error(elgg_echo('file:noaccess'));
    forward(REFERER);
}

$reply = odt_collabeditor_call_typist("delete_session", array("sessionId" => $file_guid));

if (!$reply->success) {
    register_error(elgg_echo('Deleting the collab session failed.'));
    error_log('Deleting the collab session failed: '.$reply->errorString);
    forward(REFERER);
}

system_message(elgg_echo("Collab session has been deleted."));

// TODO: where should this forward to? file manager list? which url?
// forward();
