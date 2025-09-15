# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 12.x logistics automation system for Eastern Air (CS Shipping LCB) that automates vessel tracking, shipment management, and customer notifications. The system integrates with multiple port terminals using browser automation.

## Key Commands

### Development Server
```bash
# Start development servers (Laravel + Vite)
php artisan serve     # Laravel server at http://localhost:8000
npm run dev          # Vite frontend compilation

# Alternative: Run all services concurrently
composer run dev     # Runs server, queue, logs, and vite together
```

### Testing & Quality
```bash
# Run tests
php artisan test

# Code formatting (Laravel Pint)
./vendor/bin/pint

# Database operations
php artisan migrate:fresh --seed    # Reset database with sample data
php artisan db:seed --class=SampleDataSeeder

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### Browser Automation Testing
```bash
# Test vessel tracking for different terminals
cd browser-automation
node vessel-scraper.js "VESSEL NAME"
node lcb1-wrapper.js "VESSEL NAME"
node shipmentlink-wrapper.js "VESSEL NAME"
```

## Architecture Overview

### Core Structure
- **Laravel + Livewire**: Full-stack framework with reactive components
- **Database**: SQLite (dev) with migrations for vessels, shipments, customers, documents
- **Frontend**: TailwindCSS + Alpine.js for UI interactions
- **Browser Automation**: Playwright-based scrapers for port terminal integration

### Key Services & Components

#### Livewire Components (`app/Livewire/`)
- `CustomerManager.php` - CRUD operations for customer management
- `ShipmentManager.php` - Shipment tracking and document management
- `Dashboard.php` - Real-time statistics and overview

#### Vessel Tracking (`app/Services/VesselTrackingService.php`)
Integrates with three port terminals:
- Hutchison Ports (Laem Chabang)
- ShipmentLink (Terminal B2)
- LCB1 Terminal

Each terminal has a dedicated scraper in `browser-automation/scrapers/`:
- `everbuild-scraper.js` - Hutchison Ports
- `shipmentlink-scraper.js` - ShipmentLink
- `lcb1-scraper.js` - LCB1

### Database Models (`app/Models/`)
- `Customer` - Company information and notification preferences
- `Vessel` - Vessel details with terminal codes
- `Shipment` - Complete shipment lifecycle tracking
- `Document` - File management for D/O, customs, permits

### Authentication
Custom authentication system with Eastern Air branding:
- User seeder: `database/seeders/EasternAirUserSeeder.php`
- Auth views: `resources/views/auth/`
- Session-based authentication with database driver

### Key Routes
- `/` - Dashboard
- `/customers` - Customer management
- `/shipments` - Shipment tracking
- `/vessel-test` - Vessel tracking test interface
- API routes in `routes/api.php` for automation endpoints

## Important Considerations

### Browser Automation
- Uses Playwright for web scraping port terminals
- Handles cookie consent, dynamic dropdowns, and form submissions
- Implements retry logic and error distinction (no data vs. system error)
- Screenshots saved for debugging failed attempts

### Environment Configuration
- Database: SQLite by default (`.env` DB_CONNECTION=sqlite)
- Queue: Database driver for background jobs
- Mail: Log driver for development
- Session: Database driver

### Frontend Build
- Vite for asset compilation
- TailwindCSS for styling
- Alpine.js for reactive UI components
- Livewire for server-side rendering with reactivity

### Error Handling
- Vessel tracking distinguishes between "no data found" and actual errors
- Browser automation includes screenshot capture on failures
- Comprehensive logging throughout the application

## Recent Updates
- Eastern Air branding integration with company logo
- Vessel code field added to shipments
- Enhanced vessel tracking with terminal-specific scrapers
- Authentication system with Eastern Air user accounts