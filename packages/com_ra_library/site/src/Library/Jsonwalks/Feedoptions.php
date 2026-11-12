<?php

namespace Ramblers\Component\Ra_library\Site\Library\Jsonwalks;

/**
 * Description of feedoptions
 *
 * @author chris
 */
use Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;

class Feedoptions {

    private $sources = [];

    public function addWalksManagerGroupWalks($groups, $period = null) {
        $this->checkGroups($groups);
        $readwalks = true;
        $readevents = true;
        $wellbeingWalks = false;
        $source = new Sourcewalksmanager();
        $source->_initialise($groups, $readwalks, $readevents, $wellbeingWalks, $period);
        $this->sources[] = $source;
    }

    // Add specific walks/events by Walks Manager id, e.g. "100493667" or "1,2,3"
    public function addWalksManagerIds($ids) {
        if (!preg_match('/^[0-9]+(,[0-9]+)*$/', $ids)) {
            throw new \RuntimeException('Invalid ids input');
        }
        $source = new Sourcewalksmanager();
        $source->_initialiseIds($ids);
        $this->sources[] = $source;
    }

    public function addWalksManagerWellbeingWalks($groups) {
        $this->checkGroups($groups);
        $readwalks = false;
        $readevents = false;
        $wellbeingWalks = true;
        $source = new Sourcewalksmanager();
        $source->_initialise($groups, $readwalks, $readevents, $wellbeingWalks);
        $this->sources[] = $source;
    }

    public function addWalksManagerGroupsInArea($lat, $long, $km) {
        $readwalks = true;
        $readevents = true;
        $wellbeingWalks = false;
        $source = new Sourcewalksmanagerarea();
        $source->_initialiseArea($lat, $long, $km, $readwalks, $readevents, $wellbeingWalks);
        $this->sources[] = $source;
    }

    public function addWalksManagerWellbeingInArea($lat, $long, $km) {
        $readwalks = false;
        $readevents = false;
        $wellbeingWalks = true;
        $source = new Sourcewalksmanagerarea();
        $source->_initialiseArea($lat, $long, $km, $readwalks, $readevents, $wellbeingWalks);
        $this->sources[] = $source;
    }

    private function checkGroups($input) {
        $groups = explode(",", $input);
        foreach ($groups as $group) {
            $len = strlen($group);
            if ($len !== 2 && $len !== 4) {
                throw new \RuntimeException('Invalid group input');
            }
        }
    }

    public function getWalks($walks) {
        foreach ($this->sources as $source) {
            $source->getWalks($walks);
        }
        $walks->sort(Walk::SORT_DATE, Walk::SORT_TIME, Walk::SORT_DISTANCE);
        return;
    }
}
