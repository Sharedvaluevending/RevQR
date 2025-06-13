<?php
class Cache {
    private static $instance = null;
    private $config;
    private $driver;
    
    private function __construct() {
        $this->config = require __DIR__ . '/config/cache.php';
        $this->initializeDriver();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initializeDriver() {
        if (!$this->config['enabled']) {
            return;
        }
        
        switch ($this->config['driver']) {
            case 'file':
                $this->driver = new FileCache($this->config['file']);
                break;
            case 'redis':
                $this->driver = new RedisCache($this->config['redis']);
                break;
            case 'memcached':
                $this->driver = new MemcachedCache($this->config['memcached']);
                break;
            default:
                throw new Exception('Invalid cache driver specified');
        }
    }
    
    public function get($key, $default = null) {
        if (!$this->config['enabled']) {
            return $default;
        }
        return $this->driver->get($this->config['prefix'] . $key, $default);
    }
    
    public function set($key, $value, $ttl = null) {
        if (!$this->config['enabled']) {
            return false;
        }
        return $this->driver->set(
            $this->config['prefix'] . $key,
            $value,
            $ttl ?? $this->config['ttl']
        );
    }
    
    public function delete($key) {
        if (!$this->config['enabled']) {
            return false;
        }
        return $this->driver->delete($this->config['prefix'] . $key);
    }
    
    public function clear() {
        if (!$this->config['enabled']) {
            return false;
        }
        return $this->driver->clear();
    }
}

class FileCache {
    private $path;
    private $extension;
    
    public function __construct($config) {
        $this->path = $config['path'];
        $this->extension = $config['extension'];
        
        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }
    }
    
    public function get($key, $default = null) {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return $default;
        }
        
        $data = unserialize(file_get_contents($file));
        if ($data['expires'] && $data['expires'] < time()) {
            unlink($file);
            return $default;
        }
        
        return $data['value'];
    }
    
    public function set($key, $value, $ttl) {
        $file = $this->getFilePath($key);
        $data = [
            'value' => $value,
            'expires' => $ttl ? time() + $ttl : null
        ];
        
        return file_put_contents($file, serialize($data)) !== false;
    }
    
    public function delete($key) {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }
    
    public function clear() {
        $files = glob($this->path . '/*' . $this->extension);
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    private function getFilePath($key) {
        return $this->path . '/' . md5($key) . $this->extension;
    }
} 