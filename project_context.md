# Logistics Automation Project - CS Shipping LCB

## Project Overview
**Location**: `/Users/apichakriskalambasuta/Sites/localhost/logistic_auto`  
**Framework**: Laravel (PHP)  
**Purpose**: Automate CS Shipping LCB operations workflow

## Current CS Shipping LCB Workflow Analysis

### 1. งานรับและวางแผน (Job Intake & Planning)
- รับ New shipment จาก CS BKK หรือ CS Shipping LCB
- เช็คข้อมูลเรือเข้าท่าจากเว็บท่าเรือ
- ลงแพลน Excel สำหรับอัพเดตลูกค้า
- ทำใบส่งของแนบกับ BL

### 2. การติดตามและอัพเดต (Tracking & Updates)
- เช็คสถานะเรือเข้าและอัพเดตลูกค้าทุกเช้า
- รายละเอียดที่ต้องอัพเดต: Consignee, HB/L, MB/L, INV., Vessel+ETA, PORT, D/O Status

### 3. การวางแผนส่งของ (Delivery Planning)
- ตกลงวันส่งของกับลูกค้า (ล่วงหน้า 1-2 วัน)
- ลงแพลนกลางเพื่อแจ้งทีม Shipping และ Transport

### 4. การเตรียมเอกสาร (Document Preparation)
**D/O Process:**
- CS ติดตามค่าใช้จ่ายค่าแลก D/O
- ทีม Operation จ่ายเงิน
- เช็ครับกับปลายทาง
- Messenger รับ D/O

**ใบขน Process:**
- รับจากทีม CS Shipping BKK
- เช็คเรือคอนเฟิร์มกับกรมศุลก์
- ตรวจสอบเอกสารลูกค้า
- Draft → Confirm → ปริ้นชุดงาน

**ใบอนุญาต & เอกสารอื่นๆ:**
- MILL TEST, บัตรส่งเสริม, FORM ต่างๆ

### 5. การตรวจปล่อย (Clearance Process)
- รวมเอกสารให้ครบ
- เช็คตู้และสถานะเรือ
- ส่งให้ทีม Shipping

### 6. การส่งมอบ (Final Delivery)
- ติดตามการ์ดรับสินค้าจาก Shipping
- ส่งข้อมูลให้ทีม Transport
- อัพเดตลูกค้าจนส่งเสร็จถึงโรงงาน

## Automation Opportunities (Priority Order)

### High Priority - Quick Wins
1. **Vessel Tracking Automation**
   - เช็คสถานะเรือจากเว็บท่าเรืออัตโนมัติ
   - ส่งอีเมลอัพเดตลูกค้าทุกเช้า

2. **Document Status Tracking**
   - ติดตามสถานะ D/O และใบขน
   - แจ้งเตือนกำหนดเวลาสำคัญ

3. **Central Planning Dashboard**
   - แดชบอร์ดแสดงสถานะทุก shipment
   - แจ้งเตือนทีม Shipping และ Transport

### Medium Priority
4. **Digital Document Workflow**
   - อัพโหลดและจัดการเอกสารแบบ digital
   - ตรวจสอบเอกสารครบถ้วน

5. **Customer Communication Automation**
   - ส่งอัพเดตอัตโนมัติตามเหตุการณ์
   - ระบบแจ้งเตือนล่วงหน้า

### Long-term Goals
6. **Integration with External Systems**
   - เชื่อมต่อกับระบบท่าเรือ
   - เชื่อมต่อกับระบบกรมศุลก์

## Technology Stack (Laravel-based)

### Backend
- **Framework**: Laravel 10.x
- **Database**: MySQL/PostgreSQL
- **Queue**: Redis + Laravel Horizon
- **Cache**: Redis
- **File Storage**: Laravel Storage (S3 compatible)

### Frontend
- **Blade Templates** with **Livewire** for reactive components
- **Alpine.js** for frontend interactions
- **TailwindCSS** for styling
- **Chart.js** for dashboards

### Additional Tools
- **Laravel Sanctum** for API authentication
- **Laravel Mail** for email automation
- **Laravel Scheduler** for CRON jobs
- **Laravel Scout** for search functionality

## Project Structure (Laravel)
```
logistic_auto/
├── app/
│   ├── Models/
│   │   ├── Shipment.php
│   │   ├── Vessel.php
│   │   ├── Document.php
│   │   └── Customer.php
│   ├── Http/Controllers/
│   │   ├── ShipmentController.php
│   │   ├── VesselTrackingController.php
│   │   └── DocumentController.php
│   ├── Jobs/
│   │   ├── CheckVesselStatus.php
│   │   └── SendCustomerUpdate.php
│   └── Services/
│       ├── VesselTrackingService.php
│       └── DocumentService.php
├── database/migrations/
├── resources/views/
├── routes/
└── config/
```

## Next Steps
1. Set up Laravel project structure
2. Design database schema for shipments, vessels, documents
3. Create basic CRUD for shipment management
4. Implement vessel tracking automation
5. Build customer notification system

## Key Business Rules to Remember
- ต้องอัพเดตลูกค้าทุกเช้า
- เตรียมเอกสารล่วงหน้า 1-2 วัน
- D/O ต้องได้ก่อนเรือเข้า 1 วัน
- ตรวจสอบเอกสารครบถ้วนก่อนตรวจปล่อย
- ติดตามจนส่งของถึงโรงงานลูกค้า

---
*Last updated: July 2025*
*Framework: Laravel*
*Status: Planning Phase*