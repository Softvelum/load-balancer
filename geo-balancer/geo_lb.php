<?php
/*
  Created by WMSPanel team, https://wmspanel.com/
  Contact: support@wmspanel.com
*/

require 'geoip2.phar';
use GeoIp2\Database\Reader;

class GeoLoadBalancer {

    public function __construct( $geoLiteCityPath = 'GeoLite2-City.mmdb' ) {
        $this->reader = new Reader($geoLiteCityPath);
        $this->regionToServerMap = array();
        $this->defaultServer = NULL;
    }

    public function setDefaultServer($url) {
        $this->defaultServer = rtrim($url, '/');
    }

    public function setRegionServer() {
        $numArguments = func_num_args();
        if( $numArguments == 2 ) {
            $root    = NULL;
            $regions = func_get_arg(0);
            $server  = func_get_arg(1);
        } else if( $numArguments == 3 ) {
            $root    = strtoupper(func_get_arg(0));
            $regions = func_get_arg(1);
            $server  = func_get_arg(2);
        } else {
            throw new Exception("{$numArguments} arguments passed to setRegionServer(), while it accepts either 2 or 3 arguments.");
        }

        if( $root != NULL ) {
            if( strlen($root) != 2 ) {
                throw new Exception("Root region must comply with ISO 3166-1 standard, e.g. consist of 2 letters");
            }
        }

        $server = rtrim($server, '/');

        foreach($regions as $region) {
            $region = strtoupper($region);

            $regionLength = strlen($region);
            if( $regionLength != 2 ) {
                if( ( ($regionLength != 5) && ($regionLength != 6) ) || ($region[2] != '-') ) {
                    throw new Exception("Region must comply with ISO 3166-1 or ISO 3166-2 standard. Should be either of 2 letters form (like 'US' or 'FR'), or 4-5 letters form for sub-region (like 'US-CA' or 'RU-KHA').");
                }
            }

            if( 2 == $regionLength ) {
                if( $root != NULL ) {
                    $subRegion = $region;
                    $region = $root;
                } else {
                    $subRegion = 'default';
                }
            } else {
                if( $root != NULL ) {
                    $subRoot = $region[0] . $region[1];
                    if( $root != $subRoot ) {
                        throw new Exception("Sub-region {$region} doesn't correspond to region {$root}.");
                    }
                    if( 5 == $regionLength ) {
                        $subRegion = $region[3] . $region[4];
                    } else {
                        $subRegion = $region[3] . $region[4] . $region[5];
                    }
                    $region = $root;
                } else {
                    if( 5 == $regionLength ) {
                        $subRegion = $region[3] . $region[4];
                    } else {
                        $subRegion = $region[3] . $region[4] . $region[5];
                    }
                    $region = $region[0] . $region[1];
                }
            }

            if ( !isset($this->regionToServerMap[$region]) ) {
                $this->regionToServerMap[$region] = array();
                $this->regionToServerMap[$region][$subRegion] = $server;
            } else {
                if( !isset($this->regionToServerMap[$region][$subRegion]) ) {
                    $this->regionToServerMap[$region][$subRegion] = $server;
                } else {
                    throw new Exception("Attempt to assign {$server} for region {$region}-{$subRegion}. That region already has server {$this->regionToServerMap[$region][$subRegion]} assigned for it.");
                }
            }
        }
    }

    public function getGeoVideoLink($userIp, $protocol, $relativeMediaPath, $port) {
        if( NULL == $port ) {
            throw new Exception("Media server port must be specified.");
        }

        try {
            $record = $this->reader->city($userIp);
            $userRegion = $record->country->isoCode;
            $userSubRegion = $record->mostSpecificSubdivision->isoCode;
            if( NULL == $userSubRegion ) {
                $userSubRegion = 'unknown';
            }
        } catch (Exception $e) {
            $userRegion = 'unknown';
            $userSubRegion = 'unknown';
        }

        $dedicatedServer = NULL;
        if( isset($this->regionToServerMap[$userRegion]) ) {
            if( isset($this->regionToServerMap[$userRegion][$userSubRegion]) ) {
                $dedicatedServer = $this->regionToServerMap[$userRegion][$userSubRegion];
            } else if( isset($this->regionToServerMap[$userRegion]['default']) ) {
                $dedicatedServer = $this->regionToServerMap[$userRegion]['default'];
            }
        }

        if( NULL == $dedicatedServer ) {
            if( NULL == $this->defaultServer ) {
                throw new Exception("No server match for user's location. No default server is specified either.");
            } else {
                $dedicatedServer = $this->defaultServer;
            }
        }

        // just in case
        $relativeMediaPath = preg_replace( '{^https?://}', '/', $relativeMediaPath );
        $dedicatedServer = preg_replace( '{^https?://}', '', $dedicatedServer );
        $dedicatedServer = preg_replace( '{:\d*$}', '', $dedicatedServer );

        if( $this->startsWith($relativeMediaPath, $protocol.'://') ) {
            $relativeMediaPath = str_replace($protocol.'://', '/', $relativeMediaPath);
        }
        if( $this->startsWith($dedicatedServer, $protocol.'://' ) ) {
            $mediaPath = $dedicatedServer.':'.$port.$relativeMediaPath;
        } else {
            $mediaPath = $protocol.'://'.$dedicatedServer.':'.$port.$relativeMediaPath;
        }

        return $mediaPath;
    }

    private function startsWith($str, $sub) {
        if( strlen($sub) > strlen($str) ) {
            return false;
        }
        return (substr( $str, 0, strlen($sub) ) === $sub);
    }
}
?>