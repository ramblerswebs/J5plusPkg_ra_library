<?php

namespace Ramblers\Component\Ra_library\Site\Library\Leaflet;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

use Joomla\CMS\Component\ComponentHelper;
use Ramblers\Component\Ra_eventbooking\Site\Helper\Ra_eventbookingHelper;
use Ramblers\Component\Ra_library\Site\Library\Load\Load;
use Ramblers\Component\Ra_library\Site\Helper\Ra_libraryHelper as helper;
//use Joomla\CMS\Version\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

class Script {

    private $command = 'noDirectAction';
    private $dataObject = null;

    public function __construct() {
        // os license keys
        helper::setData();
    }

    public function setCommand($command) {
        $this->command = $command;
    }

    public function setDataObject($value) {
        $this->dataObject = $value;
    }

    public function add($options) {
        //  $version = new Version();
        //  $jv= $version->getShortVersion();
        $jv = '5.3.0';
        $document = Factory::getDocument();
        $options->setLicenses();
        if ($this->command !== "noDirectAction") {
            echo "<div id='" . $options->divId . "'></div>" . PHP_EOL;
        }
        //    $text = "window.addEventListener('load', function () {" . PHP_EOL;
        $text = "(function () {var run = function () {" . PHP_EOL;
        $text .= "var mapOptions='" . addslashes(json_encode($options)) . "';" . PHP_EOL;
        // set data object for this command      
        if ($this->dataObject !== null) {
            $jsonString = json_encode($this->dataObject);
            if ($jsonString === false) {
                $error = json_last_error_msg();
                throw new \RuntimeException('Script.php: Unable to convert data to json: ' . $error);
            }
            $text .= "var data='" . addslashes(json_encode($this->dataObject)) . "';" . PHP_EOL;
        } else {
            $text .= "var data=null;" . PHP_EOL;
        }

        $text .= "ra.bootstrapper('" . $jv . "','" . $this->command . "',mapOptions,data);};" . PHP_EOL;
        $text .= "if (document.readyState === 'loading') {document.addEventListener('DOMContentLoaded', run);} else {run();}})();" . PHP_EOL;
        $document->addScriptDeclaration($text);

        $this->addScriptsandStyles($options);
    }

