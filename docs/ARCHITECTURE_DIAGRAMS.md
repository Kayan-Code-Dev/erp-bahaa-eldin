# Architecture Diagrams - Atelier Management System

## Table of Contents
1. [Current System Architecture](#current-system-architecture)
2. [Planned System Architecture](#planned-system-architecture)
3. [Database Schema - Current](#database-schema---current)
4. [Database Schema - Planned Additions](#database-schema---planned-additions)
5. [Accounting Module Flow](#accounting-module-flow)
6. [Permissions System Flow](#permissions-system-flow)
7. [Order Lifecycle](#order-lifecycle)
8. [Appointment/Scheduling Flow](#appointmentscheduling-flow)

---

## Current System Architecture

```mermaid
graph TB
    subgraph Authentication
        Auth[Auth Controller]
        Sanctum[Laravel Sanctum]
    end

    subgraph EntityManagement[Entity Management]
        Branch[Branches]
        Workshop[Workshops]
        Factory[Factories]
    end

    subgraph InventorySystem[Inventory System]
        Inventory[Inventories]
        ClothType[Cloth Types]
        Cloth[Clothes]
        Category[Categories]
        Subcategory[Subcategories]
    end

    subgraph OrderSystem[Order System]
        Order[Orders]
        Payment[Payments]
        Custody[Custodies]
        Rent[Rents]
        OrderReturn[Order Returns]
    end

    subgraph ClientSystem[Client System]
        Client[Clients]
        Phone[Phones]
        Address[Addresses]
    end

    subgraph TransferSystem[Transfer System]
        Transfer[Transfers]
        TransferItem[Transfer Items]
        TransferAction[Transfer Actions]
    end

    subgraph HistoryTracking[History & Tracking]
        ClothHistory[Cloth History]
        OrderHistory[Order History]
    end

    Auth --> Sanctum
    Branch --> Inventory
    Workshop --> Inventory
    Factory --> Inventory
    Inventory --> Cloth
    ClothType --> Cloth
    Client --> Order
    Inventory --> Order
    Order --> Payment
    Order --> Custody
    Order --> Rent
    Order --> OrderReturn
    Transfer --> TransferItem
    Transfer --> TransferAction
    Cloth --> ClothHistory
    Order --> OrderHistory
```

---

## Planned System Architecture

```mermaid
graph TB
    subgraph AuthPermissions[Authentication & Permissions]
        Auth[Auth Controller]
        Sanctum[Laravel Sanctum]
        Permission[Permissions]
        Role[Roles]
        PermMiddleware[Permission Middleware]
    end

    subgraph EntityManagement[Entity Management]
        Branch[Branches]
        Workshop[Workshops]
        Factory[Factories]
        FactoryStats[Factory Statistics]
        FactoryEval[Factory Evaluations]
    end

    subgraph InventorySystem[Inventory System]
        Inventory[Inventories]
        ClothType[Cloth Types]
        Cloth[Clothes]
        Category[Categories]
        Subcategory[Subcategories]
    end

    subgraph OrderSystem[Order System]
        Order[Orders]
        Payment[Payments]
        Custody[Custodies]
        Rent[Rents/Appointments]
        OrderReturn[Order Returns]
        TailoringStage[Tailoring Stages]
    end

    subgraph ClientSystem[Client System]
        Client[Clients + Measurements]
        Phone[Phones]
        Address[Addresses]
    end

    subgraph AccountingSystem[Accounting System - NEW]
        Cashbox[Cashboxes]
        Transaction[Transactions]
        Expense[Expenses]
        Receivable[Receivables]
    end

    subgraph TransferSystem[Transfer System]
        Transfer[Transfers]
        TransferItem[Transfer Items]
        TransferAction[Transfer Actions]
    end

    subgraph ReportingSystem[Reporting System - NEW]
        Reports[Reports Controller]
        FinancialReport[Financial Reports]
        InventoryReport[Inventory Reports]
        PerformanceReport[Performance Reports]
    end

    subgraph NotificationSystem[Notification System - NEW]
        Notification[Notifications]
        Reminder[Reminders]
    end

    Auth --> Sanctum
    Role --> Permission
    PermMiddleware --> Permission
    
    Branch --> Inventory
    Branch --> Cashbox
    Workshop --> Inventory
    Factory --> Inventory
    Factory --> FactoryStats
    Factory --> FactoryEval

    Client --> Order
    Inventory --> Order
    Order --> Payment
    Order --> Custody
    Order --> Rent
    Order --> OrderReturn
    Order --> TailoringStage

    Payment --> Transaction
    Expense --> Transaction
    Custody --> Transaction
    Transaction --> Cashbox
    
    Order --> Receivable
    Client --> Receivable

    Reports --> FinancialReport
    Reports --> InventoryReport
    Reports --> PerformanceReport
```

---

## Database Schema - Current

```mermaid
erDiagram
    users ||--o{ role_user : has
    roles ||--o{ role_user : has
    
    clients ||--o{ phones : has
    clients ||--o{ orders : places
    clients }o--|| addresses : has
    
    addresses }o--|| cities : in
    cities }o--|| countries : in
    
    branches ||--|| inventories : has
    workshops ||--|| inventories : has
    factories ||--|| inventories : has
    
    inventories ||--o{ cloth_inventory : contains
    clothes ||--o{ cloth_inventory : stored_in
    
    cloth_types ||--o{ clothes : defines
    cloth_types ||--o{ cloth_type_subcategory : categorized
    subcategories ||--o{ cloth_type_subcategory : categorizes
    categories ||--o{ subcategories : contains
    
    orders ||--o{ cloth_order : contains
    clothes ||--o{ cloth_order : ordered_in
    orders ||--o{ order_payments : has
    orders ||--o{ custodies : has
    orders ||--o{ rents : has
    orders ||--o{ order_returns : has
    orders ||--o{ order_history : tracked
    
    custodies ||--o{ custody_photos : has
    custodies ||--o{ custody_returns : has
    
    clothes ||--o{ cloth_history : tracked
    
    transfers ||--o{ transfer_items : contains
    transfers ||--o{ transfer_actions : tracked
    clothes ||--o{ transfer_items : transferred

    users {
        bigint id PK
        string name
        string email UK
        string password
        timestamp email_verified_at
        timestamps timestamps
        softDeletes deleted_at
    }
    
    roles {
        bigint id PK
        string name
        string description
        timestamps timestamps
    }
    
    clients {
        bigint id PK
        string first_name
        string middle_name
        string last_name
        date date_of_birth
        string national_id
        bigint address_id FK
        string source
        timestamps timestamps
        softDeletes deleted_at
    }
    
    orders {
        bigint id PK
        bigint client_id FK
        bigint inventory_id FK
        decimal total_price
        enum status
        decimal paid
        decimal remaining
        datetime visit_datetime
        string order_notes
        enum discount_type
        decimal discount_value
        timestamps timestamps
        softDeletes deleted_at
    }
    
    clothes {
        bigint id PK
        string code UK
        string name
        text description
        bigint cloth_type_id FK
        string breast_size
        string waist_size
        string sleeve_size
        text notes
        enum status
        timestamps timestamps
        softDeletes deleted_at
    }
    
    order_payments {
        bigint id PK
        bigint order_id FK
        decimal amount
        enum status
        enum payment_type
        datetime payment_date
        text notes
        bigint created_by FK
        timestamps timestamps
        softDeletes deleted_at
    }
    
    custodies {
        bigint id PK
        bigint order_id FK
        enum type
        string description
        decimal value
        enum status
        datetime returned_at
        string return_proof_photo
        text notes
        timestamps timestamps
        softDeletes deleted_at
    }
    
    rents {
        bigint id PK
        bigint cloth_id FK
        bigint order_id FK
        bigint cloth_order_id FK
        date delivery_date
        date return_date
        int days_of_rent
        enum status
        timestamps timestamps
    }
```

---

## Database Schema - Planned Additions

```mermaid
erDiagram
    permissions ||--o{ permission_role : assigned
    roles ||--o{ permission_role : has
    
    branches ||--|| cashboxes : has
    cashboxes ||--o{ transactions : contains
    
    order_payments ||--o{ transactions : creates
    expenses ||--o{ transactions : creates
    custodies ||--o{ transactions : creates
    
    clients ||--o{ receivables : owes
    orders ||--o{ receivables : generates
    
    users ||--o{ notifications : receives
    
    factories ||--o{ factory_evaluations : evaluated
    orders ||--o{ factory_evaluations : generates

    permissions {
        bigint id PK
        string name UK
        string description
        string module
        string action
        timestamps timestamps
    }
    
    permission_role {
        bigint permission_id FK
        bigint role_id FK
    }
    
    cashboxes {
        bigint id PK
        bigint branch_id FK UK
        decimal current_balance
        decimal opening_balance
        datetime last_transaction_at
        timestamps timestamps
    }
    
    transactions {
        bigint id PK
        bigint cashbox_id FK
        enum type
        decimal amount
        decimal balance_after
        string reference_type
        bigint reference_id
        string description
        bigint branch_id FK
        bigint user_id FK
        timestamps timestamps
    }
    
    expenses {
        bigint id PK
        bigint branch_id FK
        enum type
        decimal amount
        date expense_date
        text description
        text notes
        bigint created_by FK
        timestamps timestamps
        softDeletes deleted_at
    }
    
    receivables {
        bigint id PK
        bigint client_id FK
        bigint order_id FK
        decimal total_amount
        decimal paid_amount
        decimal remaining_amount
        date due_date
        enum status
        text notes
        timestamps timestamps
        softDeletes deleted_at
    }
    
    notifications {
        bigint id PK
        bigint user_id FK
        enum type
        string title
        text message
        string reference_type
        bigint reference_id
        datetime read_at
        enum priority
        timestamps timestamps
    }
    
    factory_evaluations {
        bigint id PK
        bigint factory_id FK
        bigint order_id FK
        decimal quality_rating
        int completion_days
        boolean on_time
        text notes
        bigint evaluated_by FK
        datetime evaluated_at
        timestamps timestamps
    }

    clients_additions {
        string breast_size
        string waist_size
        string sleeve_size
        string hip_size
        string shoulder_size
        string length_size
        text measurement_notes
        date last_measurement_date
    }

    rents_additions {
        enum appointment_type
        string title
        text description
        enum appointment_status
        boolean reminder_sent
        datetime reminder_sent_at
        int duration_minutes
    }

    orders_additions {
        enum tailoring_stage
        datetime tailoring_stage_changed_at
        date expected_completion_date
        bigint assigned_factory_id
        text factory_notes
    }
```

---

## Accounting Module Flow

```mermaid
flowchart TD
    subgraph PaymentFlow[Payment Flow]
        P1[Create Payment] --> P2{Payment Status}
        P2 -->|pending| P3[Wait for Pay Action]
        P2 -->|paid| P4[Mark as Paid]
        P3 --> P4
        P4 --> T1[Create Transaction]
    end

    subgraph TransactionFlow[Transaction Creation]
        T1 --> T2[Get Branch Cashbox]
        T2 --> T3{Validate Balance}
        T3 -->|sufficient| T4[Create Transaction Record]
        T3 -->|insufficient| T5[Return Error]
        T4 --> T6[Update Cashbox Balance]
        T6 --> T7[Log Transaction]
    end

    subgraph ExpenseFlow[Expense Flow]
        E1[Create Expense] --> E2[Get Branch Cashbox]
        E2 --> E3{Check Balance}
        E3 -->|sufficient| E4[Create Expense Record]
        E3 -->|insufficient| E5[Return Error - Insufficient Funds]
        E4 --> E6[Create Outgoing Transaction]
        E6 --> E7[Update Cashbox Balance]
    end

    subgraph CustodyFlow[Custody/Deposit Flow]
        C1[Create Custody - Money] --> C2[Create Incoming Transaction]
        C2 --> C3[Update Cashbox Balance]
        C4[Return Custody] --> C5{Return Type}
        C5 -->|returned| C6[Create Outgoing Transaction]
        C5 -->|forfeited| C7[Convert to Revenue]
        C6 --> C8[Update Cashbox Balance]
        C7 --> C9[Create Revenue Transaction]
    end

    subgraph CashboxRules[Cashbox Rules]
        R1[NO DELETE Transactions]
        R2[NO UPDATE Transactions]
        R3[Only REVERSAL Allowed]
        R4[Balance Must Be >= 0]
        R5[Every Change = New Transaction]
    end
```

---

## Permissions System Flow

```mermaid
flowchart TD
    subgraph Request[API Request]
        A1[User Makes Request] --> A2[Auth Middleware]
        A2 --> A3{Authenticated?}
        A3 -->|No| A4[401 Unauthorized]
        A3 -->|Yes| A5[Permission Middleware]
    end

    subgraph PermissionCheck[Permission Check]
        A5 --> B1{Is Super Admin?}
        B1 -->|Yes - admin@admin.com| B2[Allow All]
        B1 -->|No| B3[Get User Roles]
        B3 --> B4[Get Role Permissions]
        B4 --> B5{Has Required Permission?}
        B5 -->|Yes| B6[Allow Request]
        B5 -->|No| B7[403 Forbidden]
    end

    subgraph PermissionStructure[Permission Structure]
        C1[Module: orders]
        C2[Actions: create, view, update, delete, deliver, finish, cancel]
        C1 --> C3[orders.create]
        C1 --> C4[orders.view]
        C1 --> C5[orders.update]
        C1 --> C6[orders.delete]
        C1 --> C7[orders.deliver]
        C1 --> C8[orders.finish]
    end

    subgraph DefaultRoles[Default Roles]
        D1[general_manager] --> D1P[ALL Permissions]
        D2[reception_employee] --> D2P[clients.*, orders.rent.*, appointments.*]
        D3[sales_employee] --> D3P[clients.*, orders.buy.*, payments.*]
        D4[factory_manager] --> D4P[orders.tailoring.*, factories.*, transfers.*]
        D5[accountant] --> D5P[payments.*, custody.*, reports.financial.*]
    end
```

---

## Order Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Created: Create Order
    
    Created --> PartiallyPaid: Add Payment (partial)
    Created --> Paid: Add Payment (full)
    Created --> Canceled: Cancel Order
    
    PartiallyPaid --> Paid: Complete Payment
    PartiallyPaid --> Canceled: Cancel Order
    
    Paid --> Delivered: Deliver Items
    Paid --> Canceled: Cancel Order
    
    Delivered --> Finished: All Items Returned + All Custody Decided
    
    Finished --> [*]
    Canceled --> [*]

    note right of Created
        - Items added to order
        - Total price calculated
        - Custody can be added
    end note

    note right of Paid
        - All payments complete
        - Must have custody before delivery
    end note

    note right of Delivered
        - Items given to customer
        - Rents created for rent items
        - Waiting for returns
    end note

    note right of Finished
        - All rent items returned
        - All custody decided (returned/forfeited)
        - All fees paid
    end note
```

---

## Tailoring Order Stages

```mermaid
stateDiagram-v2
    [*] --> Received: Order Created with Tailoring Items
    
    Received --> SentToFactory: Send to Factory
    SentToFactory --> InProduction: Factory Starts Work
    InProduction --> ReadyFromFactory: Factory Completes
    ReadyFromFactory --> ReadyForCustomer: Received from Factory
    ReadyForCustomer --> Delivered: Customer Picks Up
    Delivered --> [*]

    note right of Received
        - Order accepted
        - Measurements recorded
        - Model selected
    end note

    note right of SentToFactory
        - Transfer created to factory
        - Factory assigned
        - Expected date set
    end note

    note right of InProduction
        - Factory working
        - Can track progress
    end note

    note right of ReadyFromFactory
        - Factory completed
        - Evaluation can be done
    end note

    note right of ReadyForCustomer
        - Back at branch
        - Customer notified
    end note
```

---

## Appointment/Scheduling Flow (Extended Rents)

```mermaid
flowchart TD
    subgraph AppointmentTypes[Appointment Types]
        T1[rental_delivery]
        T2[rental_return]
        T3[measurement]
        T4[tailoring_pickup]
        T5[tailoring_delivery]
        T6[other]
    end

    subgraph CreateAppointment[Create Appointment]
        A1[Select Type] --> A2[Set Date/Time]
        A2 --> A3[Link to Order - Optional]
        A3 --> A4[Link to Client]
        A4 --> A5[Set Duration]
        A5 --> A6[Save to Rents Table]
    end

    subgraph AutoCreate[Auto-Created Appointments]
        B1[Create Rent Order] --> B2[Auto: rental_delivery appointment]
        B2 --> B3[Auto: rental_return appointment]
        
        C1[Create Tailoring Order] --> C2[Auto: tailoring_delivery appointment]
    end

    subgraph AppointmentLifecycle[Appointment Lifecycle]
        L1[scheduled] --> L2{Action}
        L2 -->|Complete| L3[completed]
        L2 -->|Cancel| L4[cancelled]
        L2 -->|No Show| L5[no_show]
    end

    subgraph Reminders[Reminder System]
        R1[24h Before] --> R2[Send Notification]
        R2 --> R3[Mark reminder_sent = true]
    end
```

---

## Data Flow Summary

```mermaid
flowchart LR
    subgraph Input[Data Input]
        I1[Orders]
        I2[Payments]
        I3[Expenses]
        I4[Custody]
        I5[Returns]
    end

    subgraph Processing[Processing Layer]
        P1[Order Controller]
        P2[Payment Controller]
        P3[Expense Controller]
        P4[Custody Controller]
        P5[Transaction Service]
    end

    subgraph Storage[Data Storage]
        S1[(Orders DB)]
        S2[(Payments DB)]
        S3[(Transactions DB)]
        S4[(Cashbox DB)]
        S5[(History DB)]
    end

    subgraph Output[Output/Reports]
        O1[Financial Reports]
        O2[Inventory Reports]
        O3[Client Reports]
        O4[Performance Reports]
    end

    I1 --> P1 --> S1
    I2 --> P2 --> S2
    I2 --> P5 --> S3
    I3 --> P3 --> S3
    I4 --> P4 --> S3
    I5 --> P1 --> S5
    
    P5 --> S4
    
    S1 --> O1
    S2 --> O1
    S3 --> O1
    S4 --> O1
    S1 --> O2
    S1 --> O3
    S5 --> O4
```

---

## Summary

These diagrams show:
1. **Current State**: What's already built
2. **Planned State**: What needs to be added
3. **Data Flows**: How data moves through the system
4. **Business Logic**: Order lifecycle, permissions, accounting rules

The most critical additions are:
1. **Accounting Module** - Cashbox + Transactions (immutable log)
2. **Permissions System** - Full RBAC
3. **Extended Rents** - Appointments/Scheduling
4. **Reports** - Business intelligence







