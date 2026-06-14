<?php
/**
 * Copyright 2005-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author Ben Klang <ben@alkaloid.net>
 */

require_once __DIR__ . '/lib/Application.php';
$beatnik = Horde_Registry::appInit('beatnik');

$session = $GLOBALS['session'];
$curdomain = $session->get('beatnik', 'curdomain');

try {
    $zonedata = $beatnik->driver->getRecords($curdomain['zonename']);
} catch (Exception $e) {
    $notification->push($e, 'horde.error');
    Horde::url('listzones.php')->redirect();
}

$page_output->addScriptFile('beatnik.js');
$page_output->addScriptFile('stripe.js', 'horde');
Beatnik::notifyCommits();
$page_output->header(array(
    'title' => $curdomain['zonename']
));
require BEATNIK_TEMPLATES . '/menu.inc';

// Get a list of all the fields for all record typess we'll be processing
$fields = array();
foreach ($zonedata as $type => $data) {
    $fields = array_merge($fields, Beatnik::getRecFields($type));
}

// Remove fields that should not be shown
$expertmode = $session->get('beatnik', 'expertmode');
foreach ($fields as $field_id => $field) {
    if ($field['type'] == 'hidden' ||
        ($field['infoset'] != 'basic' && !$expertmode)) {
        unset($field[$field_id]);
    }
}

$delete = Horde::url('delete.php')->add('curdomain', $curdomain['zonename']);
$edit = Horde::url('editrec.php')->add('curdomain', $curdomain['zonename']);
$autogen = Horde::url('autogenerate.php')->add('curdomain', $curdomain['zonename']);
$rectypes = Beatnik::getRecTypes();

require BEATNIK_TEMPLATES . '/view/header.inc';
foreach ($rectypes as $type => $typedescr) {
    if (!isset($zonedata[$type])) {
        continue;
    }
    require BEATNIK_TEMPLATES . '/view/record.inc';
}
require BEATNIK_TEMPLATES . '/view/footer.inc';

$page_output->footer();