    private function addScriptsandStyles($options) {

        HTMLHelper::_('jquery.framework');

        $document = Factory::getDocument();

        Load::addScript("media/com_ra_library/js/ra.js");
        // Leaflet
        $document->addStyleSheet("https://unpkg.com/leaflet@1.9.4/dist/leaflet.css");
        Load::addScript("https://unpkg.com/leaflet@1.9.4/dist/leaflet.js");
        // Load::addScript("media/com_ra_library/js/vendors/leaflet/leaflet.js");
        Load::addScript("media/com_ra_library/js/leaflet/ra.leafletmap.js");
        Load::addStyleSheet("media/com_ra_library/css/ramblerslibrary.css");
        Load::addStyleSheet("media/com_ra_library/js/leaflet/ramblersleaflet.css");
        Load::addScript("https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.15.0/proj4.js");
        Load::addScript("https://cdnjs.cloudflare.com/ajax/libs/proj4leaflet/1.0.2/proj4leaflet.min.js");

        $path = "media/com_ra_library/js/vendors/Leaflet.fullscreen-1.0.2/dist/";
        Load::addScript($path . "Leaflet.fullscreen.min.js");
        Load::addStyleSheet($path . "leaflet.fullscreen.css");

        if ($options->displayElevation !== null) {
            // elevation
            $document->addScript("https://d3js.org/d3.v3.min.js");
            $path = "media/com_ra_library/js/vendors/Leaflet.Elevation-0.0.4-ra/";
            Load::addScript($path . "leaflet.elevation-0.0.4.src.js");
            $document->addStyleSheet($path . "elevation.css");
            Load::addScript("media/com_ra_library/js/vendors/leaflet-gpx-1.3.1/gpx.js");
        }

        if ($options->licenseKeys->OSkey !== null) {
            Load::addScript("https://cdn.jsdelivr.net/gh/OrdnanceSurvey/os-api-branding@0.3.1/os-api-branding.js");
            Load::addStyleSheet("https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.css");
            Load::addScript("https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.js");
            Load::addScript("https://unpkg.com/@maplibre/maplibre-gl-leaflet@0.1.1/leaflet-maplibre-gl.js");
        }
        if ($options->licenseKeys->OSMVectorStyle !== null) {
            Load::addStyleSheet("https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.css");
            Load::addScript("https://unpkg.com/maplibre-gl@5.3.0/dist/maplibre-gl.js");
            Load::addScript("https://unpkg.com/@maplibre/maplibre-gl-leaflet@0.1.1/leaflet-maplibre-gl.js");
        }
        // clustering
        $path = "media/com_ra_library/js/vendors/Leaflet.markercluster-1.5.3/dist/";
        Load::addStyleSheet($path . "MarkerCluster.Default.css");
        Load::addStyleSheet($path . "MarkerCluster.css");
        Load::addScript($path . "leaflet.markercluster.js");
        Load::addScript("media/com_ra_library/js/vendors/Leaflet.FeatureGroup.SubGroup-1.0.2/src/subgroup.js");
        Load::addScript("media/com_ra_library/js/leaflet/L.RA.FootpathNetworkLayer.js");

// subGroup used by Places.js
        Load::addScript("media/com_ra_library/js/leaflet/L.Control.Places.js");
        Load::addScript("media/com_ra_library/js/leaflet/L.Control.Mouse.js");
        Load::addStyleSheet("media/com_ra_library/js/leaflet/L.Control.Mouse.css");

        Load::addScript("media/com_ra_library/js/vendors/Leaflet.Control.Resizer-0.0.1/L.Control.Resizer.js");
        Load::addStyleSheet("media/com_ra_library/js/vendors/Leaflet.Control.Resizer-0.0.1/L.Control.Resizer.css");

        if ($options->mouseposition !== null or $options->osgrid !== null or $options->rightclick !== null) {
            // grid ref to/from lat/long
            Load::addScript("media/com_ra_library/js/vendors/geodesy/vector3d.js");
            Load::addScript("media/com_ra_library/js/vendors/geodesy/latlon-ellipsoidal.js");
            Load::addScript("media/com_ra_library/js/vendors/geodesy/osgridref.js");
        }


        $path = "media/com_ra_library/js/vendors/leaflet.browser.print-1/dist/";
        Load::addScript($path . "leaflet.browser.print.js");
        //     Load::addScript($path . "leaflet.browser.print.sizes.js");
        //     Load::addScript($path . "leaflet.browser.print.utils.js");

        if ($options->calendar) {
            $path = "media/com_ra_library/js/vendors/fullcalendar-6.1.9/";
            Load::addScript($path . "index.global.js");
            // Load::addStyleSheet($path . "main.css");
        }

        Load::addScript("media/com_ra_library/js/ra.js");
        Load::addScript("media/com_ra_library/js/ra.map.js");
        Load::addScript("media/com_ra_library/js/ra.walk.js");
        // Shared image gallery lightbox (also used by the Past Walks/Routes
        // views) - ra.event.media's addMediaSection() now uses this instead
        // of its own bespoke ra.modals/ra.previousNext popup.
        Load::addScript("media/com_ra_library/js/ra.lightbox.js");
        Load::addStyleSheet("media/com_ra_library/css/ra.lightbox.css");
        Load::addScript("media/com_ra_library/js/ra.tabs.js");
        Load::addScript("media/com_ra_library/js/ra.paginatedDataList.js");
        Load::addStyleSheet("media/com_ra_library/css/ra.paginatedDataList.css");
        Load::addScript("media/com_ra_library/js/vendors/cvList/cvList.js");
        Load::addStyleSheet("media/com_ra_library/js/vendors/cvList/cvList.css");
        Load::addStyleSheet("media/com_ra_library/css/ra.tabs.css");

        if (ComponentHelper::isEnabled('com_ra_eventbooking')) {
            Ra_eventbookingHelper::loadScripts();
        }
        // my location start
        Load::addScript("media/com_ra_library/js/leaflet/L.Control.MyLocation.js");
        $document->addScript("https://cdn.jsdelivr.net/npm/leaflet.locatecontrol/dist/L.Control.Locate.min.js");
        $document->addStyleSheet("https://cdn.jsdelivr.net/npm/leaflet.locatecontrol/dist/L.Control.Locate.min.css");
        // my location finish

        Load::addScript("media/com_ra_library/js/leaflet/L.Control.RAContainer.js");
        Load::addScript("media/com_ra_library/js/leaflet/L.Control.Tools.js");
        Load::addScript("media/com_ra_library/js/leaflet/L.Control.Search.js");

        // settings
        //  Load::addStyleSheet("media/com_ra_library/js/leaflet/L.Control.Settings.css");
        Load::addScript("media/com_ra_library/js/leaflet/ra.map.settings.js");
        Load::addScript("media/com_ra_library/js/ra.feedhandler.js");
        Load::addScript("media/com_ra_library/js/vendors/FileSaver-js-1.3.8/src/FileSaver.js");
    }

    public static function registerWalks($walks) {
        // register walks from php methods into raWalks.js for display
        $data = new \stdClass();
        $data->walks = $walks;
        //   $print = json_encode($walks, JSON_PRETTY_PRINT);
        //   echo "<pre>" . $print . "</pre>";
        $script = new Script();
        $options = new Mapoptions();
        $script->setCommand("ra.walk.registerPHPWalks");
        $script->setDataObject($data);
        $script->add($options);
    }
}
