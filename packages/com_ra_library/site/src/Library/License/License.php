<?php

/*
 * copyright: Chris Vaughan
 * email: ruby.tuesday@ramblers-webs.org.uk
 */

namespace Ramblers\Component\Ra_library\Site\Library\License;

/**
 * Description of License
 *
 * @author chris
 */
use Joomla\CMS\Uri\Uri;

class License {

    private static $openRoutingServicelicensekey = null;
    private static $data = null;

    public static function set($data) {
        self::$data = $data;
    }

    public static function OpenRoutingServiceKey($value) {
        self::$openRoutingServicelicensekey = $value;
    }

    public static function getOpenRoutingServiceKey() {
        return self::$openRoutingServicelicensekey;
    }

    public static function isOpenRoutingServiceKeySet() {
        return self::$openRoutingServicelicensekey != "undefined";
    }

    // Common licenses for all domains

    public static function getOrdnanceSurveyLicenseTestKey() {
        if (strpos(Uri::base(), 'localhost') !== false) {
            return self::$data->OrdnanceSurveyLicenseTestKey;
        }
        if (strpos(Uri::base(), 'locahaberandlorn-ramblers') !== false) {
            return self::$data->OrdnanceSurveyLicenseTestKey;
        }
        return null;
    }

    public static function getOrdnanceSurveyLicenseKeyNGD() {
        if (strpos(Uri::base(), 'localhost') !== false) {
            return self::$data->OrdnanceSurveyLicenseKeyNGD;
        }
        if (strpos(Uri::base(), 'locahaberandlorn-ramblers') !== false) {
            return self::$data->OrdnanceSurveyLicenseKeyNGD;
        }
        return null;
    }

    public static function getOrdnanceSurveyLicenseKey() {
        return self::$data->OrdnanceSurveyLicenseKey;
    }

    // key to display OS test style map
    public static function getOrdnanceSurveyLicenseKeyTestStyle() {
        if (strpos(Uri::base(), 'localhost') !== false) {
            return self::$data->OrdnanceSurveyLicenseKeyTestStyle;
        }
        if (strpos(Uri::base(), 'locahaberandlorn-ramblers') !== false) {
            return self::$data->OrdnanceSurveyLicenseKeyTestStyle;
        }
        return null;
    }

    public static function getMapBoxLicenseKey() {
        return null;
    }

    public static function getThunderForestLicenseKey() {
        return null;
    }

    public static function getESRILicenseKey() {
        return "";
    }

    public static function getOSMVectoricenseKey() {
        if (strpos(Uri::base(), 'localhost') !== false) {
            return "";
        }
        if (strpos(Uri::base(), 'locahaberandlorn-ramblers') !== false) {
            return "";
        } else {
            return null;
        }
    }

    public static function getW3WLicenseKey() {
        return self::$data->W3WLicenseKey;
    }
}
