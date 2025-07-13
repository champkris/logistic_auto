# Laravel Logistics Automation - Setup Guide

## 1. Project Initialization

### Create Laravel Project
```bash
cd /Users/apichakriskalambasuta/Sites/localhost/
composer create-project laravel/laravel logistic_auto
cd logistic_auto
```

### Environment Setup
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Database configuration in .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=logistic_auto
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Mail configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password

# Queue configuration
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```
## 2. Required Packages Installation

```bash
# Development tools
composer require laravel/breeze --dev
composer require livewire/livewire
composer require filament/filament

# Additional packages
composer require guzzlehttp/guzzle  # For API calls to port websites
composer require maatwebsite/excel  # Excel handling
composer require barryvdh/laravel-dompdf  # PDF generation
composer require pusher/pusher-php-server  # Real-time notifications

# Frontend
npm install
npm install alpinejs
npm install chart.js
```

## 3. Database Schema Design

### Core Tables Structure

#### Shipments Table
```php
Schema::create('shipments', function (Blueprint $table) {
    $table->id();
    $table->string('shipment_number')->unique();
    $table->string('consignee');
    $table->string('hbl_number')->nullable();
    $table->string('mbl_number')->nullable();
    $table->string('invoice_number')->nullable();
    $table->foreignId('vessel_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->string('port_of_discharge');
    $table->enum('status', ['new', 'planning', 'documents_ready', 'customs_clearance', 'delivery', 'completed']);
    $table->date('delivery_date')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```
#### Vessels Table
```php
Schema::create('vessels', function (Blueprint $table) {
    $table->id();
    $table->string('vessel_name');
    $table->string('voyage_number')->nullable();
    $table->datetime('eta')->nullable();
    $table->datetime('actual_arrival')->nullable();
    $table->string('port');
    $table->enum('status', ['scheduled', 'arrived', 'departed']);
    $table->timestamps();
});
```

#### Documents Table
```php
Schema::create('documents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shipment_id')->constrained();
    $table->enum('type', ['do', 'customs_declaration', 'permit', 'mill_test', 'other']);
    $table->string('document_name');
    $table->string('file_path')->nullable();
    $table->enum('status', ['pending', 'received', 'approved', 'rejected']);
    $table->decimal('cost', 10, 2)->nullable();
    $table->date('due_date')->nullable();
    $table->timestamps();
});
```

#### Customers Table
```php
Schema::create('customers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('company');
    $table->string('email');
    $table->string('phone')->nullable();
    $table->text('address')->nullable();
    $table->json('notification_preferences')->nullable();
    $table->timestamps();
});
```
## 4. Models with Relationships

### Shipment Model
```php
class Shipment extends Model
{
    protected $fillable = [
        'shipment_number', 'consignee', 'hbl_number', 'mbl_number',
        'invoice_number', 'vessel_id', 'customer_id', 'port_of_discharge',
        'status', 'delivery_date', 'notes'
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
```

## 5. Initial Features Implementation Priority

### Phase 1: Basic CRUD
- [x] Shipment management
- [x] Customer management
- [x] Vessel tracking
- [x] Document upload

### Phase 2: Automation
- [x] Daily vessel status checker
- [x] Customer email notifications
- [x] Document deadline reminders

### Phase 3: Advanced Features
- [x] Dashboard with charts
- [x] Planning calendar
- [x] Integration with port APIs

Ready to start building! 🚀