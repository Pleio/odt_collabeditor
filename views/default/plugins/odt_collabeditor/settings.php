<?php
/**
 * Typist plugin settings
 */

// set default value

if (!isset($vars['entity']->typist_host)) {
    $vars['entity']->typist_host = "https://127.0.0.1:3000";
}
if (!isset($vars['entity']->typist_control_host)) {
    $vars['entity']->typist_control_host = "http://127.0.0.1:3002";
}

?>
<div>
    <br /><label><?php echo elgg_echo('Typist host address'); ?></label><br />
    <?php echo elgg_view('input/text',array('name' => 'params[typist_host]',
                                            'value' => $vars['entity']->typist_host,
                                            'class' => 'text_input',)); ?>
</div>
<div>
    <br /><label><?php echo elgg_echo('Typist control host address'); ?></label><br />
    <?php echo elgg_view('input/text',array('name' => 'params[typist_control_host]',
                                            'value' => $vars['entity']->typist_control_host,
                                            'class' => 'text_input',)); ?>
</div>
