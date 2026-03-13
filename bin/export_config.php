#!/usr/bin/env php
<?php
/**
 * Helper for creating mass config files
 *
 * NOTE You should change the creation section to swit your needs
 *
 * USE:
 *  php ./export_config.php --type=TYPE --username=USR --password=PASS --rpc=URL > hosts
 *
 * Copyright 2008-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author Duck <duck@obala.net>
 * @package Beatnik
 */

use Horde\Argv\Parser;
use Horde\Argv\Option;

define('AUTH_HANDLER', true);
define('HORDE_BASE', __DIR__ . '/../../');
define('BEATNIK_BASE', HORDE_BASE . '/beatnik');

// Do CLI checks and environment setup first.
require_once HORDE_BASE . '/lib/core.php';

// Make sure no one runs this from the web.
if (!Horde_CLI::runningFromCLI()) {
    exit("Must be run from the command line\n");
}

// Load the CLI environment.
$cli = Horde_Cli::init();

// Parse command-line options using Horde\Argv\Parser
$parser = new Parser([
    'usage' => '%prog [OPTIONS]',
    'description' => 'Export DNS configuration in various formats',
    'optionList' => [
        new Option('-u', '--username', [
            'type' => 'string',
            'dest' => 'username',
            'help' => 'Horde login username'
        ]),
        new Option('-p', '--password', [
            'type' => 'string',
            'dest' => 'password',
            'help' => 'Horde login password'
        ]),
        new Option('-t', '--type', [
            'type' => 'string',
            'dest' => 'type',
            'help' => 'Export format'
        ]),
        new Option('-r', '--rpc', [
            'type' => 'string',
            'dest' => 'rpc',
            'help' => 'Remote RPC URL (e.g., http://example.com/horde/rpc.php)'
        ])
    ]
]);

list($opts, $args) = $parser->parseArgs();

$username = $opts->username;
$password = $opts->password;
$type = $opts->type;
$rpc = $opts->rpc;

if (!empty($rpc)) {
    // We will fetch data from RPC
    $http_params = array(
        'request.username' => $username,
        'request.password' => $password
    );
    $language = isset($GLOBALS['language']) ? $GLOBALS['language'] :
            (isset($_SERVER['LANG']) ? $_SERVER['LANG'] : '');
    if (!empty($language)) {
        $http_params['request.headers'] = array('Accept-Language' => $language);
    }
    $http = $GLOBALS['injector']->getInstance('Horde_Core_Factory_HttpClient')->create($http_params);
    try {
        $domains = Horde_RPC::request('xmlrpc', $rpc, 'dns.getDomains', $http, array());
    } catch (Exception $e) {
        $cli->fatal($e);
    }

// Login to horde if username & password are set and load module.
} elseif (!empty($username) && !empty($password)) {
    require_once HORDE_BASE . '/lib/base.php';
    $auth = $injector->getInstance('Horde_Core_Factory_Auth')->create();
    if (!$auth->authenticate($username, array('password' => $password))) {
        $error = _("Login is incorrect.");
        Horde::log($error, 'ERR');
        $cli->fatal($error);
    } else {
        $msg = sprintf(_("Logged in successfully as \"%s\"."), $username);
        Horde::log($msg, 'DEBUG');
        $cli->message($msg, 'cli.success');
    }

    require_once BEATNIK_BASE . '/lib/base.php';

} else {
    $msg = _("Have noting to do.");
    $cli->fatal($msg);
}

// Steps
if (empty($type)) {
    $type = 'tinydns';
}
$function = '_' . $type;
echo $function();

/**
 * Get domain records
 */
function _getRecords($domain)
{
    if (empty($GLOBALS['rpc'])) {
        $result = $GLOBALS['beatnik_driver']->getRecords($domain);
    } else {
        try {
        $result = Horde_RPC::request('xmlrpc', $GLOBALS['rpc'],
                                     'dns.getRecords',
                                     $GLOBALS['http'],
                                     array($domain));
        } catch (Exception $e) {
            $GLOBALS['cli']->fatal($e);
        }
    }

    return $result;
}

/**
 * Generate unix hosts file
 */
function _hosts()
{
    $c = "# Generated with beatnik on " . date('Y-m-d h:i:s') . " by " . $GLOBALS['username'] . "\n\n";
    foreach ($GLOBALS['domains'] as $domain) {

        $zonename = $domain['zonename'];
        $tld = substr($zonename, strrpos($zonename, '.')+1);
        $domain = substr($zonename, 0, strrpos($zonename, '.'));

        foreach ($zonedata['cname'] as $id => $values) {
            extract($values);
            if (!empty($hostname)) {
                $hostname .= '.';
            }
            $c .= "$pointer $hostname.$domain.$tld\n";
        }
    }

    return $c;
}

/**
 * Generate bash script
 */
