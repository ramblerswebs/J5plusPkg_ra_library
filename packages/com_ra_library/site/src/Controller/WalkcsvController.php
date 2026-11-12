<?php

namespace Ramblers\Component\Ra_library\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Feedoptions;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Feed as WalksFeed;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std\Walkcsv;

/*
 * index.php?option=com_ra_library&task=walkcsv.walkcsv&groups=ER05,ER06
 *
 * Sends the future walks and events of the groups as a CSV file download
 * (the column layout is in Library/Jsonwalks/Std/Walkcsv.php).
 *
 *      groups   comma separated group codes (required), 2 or 4 characters each, at most MAX_GROUPS
 *
 * The link is made by the {ra_library:walkcsv groups="..."} article tag. Nothing is stored on the server.
 */

class WalkcsvController extends BaseController {

    const MAX_GROUPS = 20;

    public function walkcsv(): void {
        $app = Factory::getApplication();

        try {
            $groups = strtoupper(trim($app->input->getString('groups', '')));
            // 2 characters is an area, 4 a group; Feedoptions rejects anything else
            if (!preg_match('/^[A-Z0-9]{2}([A-Z0-9]{2})?(,[A-Z0-9]{2}([A-Z0-9]{2})?)*$/', $groups)
                    || count(explode(',', $groups)) > self::MAX_GROUPS) {
                throw new \RuntimeException('Incorrect options', 400);
            }

            $options = new Feedoptions();
            $options->addWalksManagerGroupWalks($groups);
            $feed = new WalksFeed($options);
            $walks = $feed->getWalks();   // already in date, time, distance order

            // Everything is built before anything is sent, so a failure does not leave a half file
            $handle = fopen('php://temp', 'w+');
            (new Walkcsv())->write($handle, $walks);
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="walks-' . gmdate('Ymd') . '.csv"');
            header('Cache-Control: private, max-age=600');
            header('X-Content-Type-Options: nosniff');
            echo $csv;
        } catch (\Throwable $e) {
            header('Content-Type: text/plain; charset=utf-8', true);
            http_response_code(400);
            echo 'Incorrect options: ' . $e->getMessage();
        }

        $app->close();
    }
}
