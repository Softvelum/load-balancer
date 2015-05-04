<?php
/*
  Created by WMSPanel team, https://wmspanel.com/
  Contact: support@wmspanel.com
*/

class Memcached {
    const OPT_LIBKETAMA_COMPATIBLE = true;
    const OPT_COMPRESSION = true;
    const OPT_NO_BLOCK = true;

    public $_instance;
    public function __construct() {
        $this->_instance = new Memcache();
    }

    public function set($key, $value, $expirationTime) {
        $this->_instance->set($key, $value, false, $expirationTime);
    }

    public function __call($name, $args) {
        return call_user_func_array(array($this->_instance, $name), $args);
    }

    public function setOption() {}
}
?>