# vTiger


Patch

in file
libraries/csrf-magic/csrf-magic.php

comment javascript code
if (top != self) {top.location.href = self.location.href;}

in file
includes/http/Request.php

comment
throw new Exception('Illegal request');

add to template include

file
layouts/vlayout/modules/Vtiger/Header.tpl
after line
ADD <script> INCLUDES in JSResources.tpl - for better performance
add
<link rel="stylesheet" type="text/css" href="/WebCRM/vTiger/vTiger.css">
<script src="/WebCRM/Wildix.Interaction.js"></script>
<script src="/WebCRM/vTiger/vTiger.js"></script>
