<?php
$beatnikSession = $GLOBALS['session'];
$beatnikCurdomain = $beatnikSession->get('beatnik', 'curdomain');
$beatnikExpertmode = $beatnikSession->get('beatnik', 'expertmode');
if (Beatnik::needCommit($beatnikCurdomain['zonename'])) {
    $commit = Horde::url('commit.php')->add(array('domain' => 'current'));
    echo Horde::link($commit, _("Commit Changes"), 'button') . _("Commit Changes") . '</a><br /><br />';
}
?>

<div class="header">
<?php
$params = array('id' => $beatnikCurdomain['id'],
                'rectype' => 'soa');
echo $beatnikCurdomain['zonename'] . '&nbsp;'
     . Horde::link($edit->copy()->add($params))
     . Horde::img('edit.png', _("Edit")) . '</a> '
     . Horde::link($autogen->copy()->add($params))
     . Horde::img('devel.png', _("Autogenerate")) . '</a> '
     . Horde::link($delete->copy()->add($params))
     . Horde::img('delete.png', _("Delete")) . '</a></td>';
?>
</div>

<?php
if ($beatnikExpertmode) {
    echo '<table class="striped">' . "\n";
    foreach (Beatnik::getRecFields('soa') as $key => $value) {
        if (isset($beatnikCurdomain[$key])) {
            echo '<tr><td>' . $value['name'] . '</td><td>' . $beatnikCurdomain[$key] . '</td></tr>' . "\n";
        }
    }
    echo '</table><br />' . "\n";
}
?>
<table class="striped">
<thead>
<tr>
    <th><?php echo _("Actions")?></th>
    <th><?php echo _("Type") ?></th>
    <?php
    foreach ($fields as $field => $fdata) {
        if ((($fdata['infoset'] == 'basic') || $beatnikExpertmode) && $fdata['type'] != 'hidden') {
            echo '<th>' . $fdata['name'] . '</th>' . "\n";
        }
    }
    ?>
</tr>
</thead>
<tbody>
