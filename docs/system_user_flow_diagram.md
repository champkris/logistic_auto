# 🌊 CS Shipping LCB - System & User Flow Diagram
**Complete Workflow: From Shipment Intake to Final Delivery**

---

## 🎭 **User Roles & System Interaction Overview**

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           👥 USER ECOSYSTEM                                    │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  🏢 INTERNAL USERS              🌐 EXTERNAL USERS           🎯 SYSTEM USERS     │
│  ┌─────────────────┐            ┌─────────────────┐         ┌─────────────────┐ │
│  │ 🎯 CS LCB Team  │            │ 👤 Customers    │         │ 🤖 Automation   │ │
│  │ 🚢 Shipping     │            │ 🏭 Factories    │         │ 📧 Email Bot    │ │
│  │ 🚛 Transport    │            │ 🏢 Suppliers    │         │ 🕐 Scheduler    │ │
│  │ 📊 Management   │            │ 🌐 Port APIs    │         │ 📊 Monitor     │ │
│  │ 📄 CS BKK       │            │ 🏛️ Customs      │         │ 🔔 Notifier    │ │
│  └─────────────────┘            └─────────────────┘         └─────────────────┘ │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 🔄 **Complete System Workflow Diagram**

```
                             🎬 SHIPMENT LIFECYCLE FLOW
┌─────────────────────────────────────────────────────────────────────────────────┐
│                                                                                 │
│  📥 STAGE 1: INTAKE & PLANNING                                                 │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │                                                                         │   │
│  │  👤 CS BKK / LCB Staff                   🎯 CS LCB Team                 │   │
│  │  ┌─────────────────┐                     ┌─────────────────┐           │   │
│  │  │ 📋 New Shipment │────────────────────►│ 🎯 Job Intake   │           │   │
│  │  │   Information   │                     │   & Validation  │           │   │
│  │  │                 │                     │                 │           │   │
│  │  │ • HBL/MBL #     │                     │ • Verify Data   │           │   │
│  │  │ • Customer Info │                     │ • Create Record │           │   │
│  │  │ • Vessel Details│                     │ • Assign Tasks  │           │   │
│  │  │ • Cargo Info    │                     │ • Set Timeline  │           │   │
│  │  └─────────────────┘                     └─────────────────┘           │   │
│  │           │                                       │                     │   │
│  │           └───────────────────┬───────────────────┘                     │   │
│  │                               │                                         │   │
│  │                               ▼                                         │   │
│  │                     ┌─────────────────┐                                 │   │
│  │                     │ 💾 System       │                                 │   │
│  │                     │   Creates:      │                                 │   │
│  │                     │                 │                                 │   │
│  │                     │ • Shipment      │                                 │   │
│  │                     │ • Customer Link │                                 │   │
│  │                     │ • Vessel Link   │                                 │   │
│  │                     │ • Timeline      │                                 │   │
│  │                     └─────────────────┘                                 │   │
│  │                               │                                         │   │
│  └───────────────────────────────┼─────────────────────────────────────────┘   │
│                                  │                                             │
│  ──────────────────────────────────────────────────────────────────────────   │
│                                  │                                             │
│  📊 STAGE 2: TRACKING & MONITORING                                             │
│  ┌───────────────────────────────┼─────────────────────────────────────────┐   │
│  │                               │                                         │   │
│  │  🤖 Automated System                      🎯 CS LCB Team                │   │
│  │  ┌─────────────────┐                     ┌─────────────────┐           │   │
│  │  │ 🌐 Port Website │                     │ 📊 Dashboard    │           │   │
│  │  │   Checker       │◄────┐               │   Monitor       │           │   │
│  │  │                 │     │               │                 │           │   │
│  │  │ • ETA Updates   │     │               │ • Live Status   │           │   │
│  │  │ • Arrival Status│     │               │ • Alerts        │           │   │
│  │  │ • Port Delays   │     │               │ • Planning      │           │   │
│  │  │ • Auto Alerts   │     │               │ • Coordination  │           │   │
│  │  └─────────────────┘     │               └─────────────────┘           │   │
│  │           │               │                       │                     │   │
│  │           ▼               │                       ▼                     │   │
│  │  ┌─────────────────┐     │               ┌─────────────────┐           │   │
│  │  │ 💾 Update       │     │               │ 📧 Customer     │           │   │
│  │  │   Database      │     │               │   Updates       │           │   │
│  │  │                 │     │               │                 │           │   │
│  │  │ • Vessel Status │     │               │ • Daily Emails  │           │   │
│  │  │ • ETA Changes   │     │               │ • Status Change │           │   │
│  │  │ • Port Info     │     │               │ • Notifications │           │   │
│  │  └─────────────────┘     │               └─────────────────┘           │   │
│  │           │               │                       │                     │   │
│  │           └───────────────┼───────────────────────┘                     │   │
│  │                           │                                             │   │
│  │                           ▼                                             │   │
│  │                  ┌─────────────────┐                                    │   │
│  │                  │ 👤 Customers    │                                    │   │
│  │                  │   Receive:      │                                    │   │
│  │                  │                 │                                    │   │
│  │                  │ • Email Updates │                                    │   │
│  │                  │ • ETA Changes   │                                    │   │
│  │                  │ • Status Alerts │                                    │   │
│  │                  │ • Next Steps    │                                    │   │
│  │                  └─────────────────┘                                    │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                  │                                             │
│  ──────────────────────────────────────────────────────────────────────────   │
│                                  │                                             │
│  📄 STAGE 3: DOCUMENT MANAGEMENT                                               │
│  ┌───────────────────────────────┼─────────────────────────────────────────┐   │
│  │                               │                                         │   │
│  │  📄 CS BKK Team                             🎯 CS LCB Team              │   │
│  │  ┌─────────────────┐                     ┌─────────────────┐           │   │
│  │  │ 📋 Document     │────────────────────►│ 📊 Document     │           │   │
│  │  │   Preparation   │                     │   Tracking      │           │   │
│  │  │                 │                     │                 │           │   │
│  │  │ • Customs Decl. │                     │ • Status Check  │           │   │
│  │  │ • D/O Process   │                     │ • Completeness  │           │   │
│  │  │ • Permits       │                     │ • Deadlines     │           │   │
│  │  │ • Mill Tests    │                     │ • Coordination  │           │   │
│  │  └─────────────────┘                     └─────────────────┘           │   │
│  │           │                                       │                     │   │
│  │           ▼                                       ▼                     │   │
│  │  ┌─────────────────┐                     ┌─────────────────┐           │   │
│  │  │ 💾 Upload to    │                     │ ⚠️ Alert        │           │   │
│  │  │   System        │                     │   System        │           │   │
│  │  │                 │                     │                 │           │   │
│  │  │ • Digital Files │                     │ • Missing Docs  │           │   │
│  │  │ • Version Track │                     │ • Deadlines     │           │   │
│  │  │ • Status Update │                     │ • Dependencies  │           │   │
│  │  └─────────────────┘                     └─────────────────┘           │   │
│  │           │                                       │                     │   │
│  │           └───────────────────┬───────────────────┘                     │   │
│  │                               │                                         │   │
│  │                               ▼                                         │   │
│  │                     ┌─────────────────┐                                 │   │
│  │                     │ 🚢 Shipping     │                                 │   │
│  │                     │   Team Gets:    │                                 │   │
│  │                     │                 │                                 │   │
│  │                     │ • Complete Docs │                                 │   │
│  │                     │ • Clearance Set │                                 │   │
│  │                     │ • Ready Signal  │                                 │   │
│  │                     └─────────────────┘                                 │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                  │                                             │
│  ──────────────────────────────────────────────────────────────────────────   │
│                                  │                                             │
│  🚢 STAGE 4: CLEARANCE & PROCESSING                                            │
│  ┌───────────────────────────────┼─────────────────────────────────────────┐   │
│  │                               │                                         │   │
│  │  🚢 Shipping Team                           🏛️ External Systems          │   │
│  │  ┌─────────────────┐                     ┌─────────────────┐           │   │
│  │  │ 🔍 Pre-Clear    │◄────────────────────┤ 🌐 Port System  │           │   │
│  │  │   Validation    │                     │ 🏛️ Customs      │           │   │
│  │  │                 │                     │ 📋 Authorities  │           │   │
│  │  │ • Doc Check     │                     │                 │           │   │
│  │  │ • Container     │                     │ • Status Updates│           │   │
│  │  │ • Vessel Status │                     │ • Approvals     │           │   │
│  │  │ • Compliance    │                     │ • Clearances    │           │   │
│  │  └─────────────────┘                     └─────────────────┘           │   │
│  │           │                                       │                     │   │
│  │           ▼                                       │                     │   │
│  │  ┌─────────────────┐                             │                     │   │
│  │  │ ✅ Clearance    │                             │                     │   │
│  │  │   Processing    │                             │                     │   │
│  │  │                 │                             │                     │   │
│  │  │ • Submit Docs   │                             │                     │   │
│  │  │ • Pay Fees      │                             │                     │   │
│  │  │ • Get Approvals │                             │                     │   │
│  │  │ • Release Cargo │                             │                     │   │
│  │  └─────────────────┘                             │                     │   │
│  │           │                                       │                     │   │
│  │           └───────────────────┬───────────────────┘                     │   │
│  │                               │                                         │   │
│  │                               ▼                                         │   │
│  │                     ┌─────────────────┐                                 │   │
│  │                     │ 💾 System       │                                 │   │
│  │                     │   Updates:      │                                 │   │
│  │                     │                 │                                 │   │
│  │                     │ • Clear Status  │                                 │   │
│  │                     │ • Ready for     │                                 │   │
│  │                     │   Delivery      │                                 │   │
│  │                     │ • Notify Teams  │                                 │   │
│  │                     └─────────────────┘                                 │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                  │                                             │
│  ──────────────────────────────────────────────────────────────────────────   │
│                                  │                                             │
│  🚛 STAGE 5: DELIVERY COORDINATION                                             │
│  ┌───────────────────────────────┼─────────────────────────────────────────┐   │
│  │                               │                                         │   │
│  │  🎯 CS LCB Team                             🚛 Transport Team            │   │
│  │  ┌─────────────────┐                     ┌─────────────────┐           │   │
│  │  │ 📅 Delivery     │────────────────────►│ 🚛 Logistics    │           │   │
│  │  │   Planning      │                     │   Execution     │           │   │
│  │  │                 │                     │                 │           │   │
│  │  │ • Schedule      │                     │ • Route Plan    │           │   │
│  │  │ • Coordinate    │                     │ • Vehicle Assign│           │   │
│  │  │ • Customer Conf │                     │ • Driver Brief  │           │   │
│  │  │ • Resource Plan │                     │ • Execute       │           │   │
│  │  └─────────────────┘                     └─────────────────┘           │   │
│  │           │                                       │                     │   │
│  │           ▼                                       ▼                     │   │
│  │  ┌─────────────────┐                     ┌─────────────────┐           │   │
│  │  │ 📱 Coordination │                     │ 📍 Real-time    │           │   │
│  │  │   Dashboard     │                     │   Tracking      │           │   │
│  │  │                 │                     │                 │           │   │
│  │  │ • Live Updates  │                     │ • GPS Location  │           │   │
│  │  │ • Team Comms    │                     │ • ETA Updates   │           │   │
│  │  │ • Issue Alerts  │                     │ • Delivery Conf │           │   │
│  │  └─────────────────┘                     └─────────────────┘           │   │
│  │           │                                       │                     │   │
│  │           └───────────────────┬───────────────────┘                     │   │
│  │                               │                                         │   │
│  │                               ▼                                         │   │
│  │                     ┌─────────────────┐                                 │   │
│  │                     │ 👤 Customer     │                                 │   │
│  │                     │   Receives:     │                                 │   │
│  │                     │                 │                                 │   │
│  │                     │ • Delivery ETA  │                                 │   │
│  │                     │ • Live Updates  │                                 │   │
│  │                     │ • Completion    │                                 │   │
│  │                     │ • Feedback Req  │                                 │   │
│  │                     └─────────────────┘                                 │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                  │                                             │
│  ──────────────────────────────────────────────────────────────────────────   │
│                                  │                                             │
│  ✅ STAGE 6: COMPLETION & ANALYSIS                                             │
│  ┌───────────────────────────────┼─────────────────────────────────────────┐   │
│  │                               │                                         │   │
│  │  💾 System Automation                      📊 Management Dashboard      │   │
│  │  ┌─────────────────┐                     ┌─────────────────┐           │   │
│  │  │ 📋 Final        │                     │ 📈 Analytics    │           │   │
│  │  │   Updates       │                     │   & Reports     │           │   │
│  │  │                 │                     │                 │           │   │
│  │  │ • Status: Done  │                     │ • Performance   │           │   │
│  │  │ • Archive Docs  │                     │ • Metrics       │           │   │
│  │  │ • Time Tracking │                     │ • Trends        │           │   │
│  │  │ • Customer Conf │                     │ • Optimization  │           │   │
│  │  └─────────────────┘                     └─────────────────┘           │   │
│  │           │                                       │                     │   │
│  │           ▼                                       ▼                     │   │
│  │  ┌─────────────────┐                     ┌─────────────────┐           │   │
│  │  │ 🔄 Process      │                     │ 🎯 Continuous   │           │   │
│  │  │   Improvement   │                     │   Improvement   │           │   │
│  │  │                 │                     │                 │           │   │
│  │  │ • Learn Patterns│                     │ • Process Opt   │           │   │
│  │  │ • Update Rules  │                     │ • Team Training │           │   │
│  │  │ • Optimize Flow │                     │ • System Updates│           │   │
│  │  └─────────────────┘                     └─────────────────┘           │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 👥 **Detailed User Role Interactions**

### **🎯 CS Shipping LCB Team (Primary Users)**
```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          🎯 CS LCB TEAM WORKFLOW                               │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  📅 DAILY ROUTINE:                          🖥️ SYSTEM INTERACTIONS:             │
│  ┌─────────────────┐                        ┌─────────────────┐               │
│  │ 🌅 Morning      │                        │ 📊 Dashboard    │               │
│  │ (7:00-9:00 AM)  │                        │   Access        │               │
│  │                 │                        │                 │               │
│  │ ❌ Before: 2hrs │──────────────────────► │ ✅ After: 5min  │               │
│  │ • Manual checks │                        │ • Auto updates  │               │
│  │ • Port websites │                        │ • Live data     │               │
│  │ • Excel updates │                        │ • Smart alerts  │               │
│  │ • Email writing │                        │ • One-click     │               │
│  └─────────────────┘                        └─────────────────┘               │
│           │                                           │                        │
│           ▼                                           ▼                        │
│  ┌─────────────────┐                        ┌─────────────────┐               │
│  │ 🎯 Core Tasks   │                        │ 💻 System       │               │
│  │                 │                        │   Features      │               │
│  │ • Job intake    │◄──────────────────────►│ • CRUD shipments│               │
│  │ • Planning      │                        │ • Vessel track  │               │
│  │ • Coordination  │                        │ • Doc manage    │               │
│  │ • Monitoring    │                        │ • Auto alerts   │               │
│  │ • Customer comm │                        │ • Team notify   │               │
│  └─────────────────┘                        └─────────────────┘               │
│           │                                           │                        │
│           ▼                                           ▼                        │
│  ┌─────────────────┐                        ┌─────────────────┐               │
│  │ 📈 Outcomes     │                        │ 🎯 Benefits     │               │
│  │                 │                        │                 │               │
│  │ • Time saved    │                        │ • Less errors   │               │
│  │ • Accuracy up   │                        │ • Real-time     │               │
│  │ • Stress down   │                        │ • Professional  │               │
│  │ • Focus on      │                        │ • Scalable      │               │
│  │   value-add     │                        │ • Trackable     │               │
│  └─────────────────┘                        └─────────────────┘               │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### **🚢 Shipping Team Interactions**
```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                         🚢 SHIPPING TEAM WORKFLOW                              │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  📥 RECEIVES FROM SYSTEM:              📤 PROVIDES TO SYSTEM:                   │
│  ┌─────────────────┐                   ┌─────────────────┐                     │
│  │ 📋 Complete     │                   │ ✅ Clearance    │                     │
│  │   Documentation │                   │   Status        │                     │
│  │                 │                   │                 │                     │
│  │ • All permits   │                   │ • Processing    │                     │
│  │ • D/O ready     │                   │ • Completed     │                     │
│  │ • Customs docs  │                   │ • Issues found  │                     │
│  │ • Vessel status │                   │ • Ready for     │                     │
│  │ • Container info│                   │   transport     │                     │
│  └─────────────────┘                   └─────────────────┘                     │
│           │                                       │                            │
│           ▼                                       ▼                            │
│  ┌─────────────────┐                   ┌─────────────────┐                     │
│  │ 🔔 Notifications│                   │ 📊 Updates      │                     │
│  │                 │                   │                 │                     │
│  │ • Ready to clear│                   │ • Status changes│                     │
│  │ • Issues found  │                   │ • Time stamps   │                     │
│  │ • Urgent items  │                   │ • Cost updates  │                     │
│  │ • Dependencies  │                   │ • Next steps    │                     │
│  └─────────────────┘                   └─────────────────┘                     │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### **🚛 Transport Team Interactions**
```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        🚛 TRANSPORT TEAM WORKFLOW                              │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  📥 GETS FROM CS LCB:                  📱 MOBILE ACCESS:                        │
│  ┌─────────────────┐                   ┌─────────────────┐                     │
│  │ 📅 Delivery     │                   │ 📱 Field App    │                     │
│  │   Schedule      │                   │   Updates       │                     │
│  │                 │                   │                 │                     │
│  │ • Customer info │                   │ • GPS tracking  │                     │
│  │ • Delivery time │                   │ • Status update │                     │
│  │ • Special req   │                   │ • Photo capture │                     │
│  │ • Contact info  │                   │ • Issue report  │                     │
│  │ • Route details │                   │ • Completion    │                     │
│  └─────────────────┘                   └─────────────────┘                     │
│           │                                       │                            │
│           ▼                                       ▼                            │
│  ┌─────────────────┐                   ┌─────────────────┐                     │
│  │ 🚛 Execution    │                   │ 📊 Real-time    │                     │
│  │                 │                   │   Feedback      │                     │
│  │ • Route opt     │                   │                 │                     │
│  │ • Load planning │                   │ • ETA updates   │                     │
│  │ • Driver assign │                   │ • Issue alerts  │                     │
│  │ • Equipment     │                   │ • Success conf  │                     │
│  └─────────────────┘                   └─────────────────┘                     │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### **👤 Customer Experience Journey**
```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          👤 CUSTOMER JOURNEY                                   │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  📧 COMMUNICATION TIMELINE:                                                     │
│                                                                                 │
│  ┌─────┐  ┌─────┐  ┌─────┐  ┌─────┐  ┌─────┐  ┌─────┐  ┌─────┐               │
│  │Day-7│  │Day-3│  │Day-1│  │Day 0│  │Day+1│  │Day+2│  │Done │               │
│  └─────┘  └─────┘  └─────┘  └─────┘  └─────┘  └─────┘  └─────┘               │
│      │      │       │       │       │       │       │                        │
│      ▼      ▼       ▼       ▼       ▼       ▼       ▼                        │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 📧 EMAIL SEQUENCE (AUTOMATED):                                         │   │
│  │                                                                         │   │
│  │ 1️⃣ Shipment Received    "Your cargo is in our system"                 │   │
│  │ 2️⃣ Vessel Update        "ETA confirmed for [DATE]"                    │   │
│  │ 3️⃣ Pre-arrival Notice   "Vessel arriving tomorrow"                    │   │
│  │ 4️⃣ Arrival Confirmed    "Vessel docked, processing docs"              │   │
│  │ 5️⃣ Ready for Delivery   "Cleared, scheduling delivery"                │   │
│  │ 6️⃣ Out for Delivery     "Driver en route, ETA [TIME]"                 │   │
│  │ 7️⃣ Delivery Complete    "Delivered successfully + photos"             │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                      │                                         │
│                                      ▼                                         │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 📱 SELF-SERVICE PORTAL (Future):                                       │   │
│  │                                                                         │   │
│  │ • Track shipment status          • Download documents                  │   │
│  │ • View ETA updates               • Schedule delivery                   │   │
│  │ • Chat with CS team              • Rate service                        │   │
│  │ • Update delivery address        • Request special handling            │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### **📊 Management Dashboard Flow**
```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        📊 MANAGEMENT OVERVIEW                                  │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  🎯 REAL-TIME METRICS:              📈 ANALYTICS & REPORTS:                    │
│  ┌─────────────────┐                ┌─────────────────┐                       │
│  │ 📊 Live Stats   │                │ 📋 Performance  │                       │
│  │                 │                │   Reports       │                       │
│  │ • Active: 25    │                │                 │                       │
│  │ • Arriving: 3   │                │ • Time savings  │                       │
│  │ • Pending: 18   │                │ • Error rates   │                       │
│  │ • Completed: 47 │                │ • Customer sat  │                       │
│  │ • Alerts: 2     │                │ • Team produc   │                       │
│  └─────────────────┘                └─────────────────┘                       │
│           │                                  │                                │
│           ▼                                  ▼                                │
│  ┌─────────────────┐                ┌─────────────────┐                       │
│  │ 🚨 Alert System │                │ 🎯 Strategic    │                       │
│  │                 │                │   Insights      │                       │
│  │ • Overdue items │                │                 │                       │
│  │ • Bottlenecks   │                │ • Process opt   │                       │
│  │ • Customer esc  │                │ • Resource need │                       │
│  │ • System issues │                │ • Growth areas  │                       │
│  └─────────────────┘                └─────────────────┘                       │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 🤖 **System Automation Flows**

