<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\PasswordVerifier;

use Piwik\Container\StaticContainer;
use Piwik\Http;
use Piwik\Piwik;
use Piwik\Plugins\BulkTracking\Tracker\Response;
use Piwik\Validators\Exception;
use Psr\Log\LoggerInterface;

class PasswordVerifier extends \Piwik\Plugin
{

    public function registerEvents() {
        return array(
            'UsersManager.checkPassword' => 'verifyPassword'
        );
    }

    public function verifyPassword($password) {
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);
        $url = 'https://api.pwnedpasswords.com/range/' . $prefix;


        try {
            $response = Http::sendHttpRequest($url, $timeout = 10);
        } catch (\Exception $e) {
            $logger = StaticContainer::getContainer()->get('Psr\Log\LoggerInterface');
            $logger->warning("Can't reach haveibeenpwned.com");
            $logger->warning($e->getMessage());
            throw new Exception(Piwik::translate("PasswordVerifier_CantReachAPI"));
        }
        $hashes = [];
        if (strpos($response, $suffix) === false) {
            return true;
        }
        foreach (explode("\n", $response) as $hash) {
            $split = explode(":", $hash);
            $hashes[$split[0]] = (int)$split[1];
        }

        if (empty($hashes[$suffix])) {
            return true;
        }
        throw new \Exception(Piwik::translate('PasswordVerifier_PasswordFoundInDb', $hashes[$suffix]));
    }
}
