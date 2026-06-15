# DropFlow SFM

Minimal supplier fulfillment for **Lokkisona** (dropshipper) and **Ex-A** (supplier).

Built in three steps only. No ERP, no accounting, no extra modules until each step is tested.

## Active build (now)

| Step | Module | Status |
|------|--------|--------|
| 1 | **Connection** | Active — live OpenCart read-only |
| 2 | Product Map | Waiting for Step 1 approval |
| 3 | Order Map | Waiting for Step 2 approval |

Hidden until later: Dispatch, Returns, Payables, Reports, Supplier admin.

## Setup

```bash
cd dropflow-sfm
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8018
```

Login: `admin@lokkisona.com` / `password`

## Step 1 — Connection (live test)

In `.env`:

```
DROPflow_OC_MOCK=false
DROPflow_MODULE_PRODUCT_MAP=false
DROPflow_MODULE_ORDER_MAP=false
```

1. Open **Connection** in the menu
2. Enter store URL, API token, endpoints, supplier filter
3. Click **Test Connection** — all **6 required** checks must pass:
   - Store online
   - Token valid
   - Product API
   - Order API
   - Order Status API
   - Supplier filter (assigned products)
   - Option images (optional — informational)
4. Click **Save connection** (enabled only after required checks pass)

## Step 2 — Product Map (after approval)

- Sync supplier warehouse products only (max 50/request)
- Local fields protected on refresh: cost, low warning, custom model, notes

## Step 3 — Order Map (after approval)

- Import orders only when product exists in Product Map
- Default OC statuses (New/Pending/Processing) → Internal New
- Simple workflow: New → Accepted → Packed → Dispatched

Database tables for future dispatch/returns/payables remain in place but UI is disabled.
