<?php
/**
 * Edit an odt file collaboratively
 */

// need to be logged in
gatekeeper();

//only for group members
group_gatekeeper();

$file_guid = get_input('guid');
$file = get_entity($file_guid);
$title = $file->title;
$canSave= $file->canEdit();

// first try to simply register a user
$user = elgg_get_logged_in_user_entity();
$sessionId = $file_guid;
$userData = array(
    "guid" => $user->guid,
    "username" => $user->username,
    "fullName" => $user->name,
    "avatarUrl" => $user->getIconURL('large'),
    "color" => "blue", // TODO: hash by user guid
    "sessionId" => $sessionId
);
$reply = odt_collabeditor_call_typist("register_user", $userData);

// if no such session, then simply create one
if (!$reply->success) {
    if ($reply->error !== "ENOSESSION") {
        register_error(elgg_echo('Error on talking to Collab server.'));
        error_log('Error on talking to Collab server: '.$reply->errorString);
        forward(REFERRER);
    }

    // user must be able to edit file to create a session
    if (!$canSave) {
        register_error(elgg_echo('file:noaccess'));
        forward(REFERER);
    }

    $filepath = $file->getFilenameOnFilestore();
    $reply = odt_collabeditor_call_typist2("create_session", array(
        "sessionId" => $file_guid,
        "filename" => $genesisFilename,
        "title" => $title,
        "user" => $userData
    ), $filepath, "application/vnd.oasis.opendocument.text", $title);
    if (!$reply->success) {
        register_error(elgg_echo('Creating a collab session failed.'));
        error_log('Creating a collab session failed: '.$reply->errorString);
        forward(REFERRER);
    }
}

// TODO: here is now a potential small timewindow where somebody else might delete the session we are about to join
// no data loss happening here, so for now just hoping that noone hits it, given that chances are small
// A reload should solve things for people. Not perfect, but other things to be solved first

$genesisDocPath = $reply->data->genesisDocPath;
$token = $reply->data->token;

elgg_load_js('socket.io-client');
elgg_load_js('wodocollabtexteditor');
elgg_load_js('elgg.odt_collabeditor');
elgg_load_css('elgg.odt_editor_dojo_overwrite');

$download_url = elgg_get_site_url() . "file/download/{$file_guid}";
$content = "<div class=\"notranslate\" translate=\"no\" id=\"odt_collabeditor\" style=\"width: 100%;height: calc(100% - 28px); margin-top: 28px; padding: 0;\" data-session-id=\"$sessionId\" data-genesisdoc-path=\"$genesisDocPath\" data-token=\"$token\" data-can-save=\"$canSave\"></div>";

$body = $content;

# draw page
echo elgg_view_page($title, $body, 'odt_collabeditor');
