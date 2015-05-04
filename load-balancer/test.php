<?php
/*
  Created by WMSPanel team, https://wmspanel.com/
  Contact: support@wmspanel.com
*/

/*
  Nimble Streamer load balancing logic is implemented in two classes:
  NimbleLoadBalancer and NimbleServer, those are defined in 'nimble_lb.php' file.
  Besides that, two php extensions are used: cURL and Memcached.

  cURL extension is mandatory. It's used for performing requests to Nimble servers API.
  Memcached extension is optional. It's tied with Memcached server, which must be
  installed in your web environment. It's intended to reduce amount of external
  network requests to Nimble servers by caching them for a configurable period of time.
  Therefore, it is really helpful when requests for media link occur frequently.
  By default, Memcached extension isn't used, so it should be set explicitly
  by setMemcachedServer interface of NimbleLoadBalancer. Also, if you don't have
  Memcached extension, but you have Memcache extension (notice 'd' at the end) on your
  web server, then it will be used transparently through wrapper class defined in
  'memcached_over_memcache.php' file. Notice, however, that Memcache is older extension
  and is treated as deprecated now. Memcached extension is slightly faster than
  Memcache extension (about 10%) and has richer functionality.

  If none of Memcache or Memcached extensions is installed, then error appears on attempt
  to call SetMemcachedServer interface of NimbleLoadBalancer. NimbleLoadBalancer can be
  used without caching functionality, but all Nimble servers will be requested by network
  API each time getServer interface of NimbleLoadBalancer is invoked.

  Load balancing is set up as following:

  1. Create NimbleLoadBalancer class instance. Balancing criteria can be specified as
     parameter to the class constructor. By default, balancing criteria is set as
     'bandwidth', e.g. when media link is requested, the balancer chooses first server
     with the least outgoing bandwidth (the least amount of outgoing data). Another option
     for balancing criteria can be specified as 'connections', which means that
     the balancer chooses first server with the least number of clients connected.

  2. Add Nimble servers. Each server is specified by IP address. NimbleServer class provides
     interfaces for setting management token (password) and management port for server.

  3. (optional) Set up Memcached server and expiration time for cached entries. By default,
     this time is equal to 10 seconds.

  4. (optional) Set the balancer behavior, when all specified Nimble servers don't respond to
     the API calls. By default, it's set to 'choose_random', which means, that the balancer
     chooses random server in such case. Another option can be specified as 'throw', which
     means that the balancer throws an exception in such case.

  After the load balancing is set up, you can invoke getServer interface in order to get
  the least loaded Nimble server. Notice, that if particular server doesn't respond
  to the API request, then this server is ignored and can never be chosen by the balancer,
  except the case, when all servers don't respond and the balancer chooses random server.
*/
require 'nimble_lb.php';

/*
  Create new NimbleLoadBalancer instance with default balancing criteria 'bandwidth'.
  In order to set balancing criteria to 'connections', the balancer class constructor
  should be invoked with that parameter, e.g. new NimbleLoadBalancer('connections').
 */
$balancer = new NimbleLoadBalancer();
/*
  Set the balancer behavior, when all specified Nimble servers don't respond to the API
  calls, to throwing exception. If you want the balancer to choose random server in such
  case, just remove this line.
 */
$balancer->setWhenAllServersNotResponding( 'throw' );

/*
  Set up memcached server and data expiration time for cached requests. Memcached server
  is specified by url and port. If port isn't specified, then by default it's set to 11211.
  Default value for cache expiration time is 10 seconds, so you can omit setExpirationTime
  call, if this value is good for you.
 */
$memcachedServer = $balancer->setMemcachedServer( 'localhost' );
$memcachedServer->setExpirationTime( 5 );

$footballServers = array();
/*
  Create NimbleServer instance and add it to the balancer. Each server is specified
  by IP address. Management token (password) is optional parameter, but it's strongly
  recommended to use it on NImble server, because otherwise everyone can access API of
  that server. If particular Nimble server has default management port (8082), then no
  need to specify it explicitly. Otherwise, please use setManagementPassword interface.
 */
$server = new NimbleServer( '46.101.144.160' );
$server->setManagementPassword( 'password_1' );
$balancer->addServer( $server );

$footballServers[] = $server;

$server = new NimbleServer( '46.101.144.161' );
$server->setManagementPassword( 'password_2' );
$server->setManagementPort( 8086 );
$balancer->addServer( $server );

$footballServers[] = $server;

$server = new NimbleServer( '46.101.144.191' );
$server->setManagementPassword( 'password_3' );
$server->setManagementPort( 8083 );
$balancer->addServer( $server );

/*
  Invoke getServer interface in order to get the least loaded Nimble server. getServer
  receives array of NimbleServer objects as optional parameter. If this parameter is
  specified, then balancing is performed among specified servers only. Otherwise, the
  balancing is performed among all servers added to the balancer. This functionality
  is necessary for cases, when only part of all servers can stream particular media
  and balancing should be performed only among those servers.

  NimbleServer class provides getFullPath interface in order to generate full media
  path to given server. Two parameters are mandatory: protocol and relative media path.
  Third parameter - port - is optional. If not specified, then standard port is looked
  up from internal table by the protocol. If not found, then 8081 port is applied.
 */
$server1 = $balancer->getServer( $footballServers );
$link1 = $server1->getFullPath( 'rtmp', '/live/football/' );

$server2 = $balancer->getServer();
$link2 = $server2->getFullPath( 'rtmp', '/live/stream/', 1936 );

$server3 = $balancer->getServer();
$link3 = $server3->getFullPath( 'http', '/vod/sample.mp4/playlist.m3u8' );

print($link1."<br/>".$link2."<br/>".$link3);
?>