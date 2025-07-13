<?php
/**
 * Proxy Configuration Manager
 * Handles proxy settings and rotation for SMTP connections
 */

require_once __DIR__ . '/../config/config.php';

class ProxyManager {
    private $proxies = [];
    private $currentProxy = null;

    public function __construct() {
        $this->loadProxies();
    }

    public function isEnabled() {
        return PROXY_ENABLED && FEATURE_PROXY_SUPPORT;
    }

    public function getProxy() {
        if (!$this->isEnabled() || empty($this->proxies)) {
            return null;
        }

        if (PROXY_ROTATION) {
            return $this->getRandomProxy();
        }

        return $this->getFirstAvailableProxy();
    }

    private function loadProxies() {
        if (!file_exists(PROXY_FILE)) {
            $this->createDefaultProxyFile();
            return;
        }

        $lines = file(PROXY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (empty($line) || strpos($line, '#') === 0) {
                continue; // Skip empty lines and comments
            }

            $parts = str_getcsv($line);
            if (count($parts) >= 2) {
                $this->proxies[] = [
                    'host' => trim($parts[0]),
                    'port' => (int)trim($parts[1]),
                    'username' => isset($parts[2]) ? trim($parts[2]) : null,
                    'password' => isset($parts[3]) ? trim($parts[3]) : null,
                    'type' => isset($parts[4]) ? strtolower(trim($parts[4])) : 'http'
                ];
            }
        }
    }

    private function createDefaultProxyFile() {
        $defaultContent = <<<EOF
# Proxy Configuration File
# Format: host,port,username,password,type
# Type can be: http, https, socks5
# Lines starting with # are comments
#
# Examples:
# proxy1.example.com,8080,user,pass,http
# proxy2.example.com,1080,,,socks5
# 192.168.1.100,3128,username,password,https
#
# Add your proxy servers below:

EOF;

        if (!is_dir(dirname(PROXY_FILE))) {
            mkdir(dirname(PROXY_FILE), 0755, true);
        }

        file_put_contents(PROXY_FILE, $defaultContent);
    }

    private function getRandomProxy() {
        if (empty($this->proxies)) {
            return null;
        }

        return $this->proxies[array_rand($this->proxies)];
    }

    private function getFirstAvailableProxy() {
        foreach ($this->proxies as $proxy) {
            if ($this->testProxy($proxy)) {
                return $proxy;
            }
        }

        return null;
    }

    private function testProxy($proxy) {
        $context = stream_context_create([
            'http' => [
                'proxy' => "tcp://{$proxy['host']}:{$proxy['port']}",
                'request_fulluri' => true,
                'timeout' => PROXY_TIMEOUT
            ]
        ]);

        // Test with a simple HTTP request
        $result = @file_get_contents('http://httpbin.org/ip', false, $context);
        return $result !== false;
    }

    public function getProxyStats() {
        return [
            'enabled' => $this->isEnabled(),
            'count' => count($this->proxies),
            'rotation' => PROXY_ROTATION,
            'timeout' => PROXY_TIMEOUT
        ];
    }

    public function addProxy($host, $port, $username = null, $password = null, $type = 'http') {
        $newProxy = compact('host', 'port', 'username', 'password', 'type');
        
        // Validate proxy
        if ($this->testProxy($newProxy)) {
            $this->proxies[] = $newProxy;
            $this->saveProxies();
            return true;
        }

        return false;
    }

    private function saveProxies() {
        $content = "# Proxy Configuration File\n";
        $content .= "# Format: host,port,username,password,type\n\n";

        foreach ($this->proxies as $proxy) {
            $line = sprintf(
                "%s,%d,%s,%s,%s\n",
                $proxy['host'],
                $proxy['port'],
                $proxy['username'] ?? '',
                $proxy['password'] ?? '',
                $proxy['type']
            );
            $content .= $line;
        }

        file_put_contents(PROXY_FILE, $content);
    }
}
