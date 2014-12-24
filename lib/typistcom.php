<?php
/**
 * All RPC to Typist is defined here
 *
 * @package odt_collabeditor
 */

/**
 * @param string $command      name of the command
 * @param array  $data         data of the command
 *
 * @return array with the data of the reply
 */
function odt_collabeditor_call_typist($command, $data) {

    $fullcommand = array(
        "command" => $command,
        "data" => (object)$data
    );
    $fullcommand_string = json_encode($fullcommand);

    $typist_control_host = elgg_get_plugin_setting('typist_control_host', 'odt_collabeditor');
    $ch = curl_init($typist_control_host.'/COMMAND');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fullcommand_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($fullcommand_string))
    );
    $output = curl_exec($ch);
    if ($output === false) {
        $output = array(
            "success" => false,
            "error" => "EBADREPLY",
            "errorString" => curl_error($ch)
        );
    } else {
        $output = json_decode($output); // TODO: decoding error handling
        if (!array_key_exists("success", $output) ||
            ($output->success && !array_key_exists("data", $output)) ||
            (!$output->success && !array_key_exists("error", $output))) {
            $output = array(
                "success" => false,
                "error" => "EBADREPLY",
                "errorString" => "Bad reply data."
            );
        }
    }
    // ensure dummy error string
    if (array_key_exists("error", $output) && !array_key_exists("errorString", $output)) {
        $output->errorString = "Unknown error.";
    }

    error_log('Result of typist call: '.json_encode($output));

    curl_close($ch);

    return $output;
}

/**
 * @param string $command      name of the command
 * @param array  $data         data of the command
 *
 * @return array with the data of the reply
 */
function odt_collabeditor_call_typist2($command, $data, $filepath, $filetype, $filetitle) {

    $fullcommand = array(
        "command" => $command,
        "data" => (object)$data
    );
    $fullcommand_string = json_encode($fullcommand);

    $cfile = curl_file_create($filepath, $filetype, $filetitle);

    $postdata = array(
        'message' => $fullcommand_string,
        'document' => $cfile
    );

    $typist_control_host = elgg_get_plugin_setting('typist_control_host', 'odt_collabeditor');
    $ch = curl_init($typist_control_host.'/COMMAND2');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    $output = curl_exec($ch);
    if ($output === false) {
        $output = array(
            "success" => false,
            "error" => "EBADREPLY",
            "errorString" => curl_error($ch)
        );
    } else {
        $output = json_decode($output); // TODO: decoding error handling
        if (!array_key_exists("success", $output) ||
            ($output->success && !array_key_exists("data", $output)) ||
            (!$output->success && !array_key_exists("error", $output))) {
            $output = array(
                "success" => false,
                "error" => "EBADREPLY",
                "errorString" => "Bad reply data."
            );
        }
    }
    // ensure dummy error string
    if (array_key_exists("error", $output) && !array_key_exists("errorString", $output)) {
        $output->errorString = "Unknown error.";
    }

    error_log('Result of typist call: '.json_encode($output));

    curl_close($ch);

    return $output;
}
