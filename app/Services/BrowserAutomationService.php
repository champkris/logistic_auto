<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BrowserAutomationService
{
    /**
     * Find the correct Node.js binary path for browser automation
     * This helps resolve Node.js version mismatches between terminal and PHP execution
     */
    public static function getNodePath()
    {
        // Check for environment variable first
        $envNodePath = env('NODE_BINARY_PATH');
        if ($envNodePath && file_exists($envNodePath)) {
            return $envNodePath;
        }

        // Common Node.js installation paths to check
        $possiblePaths = [
            '/usr/local/bin/node',         // Common on Linux/Mac
            '/usr/bin/node',                // Alternative Linux path
            '/opt/homebrew/bin/node',       // Homebrew on Apple Silicon
            '/opt/homebrew/opt/node@18/bin/node', // Homebrew versioned Node
            '/opt/homebrew/opt/node@20/bin/node', // Homebrew Node 20
            '/usr/local/n/versions/node/latest/bin/node', // n version manager
            '/home/deploy/.nvm/versions/node/latest/bin/node', // nvm on Linux
            'node' // Fallback to PATH
        ];

        // Test each path to find a working Node.js binary
        foreach ($possiblePaths as $path) {
            if ($path === 'node' || file_exists($path)) {
                $testCommand = escapeshellarg($path) . ' --version 2>&1';
                $output = shell_exec($testCommand);

                // Check if we got a valid version output
                if ($output && strpos($output, 'v') === 0) {
                    // Validate it supports modern syntax (Node 15+)
                    $version = trim($output);
                    if (preg_match('/v(\d+)\./', $version, $matches)) {
                        $majorVersion = intval($matches[1]);
                        if ($majorVersion >= 15) {
                            Log::info("Using Node.js binary", [
                                'path' => $path,
                                'version' => $version
                            ]);
                            return $path;
                        } else {
                            Log::warning("Node.js version too old", [
                                'path' => $path,
                                'version' => $version,
                                'required' => 'v15+'
                            ]);
                        }
                    }
                }
            }
        }

        // If no suitable Node.js found, log error and fallback
        Log::error("Could not find suitable Node.js binary (v15+). Browser automation may fail.");
        return 'node'; // Fallback to PATH
    }

    /**
     * Execute a Node.js script with proper error handling
     */
    public static function runNodeScript($scriptPath, $args = [], $timeout = 90)
    {
        $nodePath = self::getNodePath();

        // Build the command
        $command = escapeshellarg($nodePath) . ' ' . escapeshellarg($scriptPath);

        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        Log::info('Running browser automation', [
            'node_path' => $nodePath,
            'script' => basename($scriptPath),
            'command' => $command
        ]);

        // Use proc_open for better control
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        // Add timeout to the command
        if ($timeout > 0) {
            $command = "timeout {$timeout} " . $command;
        }

        $process = proc_open($command, $descriptors, $pipes);

        if (is_resource($process)) {
            fclose($pipes[0]); // Close stdin

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);

            // Log any stderr output for debugging
            if (!empty($stderr)) {
                Log::info("Browser automation stderr", ['output' => $stderr]);
            }

            if ($returnCode !== 0 && empty($stdout)) {
                throw new \Exception("Browser automation failed (exit code: {$returnCode}): " . $stderr);
            }

            return [
                'stdout' => $stdout,
                'stderr' => $stderr,
                'return_code' => $returnCode
            ];
        }

        throw new \Exception("Failed to start browser automation process");
    }
}