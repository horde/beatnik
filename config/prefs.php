<?php
/**
 * See horde/config/prefs.php for documentation on the structure of this file.
 *
 * IMPORTANT: Local overrides should be placed in prefs.local.php, or
 * prefs-servername.php if the 'vhosts' setting has been enabled in Horde's
 * configuration.
 */

$prefGroups['display'] = array(
    'column' => _("Preferences"),
    'label' => _("Display Preferences"),
    'desc' => _("Set default display parameters."),
    'members' => array('domain_groups', 'domains_perpage')
);

$prefGroups['defaults'] = array(
    'column' => _("Preferences"),
    'label' => _("Record Defaults"),
    'desc' => _("Set default record parameters."),
    'members' => array('default_ttl')
);

// user domain groups
$_prefs['domain_groups'] = array(
    'value' => '',
    'locked' => false,
    'type' => 'implicit'
);

// listing
$_prefs['domains_perpage'] = array(
    'value' => 20,
    'locked' => false,
    'type' => 'number',
    'desc' => _("How many domain to display per page.")
);

$_prefs['default_ttl'] = array(
    'value' => '86400',
    'locked' => false,
    'type' => 'number',
    'desc' => _("Default Time-To-Live for new records.")
);

/* Local overrides. */
if (file_exists(dirname(__FILE__) . '/prefs.local.php')) {
    include dirname(__FILE__) . '/prefs.local.php';
}