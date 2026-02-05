# Order Return Functions Documentation

This document explains the two return functions available in the Orders API.

---

## Overview

| Function | Endpoint | Purpose |
|----------|----------|---------|
| `returnItems` | `POST /api/v1/orders/{id}/return` | إرجاع عدة قطع بسرعة |
| `returnCloth` | `POST /api/v1/orders/{order_id}/items/{cloth_id}/return` | إرجاع قطعة واحدة مع نقل المخزون |

---

## Comparison Table

| Feature | `returnItems` | `returnCloth` |
|---------|---------------|---------------|
| **Number of items** | Multiple items (عدة قطع) | Single item (قطعة واحدة) |
| **Content Type** | `multipart/form-data` | `multipart/form-data` |
| **Required fields** | `cloth_id` only | `entity_type`, `entity_id`, `note`, `photos` |
| **Photo upload** | ✅ Yes (files, optional) | ✅ Yes (files, required) |
| **Inventory transfer** | ❌ No | ✅ Yes |
| **Destination entity** | ❌ Not required | ✅ Required |
| **Creates OrderReturn record** | ❌ No | ✅ Yes |
| **Photo storage** | Not stored | Stored in `storage/app/public/cloth-returns/` |

---

## 1. returnItems

### Endpoint
```
POST /api/v1/orders/{id}/return
```

### Description
Quick return of multiple rented items. Does NOT transfer inventory - items remain in their current inventory.

### Request Body (multipart/form-data)

Use these keys (examples):

```
items[0][cloth_id]: 1
items[0][notes]: "القطعة بحالة جيدة"
items[0][photos][0]: [FILE - photo1.jpg]
items[0][photos][1]: [FILE - photo2.jpg]

items[1][cloth_id]: 2
items[1][notes]: "يوجد خدش بسيط"
items[1][photos][0]: [FILE - photo3.jpg]
```

### Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `items` | array | ✅ | Array of items to return |
| `items.*.cloth_id` | integer | ✅ | معرف القطعة |
| `items.*.notes` | string | ❌ | ملاحظات الإرجاع |
| `items.*.photos` | array | ❌ | صور الإرجاع كملفات. Max: 10 |
| `items.*.photos.*` | file | ❌ | صورة (jpeg/png/gif/webp/bmp, max 5MB) |

### Response
```json
{
  "message": "Items returned successfully",
  "returned_items": [
    {
      "cloth_id": 1,
      "cloth_code": "CL-101",
      "cloth_name": "فستان أحمر",
      "notes": "القطعة بحالة جيدة",
      "photos": ["cloth-return-photos/cloth-return_10_5_20260205_ABC123.jpg"],
      "rent_id": 15
    }
  ],
  "order": { ... },
  "order_finished": true
}
```

### What happens internally
1. Validates each cloth belongs to the order
2. Validates each cloth is a rent item
3. Checks if cloth is returnable (`returnable = true`)
4. Finds active rent record
5. Updates cloth status to `repairing`
6. Marks cloth as not returnable (`returnable = false`)
7. Marks rent as `completed`
8. Logs return in order history
9. Auto-finishes order if all conditions met

### Inventory Behavior
- **NO inventory transfer**
- Cloth remains in its current inventory
- Only cloth status changes to `repairing`

---

## 2. returnCloth

### Endpoint
```
POST /api/v1/orders/{order_id}/items/{cloth_id}/return
```

### Description
Detailed return of a single item with photo documentation and inventory transfer.

### Request Body (multipart/form-data)
```
entity_type: "branch"
entity_id: 1
note: "تم الإرجاع مع ملاحظة وجود بقعة صغيرة"
photos[0]: [FILE - image1.jpg]
photos[1]: [FILE - image2.jpg]
```

### Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `entity_type` | string | ✅ | Destination: `branch`, `workshop`, `factory` |
| `entity_id` | integer | ✅ | Destination entity ID |
| `note` | string | ✅ | Return notes |
| `photos` | files | ✅ | 1-10 images (jpeg/png/gif/webp/bmp, max 5MB each) |

