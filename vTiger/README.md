# vTiger

This integration requires a Wildix PBX: https://www.wildix.com/

## Features

 * call pop-ups for incoming / outgoing calls
 * click to call
 * call history creation
 * actions from call pop-ups (Add Lead, Show Contact, Open Ticket)
 
 More info here: https://www.wildix.com/wp-content/uploads/2017/02/VTIGER-Integration.pdf

## Installation

To start copy the directory WebCRM to your vTiger installation folder.

Insert in Wildix Collaboration the uri to access your vTiger deployment into: Web CRM, (for example: https://mycompany.com/vtiger/).

Make sure that the server provides a valid https certificate to the domain used.

Proceed then applying the following changes to vTiger files in the installation directory.


### File changes

1. modify

```
libraries/csrf-magic/csrf-magic.php
```


comment javascript code
```
if (top != self) {top.location.href = self.location.href;}
```

2. modify
```
includes/http/Request.php
```

comment
```
throw new Exception('Illegal request');
```

### add to template include

3. modify
```
layouts/vlayout/modules/Vtiger/Header.tpl
```
after line
```
{* ADD <script> INCLUDES in JSResources.tpl - for better performance *}
```
add

```
<link rel='stylesheet' type='text/css' href='/WebCRM/vTiger/vTiger.css'>
<script src='/WebCRM/Wildix.Interaction.js'></script>
<script src='/WebCRM/vTiger/vTiger.js'></script>
```
