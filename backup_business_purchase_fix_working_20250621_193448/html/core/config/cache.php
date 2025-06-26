<?php
// Cache Configuration
return [
    'enabled' => false, // DISABLED - Was causing navigation cache issues
    'driver' => 'file', // file, redis, or memcached
    'path' => __DIR__ . '/../../storage/cache',
    'prefix' => 'revenueqr_',
    'ttl' => 3600, // 1 hour default TTL
    
    // File cache settings
    'file' => [
        'path' => __DIR__ . '/../../storage/cache',
        'extension' => '.cache',
    ],
    
    // Redis settings (if using Redis)
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => null,
        'database' => 0,
    ],
    
    // Memcached settings (if using Memcached)
    'memcached' => [
        'host' => '127.0.0.1',
        'port' => 11211,
        'weight' => 100,
    ],
]; 