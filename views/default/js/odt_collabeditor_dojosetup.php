<?php
// TODO: get base paths below from elgg var
// TODO: fix language also for nl
?>
//<script>

var usedLocale = "C";
if (navigator && navigator.language && navigator.language.match(/^(ru|de)/)) {
    usedLocale = navigator.language.substr(0,2);
}

dojoConfig = {
    locale: usedLocale,
    paths: {
        "webodf/editor": "/mod/odt_collabeditor/vendors/wodo",
        "dijit": "/mod/odt_collabeditor/vendors/wodo/dijit",
        "dojox": "/mod/odt_collabeditor/vendors/wodo/dojox",
        "dojo": "/mod/odt_collabeditor/vendors/wodo/dojo",
        "resources": "/mod/odt_collabeditor/vendors/wodo/resources"
    },
    async: true
}
