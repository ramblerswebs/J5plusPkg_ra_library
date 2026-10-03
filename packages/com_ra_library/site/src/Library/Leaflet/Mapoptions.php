<?php

namespace Ramblers\Component\Ra_library\Site\Library\Leaflet;

/**
 * Description of mapoptions
 *
 * @author Chris Vaughan
 */
use Joomla\CMS\Uri\Uri;
use Ramblers\Component\Ra_library\Site\Library\License\License;

class Mapoptions {

    // Map options

    public $divId = "";
    public $base = "";
    public $mapHeight = "500px";
    public $mapWidth = "100%";
    public $licenseKeys;
    public $helpPage = "";
    // the following can be true of false
    public $cluster = false;
    public $fitbounds = false;
    public $resizer = true;
    public $controlcontainer = false; // used by Walks Editor
    public $displayElevation = false;
    public $calendar = false;
    public $topoMapDefault = false;
    public $initialview = null;
    public $copyright = true;
    // always on
    public $fullscreen = true;
    public $settings = true;
    public $mylocation = true;
    public $print = true;
    //// ************** these options have three values
    // null never display option
    // true always display option
    // false only display option on full screen
    public $mouseposition = false;
    public $rightclick = false;
    public $postcodes = true;

    // ************** END these options have three values

    public function __construct() {

        $this->divId = uniqid(rand());
        $this->base = Uri::base();
        $this->licenseKeys = new \stdClass();
        $this->licenseKeys->ORSkey = null;
        $this->licenseKeys->ESRIkey = null;
        $this->licenseKeys->OSTestStyle = null;
        $this->licenseKeys->OSMVectorStyle = null;
        $this->licenseKeys->mapBoxkey = null;
        $this->licenseKeys->thunderForestkey = null;
    }

    public function setinitialView($latitude, $longitude, $zoom) {
        $this->initialview = (object) [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'zoom' => $zoom
        ];
    }

    public function setLicenses() {

        $this->licenseKeys->ESRIkey = License::getESRILicenseKey();

        if (License::isOpenRoutingServiceKeySet()) {
            $this->licenseKeys->ORSkey = License::getOpenRoutingServiceKey();
        }

        $this->licenseKeys->OSkey = License::getOrdnanceSurveyLicenseKey();
        $this->licenseKeys->OSTestkey = License::getOrdnanceSurveyLicenseTestKey();
        $this->licenseKeys->OSTestStyle = License::getOrdnanceSurveyLicenseKeyTestStyle();
        $this->licenseKeys->OSMVectorStyle = License::getOSMVectoricenseKey();
        $this->licenseKeys->OSMNGD = License::getOrdnanceSurveyLicenseKeyNGD();
        $this->licenseKeys->mapBoxkey = License::getMapBoxLicenseKey();
        $this->licenseKeys->thunderForestkey = License::getThunderForestLicenseKey();
        $this->licenseKeys->W3Wkey = License::getW3WLicenseKey();
    }
}
