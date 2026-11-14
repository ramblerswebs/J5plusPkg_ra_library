<?php

/**
 * @module      RA Cancelled Walks
 * @author      Chris Vaughan
 * @website     http://ramblers-webs.org.uk/
 * @copyright   Copyright (C) 2024 Chris Vaughan webmaster@ramblers-webs.org.uk All rights reserved.
 * @license     http://www.gnu.org/licenses/gpl.html GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Feed;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Feedoptions;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std\Cancelledwalks;

// -----------------------------------------------------------------------
// Assets / CSS — use the Web Asset Manager (J5/6 standard)
// -----------------------------------------------------------------------
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle(
        'mod_racancelled.style',
        'modules/mod_racancelled/css/ramblerscancelled.css'
);

// -----------------------------------------------------------------------
// Module logic
// -----------------------------------------------------------------------
$groups = $params->get('ramblersgroups', '');
if ($groups === "") {
    $app = Factory::getApplication();
    $app->enqueueMessage('Cancelled walks module needs editing to correct specified groups', 'error');
}
$groupsPairs = json_decode($groups);
$groupsArray = array_column($groupsPairs, 'code');
$groupsCSV = implode(",", $groupsArray);
$diagnostics = (int) $params->get('diagnostics', 0);

$options = new Feedoptions();
$options->addWalksManagerGroupWalks($groupsCSV);
$feed = new Feed($options);
$display = new Cancelledwalks();

$number = $feed->noCancelledWalks();
$total = $feed->numberWalks();

if ($diagnostics === 1) {
    echo '<div class="cancelledWalks">';
    echo '<h3>Cancelled walks - Diagnostics</h3>';
    echo '<h4>Groups accessed: ' . htmlspecialchars((string) $groups, ENT_QUOTES, 'UTF-8') . '</h4>';
    echo '<h4>Number of cancelled walks: ' . (int) $number . '</h4>';
    echo '<h4>Total number of walks: ' . (int) $total . '</h4>';
    echo '</div>';
}

$feed->Display($display);
