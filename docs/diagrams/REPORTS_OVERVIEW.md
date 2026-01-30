# Atelier Management System - Reports Overview

## 📊 Reports Module

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                              📊 REPORTS MODULE                                       │
└─────────────────────────────────────────────────────────────────────────────────────┘

┌───────────────────────┐  ┌───────────────────────┐  ┌───────────────────────┐
│   INVENTORY REPORTS   │  │  FINANCIAL REPORTS    │  │  PERFORMANCE REPORTS  │
│                       │  │                       │  │                       │
│ • Available Dresses   │  │ • Rental Profits      │  │ • Factory Evaluations │
│ • Out of Branch       │  │ • Tailoring Profits   │  │ • Employee Orders     │
│ • Overdue Returns     │  │ • Daily Cashbox       │  │ • Most Rented         │
│ • Most Rented         │  │ • Monthly Financial   │  │ • Most Sold           │
│ • Most Sold           │  │ • Expenses Breakdown  │  │                       │
│                       │  │ • Deposits Status     │  │                       │
│                       │  │ • Debts/Aging         │  │                       │
└───────────────────────┘  └───────────────────────┘  └───────────────────────┘
```

## Report Endpoints

### Inventory Reports

| Endpoint | Description | Filters |
|----------|-------------|---------|
| `GET /reports/available-dresses` | Clothes ready for rent | branch_id, category_id, cloth_type_id |
| `GET /reports/out-of-branch` | Currently rented out | branch_id |
| `GET /reports/overdue-returns` | Late returns | days_overdue |
| `GET /reports/most-rented` | Popular rental items | start_date, end_date, limit |
| `GET /reports/most-sold` | Best selling tailoring | start_date, end_date, limit |

### Financial Reports

| Endpoint | Description | Filters |
|----------|-------------|---------|
| `GET /reports/rental-profits` | Rental revenue breakdown | start_date, end_date, group_by |
| `GET /reports/tailoring-profits` | Tailoring revenue breakdown | start_date, end_date, group_by |
| `GET /reports/daily-cashbox` | Daily cash summary | date, branch_id |
| `GET /reports/monthly-financial` | Monthly overview | year, month |
| `GET /reports/expenses` | Expense breakdown | start_date, end_date, branch_id, category |
| `GET /reports/deposits` | Custody/deposit status | status |
| `GET /reports/debts` | Outstanding receivables | status, overdue_only |

### Performance Reports

| Endpoint | Description | Filters |
|----------|-------------|---------|
| `GET /reports/factory-evaluations` | Factory performance | factory_id, start_date, end_date |
| `GET /reports/employee-orders` | Orders per employee | start_date, end_date |

## Report Response Structures

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                        COMMON RESPONSE STRUCTURE                                     │
└─────────────────────────────────────────────────────────────────────────────────────┘

    {
      "period": {                    // Time range (if applicable)
        "start_date": "2025-01-01",
        "end_date": "2025-12-31",
        "grouped_by": "month"
      },
      "summary": {                   // Aggregate totals
        "total_count": 150,
        "total_amount": 50000.00,
        ...
      },
      "breakdown": [                 // Detailed data
        { "period": "2025-01", "count": 10, "amount": 5000 },
        ...
      ],
      "items": [                     // Individual records (if applicable)
        { "id": 1, "name": "...", ... },
        ...
      ],
      "generated_at": "2025-01-09T10:30:00Z"
    }
```

## Debts Report (Aging Analysis)

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                           DEBTS AGING ANALYSIS                                       │
└─────────────────────────────────────────────────────────────────────────────────────┘

    Response includes aging buckets:
    
    {
      "aging": {
        "current":      { "count": 5, "amount": 2000 },   // Not yet due
        "1_30_days":    { "count": 3, "amount": 1500 },   // 1-30 days overdue
        "31_60_days":   { "count": 2, "amount": 1000 },   // 31-60 days overdue
        "61_90_days":   { "count": 1, "amount": 500 },    // 61-90 days overdue
        "over_90_days": { "count": 1, "amount": 800 }     // 90+ days overdue
      },
      "top_debtors": [
        { "client_id": 5, "client_name": "John Doe", "total_owed": 1500 },
        ...
      ]
    }
```

## Profit Reports (Grouping Options)

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                          PROFIT REPORT GROUPING                                      │
└─────────────────────────────────────────────────────────────────────────────────────┘

    group_by parameter options:
    
    • "day"   → Daily breakdown (YYYY-MM-DD)
    • "week"  → Weekly breakdown (YYYY-WW)
    • "month" → Monthly breakdown (YYYY-MM) [default]
    
    Response:
    {
      "breakdown": [
        { "period": "2025-01", "rental_count": 45, "gross_revenue": 15000, ... },
        { "period": "2025-02", "rental_count": 52, "gross_revenue": 17500, ... },
        ...
      ]
    }
```

## Permission Requirements

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                          REPORT PERMISSIONS                                          │
└─────────────────────────────────────────────────────────────────────────────────────┘

    Permission              Required For
    ─────────────────────────────────────────────────
    reports.view            Inventory reports (basic)
    reports.financial       All financial reports
    reports.performance     Factory & employee reports
    
    Roles with access:
    • General Manager       All reports
    • Accountant            Financial reports
    • Factory Manager       Performance reports
```





