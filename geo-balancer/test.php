<?php
/*
  Created by WMSPanel team, https://wmspanel.com/
  Contact: support@wmspanel.com
*/

/*
  In order to have geo balancing code working, you need to place the following 
  3 files in same directory:
  1. geo_lb.php (included in this release)
  2. geoip2.phar (latest version can be downloaded at 
    https://github.com/maxmind/GeoIP2-php/releases)
  3. GeoLite2-City.mmdb (latest version can be downloaded at 
    http://dev.maxmind.com/geoip/geoip2/geolite2/ - 
    please notice the word "City" when choosing appropriate database)
  When you have the above 3 files placed in one directory, set the proper path 
  to the 'geo_lb.php' below.
  By default, it's set as if above 3 files are in the same directory 
  with the current file.
*/
require 'geo_lb.php';

/* 
  GeoLoadBalancer class determines user's location country by IP address 
  and translates general media reference to appropriate media server 
  according to predefined balancing configuration. 
  GeoLoadBalancer constructor accepts path to geoip2 city database 
  as optional parameter. By default it's set as GeoLite2-City.mmdb. 
  If you want to set custom path to the database, 
  your code would look like the following:
  $balancer = new GeoLoadBalancer('/usr/local/share/geoip/GeoLite2-City.mmdb');
*/
$balancer = new GeoLoadBalancer();

/* 
  Set default server URL which is used when user's location 
  doesn't exist in balancing configuration.
  It is highly recommended to set the default server URL 
  in order to have fallback for unexpected locations.
*/
$balancer->setDefaultServer('usa.example.com');

/*
  Describe balancing configuration with setRegionServer method. 
  Each media server URL should be assigned to specific region or set of regions. 
  Only one server can be specified for a region. But several regions can point 
  to one server. Each region is presented in two-character ISO 3166-1 country code.
  For example, US is for United States, FR is for France, 
  GB is for United Kingdom of Great Britain and Northern Ireland.
  Please, see http://en.wikipedia.org/wiki/ISO_3166-1 for further reference.
  You can also specify sub-regions in ISO 3166-2 format. 
  It consists of 2 parts divided by hyphen sign:
  1. The first part is ISO 3166-1 code of the country 
    (consisting of 2 alphanumeric characters)
  2. The second part is a string of up to three alphanumeric characters 
    representing code of sub-region.
    For example, that can be 'US-CA' for the state California of the United States, 
    'RU-PRI' for Primorskiy kraj of Russia or 
    'GB-BKM' for Buckinghamshire of United Kingdom.
  Please, refer to https://en.wikipedia.org/wiki/ISO_3166-2 for sub-region format.
*/
$balancer->setRegionServer(['JP','KR'], 'asia.example.com');
$balancer->setRegionServer(['FR','GB','IT'], 'europe.example.com' );
$balancer->setRegionServer(['US-CA', 'US-NV', 'US-OR', 'US-WA'], 'east.usa.example.com');
$balancer->setRegionServer(['US-MT', 'US-ND', 'US-MN', 'CA'], 'north.usa.example.com');

/*
  This method is convenient to configure balancing between sub-regions 
  of particular country. It has the same name as the method above, 
  but it takes 3 parameters instead of 2. 
  1. The first parameter is 2 letters country code. 
  2. The second parameter is list of sub-regions in ISO 3166-2 format. 
    Sub-regions can be specified either in short form 
    (for example, IL for state Illinois) or long form 
    (for example, US-IL for state Illinois),
    but in case of long form, first 2 letters of country code must be equal 
    to the first parameter, e.g. all sub-regions must correspond only to the country 
    specified in the first parameter.
*/
$balancer->setRegionServer('US', ['NE', 'KS', 'OK', 'CO'], 'central.usa.example.com');

/*
  Determine user's IP address. $_SERVER['REMOTE_ADDR'] contains the real IP address 
  of the connecting party. That is the most reliable value, that can be found. 
  However, user can be behind a proxy, and in that case
  $_SERVER['REMOTE_ADDR'] actually contains that proxy address. 
  The proxy may have set the $_SERVER['HTTP_X_FORWARDED_FOR'], but this value 
  is easily spoofed. For example, it can be set by someone
  without a proxy, or the IP can be an internal IP from the LAN behind the proxy.
*/
$userIp = $_SERVER['REMOTE_ADDR'];

/*
  Generate final media reference based on user's IP address and 
  location balancing configuration. getGeoVideoLink method takes user's IP, 
  protocol, port, and general media reference as parameters and produces media reference,
  pointing to appropriate media server with specified protocol on specified port.
*/
$link1 = $balancer->getGeoVideoLink($userIp, 'https', '/vod/sample.mp4/playlist.m3u8', 443);

// Get appropriate media references for given IP addresses (for testing purposes).
$link2 = $balancer->getGeoVideoLink('1.201.255.255', 'rtsp', '/live/channel/', 554);
$link3 = $balancer->getGeoVideoLink('199.193.156.29', 'rtmp', '/live/stream/', 1935);
$link4 = $balancer->getGeoVideoLink('123.123.123.123', 'http', '/live/channel/playlist.m3u8', 8081);

print($link1."<br/>".$link2."<br/>".$link3."<br/>".$link4);
?>