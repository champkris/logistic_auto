<?php
/**
 * GitHub Webhook Auto-Deploy Script for CloudPanel
 * 
 * Upload this file to your web root via CloudPanel File Manager
 * Then configure GitHub webhook to: https://vessel.easternair.co.th/deploy.php
 * 
 * Security: Change the secret below and use the same in GitHub webhook settings
 */

// Configuration
define('SECRET', 'your_webhook_secret_here_change_this'); // Change this!
define('REPO_PATH', '/home/champkris/htdocs/vessel.easternair.co.th');
define('GIT_REPO', 'https://github.com/champkris/logistic_auto.git');
define('BRANCH', 'master');
define('LOG_FILE', __DIR__ . '/deploy.log');

// Verify webhook secret
$headers = getallheaders();
$signature = $headers['X-Hub-Signature-256'] ?? '';

if (empty($signature)) {
    http_response_code(401);
    die('No signature provided');
}

$payload = file_get_contents('php://input');
$calculated_signature = 'sha256=' . hash_hmac('sha256', $payload, SECRET);

if (!hash_equals($signature, $calculated_signature)) {
    http_response_code(401);
    die('Invalid signature');
}

// Log function
function log_message($message) {
    file_put_contents(LOG_FILE, date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
}

// Start deployment
log_message('=== Deployment Started ===');

try {
    // Change to repository directory
    chdir(REPO_PATH);
    
    // Pull latest changes
    exec('git pull origin ' . BRANCH . ' 2>&1', $output, $return);
    log_message('Git Pull: ' . implode("\n", $output));
    
    if ($return !== 0) {
        throw new Exception('Git pull failed');
    }
    
    // Install Composer dependencies
    exec('composer install --no-dev --optimize-autoloader 2>&1', $output, $return);
    log_message('Composer: ' . implode("\n", $output));
    
    // Build assets
    exec('npm install && npm run build 2>&1', $output, $return);
    log_message('NPM Build: ' . implode("\n", $output));
    
    // Run migrations
    exec('php artisan migrate --force 2>&1', $output, $return);
    log_message('Migrations: ' . implode("\n", $output));
    
    // Clear and cache
    exec('php artisan config:cache 2>&1', $output);
    exec('php artisan route:cache 2>&1', $output);
    exec('php artisan view:cache 2>&1', $output);
    log_message('Cache cleared and rebuilt');
    
    log_message('=== Deployment Successful ===');
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Deployment completed successfully',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    log_message('ERROR: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
