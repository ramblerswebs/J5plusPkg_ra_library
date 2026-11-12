<?php

namespace Ramblers\Component\Ra_library\Site\Library\Jsonwalks\Wm;

/**
 * Description of feed
 *
 * @author Chris Vaughan
 */
use Joomla\CMS\Factory;

// Live Site Definition Below
define("WALKMANAGER", "https://walks-manager.ramblers.org.uk/api/volunteers/walksevents?");
// https://walks-manager.ramblers.org.uk/api/volunteers/walksevents?&types=group-walk&api-key=853aa876db0a37ff0e6780db2d2addee&groups=er05
// API Key for the walks manager
define("APIKEY", "853aa876db0a37ff0e6780db2d2addee");
// no direct access
defined('_JEXEC') or die('Restricted access');

class Feed {

    private $cacheFolder = null;

    public function __construct() {
        $this->cacheFolder = new Cachefolder('ra_events_feed');
        Fileio::setSecretStrings([APIKEY]);
    }

    public function getGroupEventItems($groups, $readwalks, $readevents, $readwellbeingwalks, $period) {
        $feedOpts = new Feedoptions();
        $feedOpts->groupCode = $groups;
        $feedOpts->include_walks = $readwalks;
        $feedOpts->include_events = $readevents;
        $feedOpts->include_wellbeing_walks = $readwellbeingwalks;
        $feedOpts->date_start = $period->getStartDateString();
        $feedOpts->date_end = $period->getEndDateString();
        $future = $period->isFuture();
        $items = $this->getFeed($feedOpts, $future);
        return $items;
    }

    // Read specific walks/events by Walks Manager id (no date window, so past items can be fetched)
    public function getItemsByIds($ids, $readwalks, $readevents) {
        $feedOpts = new Feedoptions();
        $feedOpts->ids = $ids;
        $feedOpts->include_walks = $readwalks;
        $feedOpts->include_events = $readevents;
        // a list of ids can be any combination, so only cache single ids (bounded by the number of walks)
        $feedOpts->useCache = (strpos($ids, ',') === false);
        return $this->getFeed($feedOpts, true);
    }

    public function getItemsWithinArea($latitude, $longitude, $distance, $readwalks, $readevents, $readwellbeingwalks) {
        $feedOpts = new Feedoptions();

        $feedOpts->latitude = $latitude;
        $feedOpts->longitude = $longitude;
        $feedOpts->distance = $distance;
        $feedOpts->include_walks = $readwalks;
        $feedOpts->include_events = $readevents;
        $feedOpts->include_wellbeing_walks = $readwellbeingwalks;
        $items = $this->getFeed($feedOpts);
        return $items;
    }

    private function getFeed($feedOpts, $future = true) {
        $debug = false;
        $feedurl = $feedOpts->getFeedURL();
        $cachedWalksFileName = $feedOpts->getCacheFileName("walks.json");
        $source = $this->whichSource($cachedWalksFileName);
        if (!$future) {
            $source = READSOURCE::FEED;
        }
        if (!$feedOpts->useCache) {
            $source = READSOURCE::FEED;
        }

        if ($debug) {
            $this->debugMsg($feedurl, "Reading data from " . $source);
        }
        if ($source === READSOURCE::CACHE) {
            $result = $this->cacheFolder->readFile($cachedWalksFileName);
            if ($result === false) {
                Fileio::errorMsg("Error reading walks from cache: ");
                $source = READSOURCE::FEED;
            }
        }
        if ($source === READSOURCE::FEED) {
            // read from feed and then cache data
            $result = Fileio::readFile($feedurl);
            if ($result === false) { // unable to read feed
                Fileio::errorMsg("Error reading walks from Central Office: ");
                if ($this->cacheFolder->fileExists($cachedWalksFileName)) {
                    // try reading walks from cache
                    $result = $this->cacheFolder->readFile($cachedWalksFileName);
                    if ($result === false) {
                        Fileio::errorMsg("Error reading walks from cache: ");
                    }
                }
            } else {
                // write walks to cache file
                if ($feedOpts->useCache) {
                    $this->cacheFolder->writeFile($cachedWalksFileName, $result);
                }
            }
        }

        if ($result === false) {
            return [];
        }
        $items = $this->convertResults($result, $feedurl);
        if ($items === null) {
            return [];
        }
        return $items->data;
    }

    private function whichSource($cachedWalksFileName) {
        if ($this->cacheFolder->fileExists($cachedWalksFileName)) {
            $cachedDate = $this->cacheFolder->lastModified($cachedWalksFileName);
            if ($cachedDate !== false) {
                $date = new \DateTimeImmutable();
                $elapsed = $date->getTimestamp() - $cachedDate;
                if ($elapsed < 60 * 10) { // walks were refreshed within 10 minutes
                    return READSOURCE::CACHE;
                }
            }
        }
        return READSOURCE::FEED;
    }

    private function convertResults($result, $feedurl) {
        if ($result === null) {
            return null;
        }
        if (!self::startsWith("$result", "{")) {
            self::errorMsg($feedurl, "JSON code does not start with {");
            return null;
        }
        $items = json_decode($result);
        if (json_last_error() === JSON_ERROR_NONE) {
            $properties = [];
            if (self::checkJsonFileProperties($items->data, $properties) > 0) {
                self::errorMsg($feedurl, "Expected properties not found in JSON feed");
                return null;
            }
        } else {
            self::errorMsg($feedurl, "Error when decoding JSON feed: " . json_last_error_msg());
            return null;
        }
        return $items;
    }

    private static function checkJsonFileProperties($json, $properties) {
        $errors = 0;
        foreach ($json as $item) {
            $ok = self::checkJsonProperties($item, $properties);
            $errors += $ok;
        }
        return $errors;
    }

    private static function checkJsonProperties($item, $properties) {
        foreach ($properties as $value) {
            if (!self::checkJsonProperty($item, $value)) {
                return 1;
            }
        }
        return 0;
    }

    private static function checkJsonProperty($item, $property) {
        if (property_exists($item, $property)) {
            return true;
        }
        return false;
    }

    private static function errorMsg($url, $msg) {
        $urlout = str_replace(APIKEY, '???', $url);
        RErrors::notifyError("Error reading walks: " . $msg . " " . $urlout, "Walks Manager", 'error');
    }

    private static function debugMsg($url, $msg) {
        $urlout = str_replace("&api-key=" . APIKEY, '', $url);
        $app = Factory::getApplication();
        $app->enqueueMessage("Debug reading walks: " . $msg . " " . $urlout, 'Information');
    }

    private static function startsWith($string, $startString) {
        $len = strlen($startString);
        return (substr($string, 0, $len) === $startString);
    }
}

abstract class READSOURCE {

    const FEED = 'FEED';
    const CACHE = 'CACHE';
}
