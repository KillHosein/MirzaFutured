<?php

class SmokeTest {
    private $appDir;
    private $errors = [];

    public function __construct($appDir) {
        $this->appDir = $appDir;
    }

    public function run() {
        echo "Running Smoke Tests...\n";
        echo "----------------------\n";

        $this->checkCriticalFiles();
        $this->checkDirectoryStructure();
        $this->checkIndexIntegrity();

        echo "----------------------\n";
        if (empty($this->errors)) {
            echo "✅ All tests passed!\n";
            exit(0);
        } else {
            echo "❌ Tests failed:\n";
            foreach ($this->errors as $error) {
                echo " - $error\n";
            }
            exit(1);
        }
    }

    private function checkCriticalFiles() {
        $files = [
            'index.php',
            '.htaccess',
            'static/js/main.js',
            'static/js/vendor.js',
            'static/css/style.css',
            'static/css/theme.css',
            'static/js/theme-loader.js'
        ];

        foreach ($files as $file) {
            if (!file_exists($this->appDir . '/' . $file)) {
                $this->errors[] = "Missing critical file: $file";
            } else {
                echo "✅ File exists: $file\n";
            }
        }
    }

    private function checkDirectoryStructure() {
        $dirs = [
            'static/js',
            'static/css',
            'static/fonts'
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($this->appDir . '/' . $dir)) {
                $this->errors[] = "Missing directory: $dir";
            } else {
                echo "✅ Directory exists: $dir\n";
            }
        }
    }

    private function checkIndexIntegrity() {
        $indexContent = file_get_contents($this->appDir . '/index.php');
        
        $requiredStrings = [
            'static/js/main.js',
            'static/css/style.css',
            'theme-loader.js',
            'dir="rtl"',
            'lang="fa"'
        ];

        foreach ($requiredStrings as $str) {
            if (strpos($indexContent, $str) === false) {
                $this->errors[] = "index.php missing required string: '$str'";
            }
        }
        echo "✅ index.php integrity check passed\n";
    }
}

// Run the test
$test = new SmokeTest(__DIR__ . '/../app');
$test->run();