### **⏰ Scheduled Tasks (CRON Jobs)**
```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          🤖 AUTOMATION SCHEDULE                                │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  🌅 EVERY MORNING (6:00 AM):                                                   │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 1. Check all vessel statuses from port websites                        │   │
│  │ 2. Update ETAs and arrival times                                       │   │
│  │ 3. Generate daily customer email reports                               │   │
│  │ 4. Send alerts for urgent items                                        │   │
│  │ 5. Update dashboard statistics                                          │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                 │
│  🕒 EVERY HOUR (24/7):                                                         │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ 1. Monitor critical shipments                                          │   │
│  │ 2. Check for document updates                                           │   │
│  │ 3. Process notification queue                                           │   │
│  │ 4. Sync with external systems                                           │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                 │
│  📅 REAL-TIME TRIGGERS:                                                        │
│  ┌─────────────────────────────────────────────────────────────────────────┐   │
│  │ • Vessel arrival → Notify CS team + customers                          │   │
│  │ • Document upload → Validate and alert if missing                      │   │
│  │ • Status change → Update all stakeholders                              │   │
│  │ • Deadline approaching → Escalate to management                        │   │
│  │ • Customer response → Route to appropriate team                        │   │
│  └─────────────────────────────────────────────────────────────────────────┘   │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 📊 **System Integration Points**

### **🌐 External API Connections**
```
CS Shipping LCB System
         │
         ├── 🌐 Port Websites APIs
         │   ├── Laem Chabang Port
         │   ├── Bangkok Port  
         │   └── Map Ta Phut Port
         │
         ├── 🏛️ Government Systems
         │   ├── Customs Department
         │   ├── Port Authority
         │   └── Maritime Department
         │
         ├── 📧 Communication Services
         │   ├── Email Provider (SMTP)
         │   ├── SMS Gateway
         │   └── Push Notifications
         │
         └── 🚛 Logistics Partners
             ├── Transport Companies
             ├── GPS Tracking
             └── Delivery Confirmation
```

---

**🎯 This comprehensive flow diagram shows how every user role interacts with the system, from initial shipment intake through final delivery, with full automation and real-time updates throughout the entire process!**