<?php
/**
 * Beatnik application interface.
 *
 * This file defines Horde's application interface. Other Horde libraries
 * and applications can interact with Beatnik through this API.
 *
 * Copyright 2006-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you did not
 * did not receive this file, see http://www.horde.org/licenses/gpl
 *
 * @author  Ben Klang <bklang@horde.org>
 * @package Beatnik
 */

if (!defined('BEATNIK_BASE')) {
    define('BEATNIK_BASE', __DIR__. '/..');
}

if (!defined('HORDE_BASE')) {
    /* If horde does not live directly under the app directory, the HORDE_BASE
     * constant should be defined in config/horde.local.php. */
    if (file_exists(BEATNIK_BASE. '/config/horde.local.php')) {
        include BEATNIK_BASE . '/config/horde.local.php';
    } else {
        define('HORDE_BASE', BEATNIK_BASE . '/..');
    }
}

/* Load the Horde Framework core (needed to autoload
 * Horde_Registry_Application::). */
require_once HORDE_BASE . '/lib/core.php';


class Beatnik_Application extends Horde_Registry_Application
{
    public $version = 'H6 (1.0-git)';
    public $driver = null;
    public $domains = null;

    protected function _init()
    {
        $this->driver = Beatnik_Driver::factory();

        // Get a list of domains to work with
        $this->domains = $this->driver->getDomains();

        $session = $GLOBALS['session'];

        // Jump to new domain
        if (Horde_Util::getFormData('curdomain') !== null && !empty($this->domains)) {
            try {
                $domain = $this->driver->getDomain(Horde_Util::getFormData('curdomain'));
            } catch (Exception $e) {
                $GLOBALS['notification']->push($e->getMessage(), 'horde.error');
                $domain = $domains[0];
            }

            $session->set('beatnik', 'curdomain', $domain);
        }

        // Determine if the user should see basic or advanced options
        if (!$session->exists('beatnik', 'expertmode')) {
            $session->set('beatnik', 'expertmode', false);
        } elseif (Horde_Util::getFormData('expertmode') == 'toggle') {
            if ($session->get('beatnik', 'expertmode')) {
                $GLOBALS['notification']->push(_("Expert Mode off"), 'horde.message');
                $session->set('beatnik', 'expertmode', false);
            } else {
                $GLOBALS['notification']->push(_("Expert Mode ON"), 'horde.warning');
                $session->set('beatnik', 'expertmode', true);
            }
        }

        // Initialize the page marker
        if (!$session->exists('beatnik', 'curpage')) {
            $session->set('beatnik', 'curpage', 0);
        }
    }

    /**
     */
    public function perms()
    {
        $perms = array(
            'domains' => array(
                'title' => _("Domains")
            ),
        );

        // Run through every domain
        foreach ($this->driver->getDomains() as $domain) {
            $perms['domains:' . $domain['zonename']] = array(
                'title' => $domain['zonename']
            );
        }

        return $perms;
    }

    /**
     */
    public function menu($menu)
    {
        $session = $GLOBALS['session'];

        // We are editing rather than adding if an ID was passed
        $editing = Horde_Util::getFormData('id');
        $editing = !empty($editing);

        $menu->add(Horde::url('listzones.php'), _("List Domains"), 'website.png');
        $curdomain = $session->get('beatnik', 'curdomain');
        if (!empty($curdomain)) {
            $menu->add(Horde::url('editrec.php')->add('curdomain', $curdomain['zonename']), ($editing) ? _("Edit Record") : _("Add Record"), 'edit.png');
        } else {
            $menu->add(Horde::url('editrec.php?rectype=soa'), _("Add Zone"), 'edit.png');
        }

        $url = Horde::selfUrl(true)->add(array('expertmode' => 'toggle'));
        $menu->add($url, _("Expert Mode"), 'hide_panel.png', null, '', null, $session->get('beatnik', 'expertmode') ? 'current' : '');

        if (count(Beatnik::needCommit())) {
            $url = Horde::url('commit.php')->add(array('domain' => 'all'));
            $menu->add($url, _("Commit All"), 'commit-all.png');
        }

    }

}
