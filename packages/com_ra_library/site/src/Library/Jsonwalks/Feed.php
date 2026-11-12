<?php

/**
 * @version		0.0
 * @package		Simple JSON Feed reader
 * @author              Chris Vaughan Ramblers-webs.org.uk
 * @copyright           Copyright (c) 2021 Chris Vaughan. All rights reserved.
 * @license		GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 */

namespace Ramblers\Component\Ra_library\Site\Library\Jsonwalks;

// no direct access
defined('_JEXEC') or die('Restricted access');

use Ramblers\Component\Ra_library\Site\Library\Load\Load;
use Joomla\CMS\Factory;

class Feed {

    private $walks = [];
    private $options;

    public function __construct($options) {

        // the options must be a Feedoptions object (a string of group codes is no longer accepted)
        if (!($options instanceof Feedoptions)) {
            $app = Factory::getApplication();
            $app->enqueueMessage('Input to Feed is not valid', 'error');
            return;
        }
        $this->options = $options;

        $this->walks = new Walks(NULL);

        $this->options->getWalks($this->walks);
        $this->setNewWalks(7);
        $this->setBookings();
    }

    private function setBookings() {
        $this->walks->setBookings();
    }

    public function setNewWalks($days) {
        $this->walks->setNewWalks($days);
    }

    public function setDisplayLimit($no) {
        $app = Factory::getApplication();
        $app->enqueueMessage('WEBMASTER: $feed->setDisplayLimit is no longer supported', 'error');
    }

    public function filterCancelled() {
        $this->walks->filterCancelled();
    }

    public function filterStatus($status) {
        $this->walks->filterStatus($status);
    }

    public function filterFeedDistances($distances) {
        // used by calendar feed
        $this->walks->filterFeedDistances($distances);
    }

    public function filterWalksDistance($minDistance, $maxDistance) {
        $this->walks->filterDistance(floatval($minDistance), floatval($maxDistance));
    }

    public function filterWalksDistanceBelow($minDistance) {
        $this->walks->filterDistanceBelow(floatval($minDistance));
    }

    public function filterWalksDistanceAbove($maxDistance) {
        $this->walks->filterDistanceAbove(floatval($maxDistance));
    }

    public function filterDistanceFromLatLong($lat, $lon, $distanceKm) {
        $this->walks->filterDistanceFromLatLong(floatval($lat), floatval($lon), floatval($distanceKm));
    }

    public function filterGroups($filter) {
        $this->walks->filterGroups($filter);
    }

    public function filterEvents() {
        $this->walks->filterEvents();
    }

    public function filterWalks() {
        $this->walks->filterWalks();
    }

    public function filterTitle($filter, $option = 'remove') {
        $this->walks->filterTitle($filter, $option);
    }

    public function filterTitleContains($filter, $option = 'remove') {
        $this->walks->filterTitleContains($filter, $option);
    }

    public function filterNationalGrade($grades) {
        $this->walks->filterNationalGrade($grades);
    }

    public function filterFestivals($filter) {
        $this->walks->filterFestivals($filter);
    }

    public function noFestivals() {
        $this->walks->noFestivals();
    }

    public function allFestivals() {
        $this->walks->allFestivals();
    }

    public function filterDateRange($datefrom, $dateto) {
        if (!is_a($datefrom, 'DateTime')) {
            $app = Factory::getApplication();
            $app->enqueueMessage("feed->filterDateRange: first parameter is NOT Datetime", 'error');
            return;
        }
        if (!is_a($dateto, 'DateTime')) {
            $app = Factory::getApplication();
            $app->enqueueMessage("feed->filterDateRange: second parameter is NOT Datetime", 'error');
            return;
        }
        $dateto->setTime(0, 0, 0);
        $datefrom->setTime(0, 0, 0);
        $this->walks->filterDateRange($datefrom, $dateto);
    }

    public function filterDateBefore($datefrom) {
        if (!is_a($datefrom, 'DateTime')) {
            $app = Factory::getApplication();
            $app->enqueueMessage("feed->filterDateBefore: parameter is NOT Datetime", 'error');
            return;
        }
        $datefrom->setTime(0, 0, 0);
        $this->walks->filterDateBefore($datefrom);
    }

    public function filterDateAfter($dateto) {
        if (!is_a($dateto, 'DateTime')) {
            $app = Factory::getApplication();
            $app->enqueueMessage("feed->filterDateAfter: parameter is NOT Datetime", 'error');
            return;
        }
        $dateto->setTime(0, 0, 0);
        $this->walks->filterDateAfter($dateto);
    }

    public function filterDayofweek($days) {
        $this->walks->filterDayofweek($days);
    }

    public function filterFlags($flags, $include = true) {
        $this->walks->filterFlags($flags, $include);
    }

    public function limitNumberWalks($no) {
        $this->walks->limitNumberWalks($no);
    }

