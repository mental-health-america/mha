Webformscoreredirect 8.x-1.x
---------------

### About this Module

The Webform Score Redirect module is a webform submit handler.
It adds a redirect rule based on a calculated store after a webform is submitted.
The score calculation and redirect settings are in:
sites/all/modules/webformscoreredirect/src/Plugin/WebformHandler/MyFormHandler.php


### Installing the module

1. Copy/upload the webform module to the modules directory of your Drupal
   installation.

2. Enable the 'Webform' module and desired sub-modules in 'Extend'. 
   (/admin/modules)

3. Edit your webform, go to "Email / Handlers" and click on "+ Add Handler" button.
- You should see your plugin listed, click Add Handler.
- Click Save button.

4. Test your form - make a submission to the form, and then check the watchdog log.
- You should see the values sent to it.