<?php
/*
  Created by WMSPanel team, https://wmspanel.com/
  Contact: support@wmspanel.com
*/

if( !class_exists('Memcached') && class_exists('Memcache') ) {
    require 'memcached_over_memcache.php';
}

class NimbleServer {
    public function __construct( $url ) {
        $url = rtrim( $url, '/' );
        $url = preg_replace( '{:\d*$}', '', $url );
        $url = preg_replace( '{^([^:/.]{2,6}://)}', '', $url );

        $this->url = $url;
        $this->password = NULL;
        $this->port = 8082;
    }

    public function getFullPath( $protocol, $relativeMediaPath, $port = NULL ) {
        if( '/' != $relativeMediaPath[0] ) {
            $relativeMediaPath = '/'.$relativeMediaPath;
        }
        if( NULL == $port ) {
            $port = $this->standardPortForProtocol( $protocol );
        }
        return ( $protocol . '://' . $this->url . ':' . $port . $relativeMediaPath );
    }

    public function setManagementPassword( $password ) {
        $this->password = $password;
    }

    public function setManagementPort( $port ) {
        $this->port = $port;
    }

    public function getStatus() {
        $apiUrl = 'http://'.$this->url.':'.$this->port.'/manage/server_status';
        if( NULL != $this->password ) {
            $salt = rand( 0, 1000000 );
            $str2hash = $salt."/".$this->password;
            $md5raw = md5( $str2hash, true );
            $base64hash = base64_encode( $md5raw );
            $apiUrl = $apiUrl.'?salt='.$salt.'&hash='.$base64hash;
        }

        $ch = curl_init();
        $options = [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_URL            => $apiUrl
        ];

        curl_setopt_array( $ch, $options );
        $curlData = curl_exec( $ch );

        $data = NULL;
        if( !curl_errno( $ch ) ) {
            $data = json_decode( curl_exec( $ch ), true );
            curl_close( $ch );
        }

        return $data;
    }

    public function id() {
        return $this->url.':'.$this->port;
    }

    private function standardPortForProtocol( $protocol ) {
        if( 'rtmp' == $protocol ) {
            $port = 1935;
        } else if( 'rtsp' == $protocol ) {
            $port = 554;
        } else {
            $port = 8081;
        }
        return $port;
    }
}

class MemcachedServer {
    public function __construct( $url = NULL, $port = NULL ) {
        if( NULL != $url ) {
            $this->memcached = new Memcached();
            $this->memcached->addServer( $url, $port );
            $this->expirationTime = 10;
        } else {
            $this->memcached = NULL;
        }
    }

    public function setExpirationTime( $time ) {
        $this->expirationTime = $time;
    }

    public function get( $key ) {
        $value = NULL;
        if( NULL != $this->memcached ) {
            $value = $this->memcached->get( $key );
        }
        return $value;
    }

    public function set( $key, $value ) {
        if( NULL != $this->memcached ) {
            $this->memcached->set( $key, $value, $this->expirationTime );
        }
    }
}

class NimbleLoadBalancer {

    public function __construct( $balancingCriteria = 'bandwidth' ) {
        $this->nimbleServers = array();
        $this->balancingCriteria = $balancingCriteria;
        $this->whenAllServersNotResponding = 'choose_random';
        $this->memcachedServer = new MemcachedServer();
    }

    public function setMemcachedServer( $url, $port = 11211 ) {
        $server = $this->memcachedServer = new MemcachedServer( $url, $port );
        $server->setExpirationTime( 10 );
        return $server;
    }

    public function setWhenAllServersNotResponding( $action ) {
        $this->whenAllServersNotResponding = $action;
    }

    public function addServer( $server ) {
        $this->nimbleServers[] = $server;
    }

    public function getServer( $customServers = NULL ) {
        $dedicatedServer = NULL;
        $servers = $this->nimbleServers;
        if( NULL != $customServers ) {
            $servers = $customServers;
        }
        if( empty( $servers ) ) {
            throw new Exception( "No Nimble servers specified!" );
        }

        $leastConnectionsServer = array( 'id' => NULL, 'value' => PHP_INT_MAX );
        $leastBandwidthServer   = array( 'id' => NULL, 'value' => PHP_INT_MAX );
        foreach( $servers as $idx => $server ) {
            $serverStatus = $this->memcachedServer->get( $server->id() );
            $updateMemcached = false;
            if( NULL == $serverStatus ) {
                $serverStatus = $server->getStatus();
                $updateMemcached = true;
            }
            if( NULL != $serverStatus && isset( $serverStatus['Connections'] ) && isset( $serverStatus['OutRate'] ) ) {
                if( $updateMemcached ) {
                    $this->memcachedServer->set( $server->id(), $serverStatus );
                }
                if( $leastConnectionsServer['value'] > $serverStatus['Connections'] ) {
                    $leastConnectionsServer['id'] = $idx;
                    $leastConnectionsServer['value'] = $serverStatus['Connections'];
                }
                if( $leastBandwidthServer['value'] > $serverStatus['OutRate'] ) {
                    $leastBandwidthServer['id'] = $idx;
                    $leastBandwidthServer['value'] = $serverStatus['OutRate'];
                }
            }
        }

        if( 'bandwidth' == $this->balancingCriteria ) {
            if( NULL !== $leastBandwidthServer['id'] ) {
                $dedicatedServer = $servers[ $leastBandwidthServer['id'] ];
            }
        } else {
            if( NULL !== $leastConnectionsServer['id'] ) {
                $dedicatedServer = $servers[ $leastConnectionsServer['id'] ];
            }
        }

        if( NULL == $dedicatedServer ) {
            if( 'choose_random' == $this->whenAllServersNotResponding ) {
                $dedicatedServer = $servers[ rand( 0, count( $servers ) - 1 ) ];
            } else {
                throw new Exception( "Unable to connect to any of specified Nimble servers." );
            }
        }

        return $dedicatedServer;
    }
}
?>