    public function numberWalks() {
        return count($this->walks->allWalks());
    }

    public function noCancelledWalks() {
        $number = 0;
        $items = $this->walks->allWalks();
        foreach ($items as $walk) {
            if ($walk->isCancelled()) {
                $number += 1;
            }
        }
        return $number;
    }

    public function appendWalkTitle($group, $title) {
        $ugroup = strtoupper($group);
        $items = $this->walks->allWalks();
        foreach ($items as $walk) {
            $walk->appendWalkTitle($ugroup, $title);
        }
    }

    public function display($displayclass) {
        if ($this->walks == null) {
            echo "No walks found";
            return;
        }
        Load::addStyleSheet('media/com_ra_library/css/ramblerslibrary.css');
        $displayclass->DisplayWalks($this->walks);
    }

//    public function displayIcsDownload($name, $pretext, $linktext, $posttext) {
//        $events = new REventGroup(); // create a group of events
//        $events->addWalks($this); // add walks to the group of events
//        $display = new REventDownload();
//        $display->setPreText($pretext);
//        $display->setLinkText($linktext);
//        $display->setPostText($posttext);
//        $display->Display($name, $events); // display walks information
//// is this line correct and is function used
//    }

    public function getWalks() {
        return $this->walks;
    }

    public function clearCache() {
        
    }

    public function filterFeed($filter) { // filter by component filter subform
        if ($filter->titlecontains !== '') {
            $this->filterTitleContains($filter->titlecontains, "keep");
        }
        if ($filter->titledoesntcontains !== '') {
            $this->filterTitleContains($filter->titledoesntcontains, "remove");
        }
        if ($filter->titleequals !== '') {
            $this->filterTitle($filter->titleequals, "keep");
        }
        if ($filter->titledoesntequal !== '') {
            $this->filterTitle($filter->titledoesntequal, "remove");
        }
        $limit = intval($filter->limit);
        if ($limit > 0) {
            $this->noWalks($limit);
        }
        $dist = floatval($filter->filterdistance);
        if ($dist > 0) {
            $this->filterDistanceFromLatLong(floatval($filter->filtercentrelatitude), floatval($filter->filtercentrelongitude), $dist);
        }
        if (count($filter->filternationalgrades) > 0) {
            $this->filterNationalGrade($filter->filternationalgrades);
        }
        if (count($filter->filterdaysofweek) > 0) {
            $this->filterDayofweek($filter->filterdaysofweek);
        }
        if ($filter->filterstrand !== '') {
            $this->filterStrands($filter->filterstrand);
        }
    }

    // function used to test filters on local PC
    public function testFilters() {

        $a = "BREAK HERE";
        // uncomment the filter you wish to test
        //    $this->setNewWalks(10); // Sets the number of days that a walk is considered to be 'new' after it has been published or updated
        //   $this->noWalks(5); // remove any walks above this number, the oldest walks are removed

        $groups = ["de01", "de02"];       // removes any walks for groups not in the list
        //    $this->filterGroups($groups);  // removes any walks for groups not in the list

        $dow = ["Saturday", "Sunday"];   // removes any walks held on days not in the list
        //    $this->filterDayofweek($dow); // removes any walks held on days not in the list
        //    $this->filterCancelled(); // removes walks that have been cancelled  
        //    $this->filterTitle('filter', 'remove'); // remove or keep walks whose title is equal to filter, option='remove' or 'keep'.
        //    $this->filterTitleContains('filter', 'keep'); // remove or keep walks whose title contains filter, option='remove' or 'keep'.

        $grades = ['Leisurely', 'Technical'];   // set up an array contain one or more national walk grades from the list Easy Access, Easy, Leisurely, Moderate, Strenuous ,Technical
        //    $this->filterNationalGrade($grades); // remove walks whose grades are not in the list.
        //    filter by length of walk
        //    $this->filterWalksDistance(5.5,17);
// filter feed by dates
        $datefrom = new \DateTime('2023-05-01'); // set start date
        $dateto = new \DateTime('2024-02-11');  // set end date
        //    $this->filterDateRange($datefrom, $dateto);  // remove walks outside date range
// or filter feed from date and date interval
        $datefrom = new \DateTime(); // set date to today
        $dateto = new \DateTime(); // set date to today
        $dateto->add(new \DateInterval('P1M')); // add one month, 'P3M' is 3 months, 'P30D' is 30 days
        //    $this->filterDateRange($datefrom, $dateto);
// filter by distance from a location
        $lat = 5.3;
        $lon = 1.2;
        $distanceKm = 10000;
        //   $this->filterDistanceFromLatLong($lat, $lon, $distanceKm);
        $flags = ["Accessible by public transport", "No car needed"];
        $flags = ["Toilets available"];
        //    $this->filterFlags($flags, true);
// filter/remove events
        //   $this->filterEvents();
// filter/remove walks
        //    $this->filterWalks();
    }
}
