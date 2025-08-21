<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🚀 Setting up Customer and Shipment Management System...\n\n";

try {
    // Run database migrations
    echo "📊 Running database migrations...\n";
    $migrationOutput = shell_exec('cd ' . __DIR__ . ' && php artisan migrate --force 2>&1');
    echo $migrationOutput . "\n";

    // Seed sample data
    echo "🌱 Seeding sample data...\n";
    $seedOutput = shell_exec('cd ' . __DIR__ . ' && php artisan db:seed --class=SampleDataSeeder --force 2>&1');
    echo $seedOutput . "\n";

    // Test the new components
    echo "🔍 Testing Customer and Shipment data...\n";
    
    $customerCount = \App\Models\Customer::count();
    $shipmentCount = \App\Models\Shipment::count();
    $vesselCount = \App\Models\Vessel::count();
    
    echo "✅ Database setup completed successfully!\n\n";
    echo "📈 **Current Data Summary:**\n";
    echo "   👥 Customers: {$customerCount}\n";
    echo "   📦 Shipments: {$shipmentCount}\n";
    echo "   🚢 Vessels: {$vesselCount}\n\n";
    
    echo "🎯 **New Menu System Features:**\n";
    echo "   ✅ Customer Management - Create, edit, delete customers\n";
    echo "   ✅ Shipment Management - Track shipments from arrival to delivery\n";
    echo "   ✅ Automated shipment numbering (CSL format)\n";
    echo "   ✅ Status tracking with color-coded indicators\n";
    echo "   ✅ Search and filter functionality\n";
    echo "   ✅ Responsive modals for data entry\n\n";
    
    echo "🌐 **Available URLs:**\n";
    echo "   📊 Dashboard: http://localhost:8000/\n";
    echo "   👥 Customer Management: http://localhost:8000/customers\n";
    echo "   📦 Shipment Management: http://localhost:8000/shipments\n";
    echo "   🚢 Vessel Testing: http://localhost:8000/vessel-test\n\n";
    
    echo "🚀 **Ready to Use!**\n";
    echo "   Start your Laravel server: php artisan serve\n";
    echo "   Navigate to the new menu items to test customer and shipment creation.\n\n";
    
    // Sample customer data preview
    $sampleCustomer = \App\Models\Customer::first();
    if ($sampleCustomer) {
        echo "📋 **Sample Customer Created:**\n";
        echo "   Company: {$sampleCustomer->company}\n";
        echo "   Contact: {$sampleCustomer->name}\n";
        echo "   Email: {$sampleCustomer->email}\n";
        echo "   Active Shipments: " . $sampleCustomer->activeShipments()->count() . "\n\n";
    }
    
    // Sample shipment data preview
    $sampleShipment = \App\Models\Shipment::first();
    if ($sampleShipment) {
        echo "📦 **Sample Shipment Created:**\n";
        echo "   Shipment #: {$sampleShipment->shipment_number}\n";
        echo "   Customer: {$sampleShipment->customer->company}\n";
        echo "   Consignee: {$sampleShipment->consignee}\n";
        echo "   Status: {$sampleShipment->status}\n";
        echo "   Port: {$sampleShipment->port_of_discharge}\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error during setup: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and try again.\n";
}