function _bash()
{
    $c = "# Generated with beatnik on " . date('Y-m-d h:i:s') . " by " . $GLOBALS['username'] . "\n\n";
    foreach ($GLOBALS['domains'] as $domain) {

        $zonename = $domain['zonename'];
        $tld = substr($zonename, strrpos($zonename, '.')+1);
        $domain = substr($zonename, 0, strrpos($zonename, '.'));

        $c .= "useradd $zonename" . "_$tld -d /var/www/$zonename" . "_$tld -s /bin/false" . "\n";
        $c .= "mkdir /var/www/$zonename" . "_$tld/" . "\n";
        $c .= "chown -R $zonename" . "_$tld:apache /var/www/$zonename" . "_$tld/" . "\n";

        foreach ($zonedata['cname'] as $id => $values) {
            extract($values);
            if (empty($hostname)) {
                // use empty hostname as alias for www
                $c .= "ln -s /var/www/$zonename" . "_$tld/$zonename.$tld " .
                           "/var/www/$zonename" . "_$tld/$hostname.$zonename.$tld" . "\n";
                continue;
            }

            $c .= "mkdir /var/www/$zonename" . "_$tld/$hostname.$zonename.$tld" . "\n";
            $c .= "mkdir /var/tmp/www/$hostname.$zonename.$tld" . "\n";
            $c .= "mkdir /var/log/apache2/$hostname.$zonename.$tld" . "\n";
        }
    }

    return $c;
}

/**
 * Generate apache host defs
 */
function _apache()
{
    $c = "# Generated with beatnik on " . date('Y-m-d h:i:s') . " by " . $GLOBALS['username'] . "\n\n";
    foreach ($GLOBALS['domains'] as $domain) {
        // Get default data and skip if no cnames
        $zonedata = _getRecords($domain['zonename']);
        if (!isset($zonedata['cname'])) {
            continue;
        }

        // data
        $zonename = $domain['zonename'];
        $tld = substr($zonename, strrpos($zonename, '.')+1);
        $domain = substr($zonename, 0, strrpos($zonename, '.'));
        foreach ($zonedata['cname'] as $id => $values) {

            extract($values);
            if (empty($hostname)) {
                continue; // use empty hostname as alias for www
            }

            $c .= "\n";
            $c .= "<VirtualHost $hostname.$zonename:80>\n";
            $c .= "    DocumentRoot /var/www/$domain" . "_$tld/$hostname.$zonename\n";
            $c .= "    ServerName $hostname.$zonename\n";
            if ($hostname == 'www') {
                $c .= "    ServerAlias $zonename\n";
            }
            $c .= "    ErrorLog logs/$hostname.$zonename/error_log\n";
            $c .= "    TransferLog logs/$hostname.$zonename/access_log\n";
            $c .= "    php_admin_value upload_tmp_dir \"/var/tmp/www/$hostname.$zonename\"\n";
            $c .= "    php_admin_value open_basedir \".:/usr/lib/php:/var/tmp/www/$hostname.$zonename:/var/www/$domain" . "_$tld/$hostname.$zonename\"\n";
            $c .= "</VirtualHost>\n";
        }
    }

    return $c;
}

/**
 * Generate tinydns defs
 */
function _tinydns()
{
    $c = "# Generated with beatnik on " . date('Y-m-d h:i:s') . " by " . $GLOBALS['username'] . "\n\n";
    foreach ($GLOBALS['domains'] as $domain) {
        // Get zone data
        $zonedata = _getRecords($domain['zonename']);
        $c .= '# ' . $domain['zonename'] . "\n";

        // default SOA, NS
        $c .= '.' . $domain['zonename'] . ':' . gethostbyname($domain['zonens']) . ':' . $domain['zonens'] . ':' . $domain['ttl'] . "\n";

        // NS records
        if (isset($zonedata['ns'])) {
            foreach ($zonedata['ns'] as $id => $values) {
                $c .= '.' . $domain['zonename'] . ':'.  gethostbyname($values['pointer']) . ':' . $values['pointer'] . ':' . $values['ttl'] . "\n";
            }
        }

        // MX records
        if (isset($zonedata['mx'])) {
            foreach ($zonedata['mx'] as $id => $values) {
                $c .= '@' . $domain['zonename'] . ':'.  gethostbyname($values['pointer']) . ':' . $values['pointer'] . ':' . $values['pref'] . ':' . $values['ttl'] . "\n";
            }
        }

        // PTR records
        if (isset($zonedata['ptr'])) {
            foreach ($zonedata['ptr'] as $id => $values) {
                $c .= '=';
                $c .= $values['hostname'] . '.' . $domain['zonename'] . ':' . $values['pointer'] . ':' . $values['ttl'] . "\n";
            }
        }

        // A records
        if (isset($zonedata['a'])) {
            foreach ($zonedata['a'] as $id => $values) {
                $c .= '+';
                if ($values['hostname']) {
                    $c .= $values['hostname'] . '.';
                }
                $c .= $domain['zonename'] . ':' . $values['ipaddr'] . ':' . $values['ttl'] . "\n";
            }
        }

        // CNAME records
        if (isset($zonedata['cname'])) {
            foreach ($zonedata['cname'] as $id => $values) {
                $c .= 'C';
                if ($values['hostname']) {
                    $c .= $values['hostname'] . '.';
                }
                $c .= $domain['zonename'] . ':' . $values['pointer'] . ':' . $values['ttl'] . "\n";
            }
        }

        $c .= "\n";
    }

    return $c;
}
