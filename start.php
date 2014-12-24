<?php
/**
 * Start file for this plugin
 */

require_once(dirname(__FILE__) . "/lib/hooks.php");
require_once(dirname(__FILE__) . "/lib/typistcom.php");

elgg_register_event_handler('init', 'system', 'odt_collabeditor_init');

function odt_collabeditor_init() {
    // extend file page handler
    elgg_register_plugin_hook_handler("route", "file", "odt_collabeditor_route_file_handler");

    $typist_host = elgg_get_plugin_setting('typist_host', 'odt_collabeditor');
    elgg_register_js('socket.io-client', $typist_host . '/socket.io/socket.io.js');
    elgg_register_simplecache_view('js/odt_collabeditor_dojosetup');
    elgg_register_js('wodocollabtexteditor', '/mod/odt_collabeditor/vendors/wodo/wodocollabtexteditor.js');

    elgg_register_js('elgg.odt_collabeditor', elgg_get_simplecache_url('js', 'odt_collabeditor'));
    elgg_register_simplecache_view('js/odt_collabeditor');

    elgg_register_action("odt_collabeditor/create_session", elgg_get_plugins_path() . "odt_collabeditor/actions/odt_collabeditor/create_session.php");
    elgg_register_action("odt_collabeditor/delete_session", elgg_get_plugins_path() . "odt_collabeditor/actions/odt_collabeditor/delete_session.php");
}
