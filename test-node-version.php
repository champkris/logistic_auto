<?php

// Test Node.js version as seen by PHP process
echo "Testing Node.js version from PHP context:\n";
echo "==========================================\n\n";

// Test 1: which node
echo "1. Location of node binary:\n";
$which_node = shell_exec('which node 2>&1');
echo "   which node: " . trim($which_node) . "\n\n";

// Test 2: node version
echo "2. Node.js version:\n";
$node_version = shell_exec('node --version 2>&1');
echo "   node --version: " . trim($node_version) . "\n\n";

// Test 3: Full path node version
echo "3. Node.js version using full path:\n";
$full_path_version = shell_exec('/usr/local/bin/node --version 2>&1');
echo "   /usr/local/bin/node --version: " . trim($full_path_version) . "\n\n";

// Test 4: PATH environment variable
echo "4. PATH environment variable:\n";
$path = shell_exec('echo $PATH 2>&1');
echo "   PATH: " . trim($path) . "\n\n";

// Test 5: Test nullish coalescing operator
echo "5. Testing nullish coalescing operator (??=):\n";
$test_script = <<<'JS'
let x;
x ??= 5;
console.log('Nullish coalescing works! x = ' + x);
JS;

file_put_contents('/tmp/test-nullish.js', $test_script);
$nullish_test = shell_exec('node /tmp/test-nullish.js 2>&1');
echo "   Result: " . trim($nullish_test) . "\n\n";

// Test 6: Check if running through web server vs CLI
echo "6. PHP SAPI:\n";
echo "   SAPI: " . PHP_SAPI . "\n\n";

// Test 7: Check all node installations
echo "7. All node installations found:\n";
$all_nodes = shell_exec('find /usr -name node -type f 2>/dev/null | head -20');
echo $all_nodes . "\n";

// Test 8: Check nvm installations
echo "8. NVM installations:\n";
$nvm_check = shell_exec('ls -la ~/.nvm/versions/node/ 2>&1 | head -20');
echo $nvm_check . "\n";

// Clean up
@unlink('/tmp/test-nullish.js');