### Response
```json
{
  "success": true,
  "message": "تم إرجاع القطعة بنجاح | Cloth returned successfully",
  "data": {
    "cloth": { ... },
    "order_id": 10,
    "destination": {
      "type": "workshop",
      "id": 2,
      "name": "ورشة الصيانة",
      "inventory_id": 5
    },
    "photos_count": 3
  }
}
```

### What happens internally
1. Validates cloth belongs to order and is returnable
2. Validates order is not finished/canceled
3. Validates destination entity exists
4. Gets or creates destination inventory
5. Uploads and stores photos
6. Creates `ClothReturnPhoto` records
7. Marks cloth as not returnable (`returnable: false`)
8. Updates cloth status to `repairing`
9. **Transfers cloth to destination inventory**
10. Records history

### Inventory Behavior
- **Transfers cloth to destination inventory**
- Removes cloth from ALL current inventories
- Adds cloth to the specified entity's inventory

```php
// Remove from all inventories
DB::table('cloth_inventory')
    ->where('cloth_id', $cloth->id)
    ->delete();

// Add to destination inventory
DB::table('cloth_inventory')->insert([
    'cloth_id' => $cloth->id,
    'inventory_id' => $destinationInventory->id,
]);
```

---

## Use Case Examples

### Scenario 1: Quick return at same branch
Customer returns items at the same branch where they rented.

**Use:** `returnItems`
```json
POST /api/v1/orders/10/return
{
  "items": [
    { "cloth_id": 5, "notes": "تم الإرجاع بحالة ممتازة" }
  ]
}
```

---

### Scenario 2: Return with transfer to workshop
Customer returns a damaged item that needs repair at the workshop.

**Use:** `returnCloth`
```
POST /api/v1/orders/10/items/5/return
entity_type: "workshop"
entity_id: 2
note: "تحتاج تنظيف وإصلاح"
photos: [صور الحالة]
```

---

### Scenario 3: Return to different branch
Customer returns item at a different branch than where they rented.

**Use:** `returnCloth`
```
POST /api/v1/orders/10/items/5/return
entity_type: "branch"
entity_id: 3
note: "تم الإرجاع في فرع المعادي"
photos: [صور]
```

---

### Scenario 4: Return to factory for major repair
Item needs major repair at the factory.

**Use:** `returnCloth`
```
POST /api/v1/orders/10/items/5/return
entity_type: "factory"
entity_id: 1
note: "تحتاج إصلاح جذري"
photos: [صور الضرر]
```

---

## Decision Guide

| If you need to... | Use |
|-------------------|-----|
| Return multiple items quickly | `returnItems` |
| Keep items in same inventory | `returnItems` |
| Transfer item to workshop | `returnCloth` |
| Transfer item to different branch | `returnCloth` |
| Transfer item to factory | `returnCloth` |
| Document return with photos | `returnCloth` |
| Create formal return record | `returnCloth` |

---

## Error Codes

### returnItems Errors
| Error | Description |
|-------|-------------|
| 422 | Cloth not found in order |
| 422 | Cloth is not a rent item |
| 422 | No active rent found |

### returnCloth Errors
| Error Code | Description |
|------------|-------------|
| `ORDER_NOT_FOUND` | Order does not exist |
| `CLOTH_NOT_FOUND` | Cloth does not exist |
| `CLOTH_NOT_IN_ORDER` | Cloth is not part of this order |
| `CLOTH_NOT_RENTABLE` | Cloth is not a rental item |
| `CLOTH_ALREADY_RETURNED` | Cloth has already been returned |
| `ORDER_ALREADY_FINISHED` | Cannot return from finished order |
| `ORDER_CANCELED` | Cannot return from canceled order |
| `DESTINATION_NOT_FOUND` | Destination entity not found |
| `PHOTO_UPLOAD_FAILED` | Failed to upload photos |
| `VALIDATION_ERROR` | Invalid input data |

---

## Summary

- **`returnItems`**: Fast, simple, multiple items, no inventory transfer
- **`returnCloth`**: Detailed, single item, with photos, transfers inventory

Choose based on whether you need to transfer the cloth to a different location or just mark it as returned.

