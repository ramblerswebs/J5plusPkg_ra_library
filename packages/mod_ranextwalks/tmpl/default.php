<?php

/**
 * @module      RA Next Walks
 * @author      Chris Vaughan
 * @website     http://ramblers-webs.org.uk/
 * @copyright   Copyright (C) 2024 Chris Vaughan webmaster@ramblers-webs.org.uk All rights reserved.
 * @license     http://www.gnu.org/licenses/gpl.html GNU/GPL
 */
defined('_JEXEC') or die;

use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Feed;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Feedoptions;
use Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Std\Nextwalks;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;

// -----------------------------------------------------------------------
// Module logic
// -----------------------------------------------------------------------
$doc = Factory::getApplication()->getDocument();
$doc->addStyleDeclaration('
    .mod-nextwalks-content {margin-left:5px;
    margin-right:5px;}
');

$comParams = ComponentHelper::getParams('com_ra_library');
$newEvents = (int) $comParams->get('daysfornew', 7);

$groupsString = $params->get('ramblersgroups', '');
if ($groupsString === "") {
    throw new \RuntimeException('Next walks module: no groups specified');
}
$limit = $params->get('limit', 7);

echo "<div class='mod-nextwalks-content'>";
$codes = array_column(json_decode($groupsString), 'code');
$groups = implode(",", $codes);

$options = new Feedoptions();
$options->addWalksManagerGroupWalks($groups);
$feed = new Feed($options);
$feed->limitNumberWalks($limit);
$feed->setNewWalks($newEvents);
$display = new Nextwalks();
$feed->Display($display);
echo "</div>";
