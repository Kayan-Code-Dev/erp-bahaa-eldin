<?php

namespace App\Services;

use App\Models\OrderHistory;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class OrderHistoryService
{
    /**
     * Log order creation
     */
    public function logCreated(Order $order, $user = null)
    {
        $user = $user ?? Auth::user();

        return OrderHistory::create([
            'order_id' => $order->id,
            'change_type' => 'created',
            'description' => "Order #{$order->id} created for client #{$order->client_id}",
            'changed_by' => $user->id ?? null,
        ]);
    }

    /**
     * Log order update
     */
    public function logUpdated(Order $order, $field = null, $oldValue = null, $newValue = null, $description = null, $user = null)
    {
        $user = $user ?? Auth::user();

        if (!$description) {
            if ($field) {
                $description = "Order #{$order->id} field '{$field}' changed from '{$oldValue}' to '{$newValue}'";
            } else {
                $description = "Order #{$order->id} updated";
            }
        }

        return OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => $field,
            'old_value' => $oldValue !== null ? (string)$oldValue : null,
            'new_value' => $newValue !== null ? (string)$newValue : null,
            'change_type' => 'updated',
            'description' => $description,
            'changed_by' => $user->id ?? null,
        ]);
    }

    /**
     * Log item addition
     */
    public function logItemAdded(Order $order, $clothId, $clothCode = null, $user = null)
    {
        $user = $user ?? Auth::user();
        $clothInfo = $clothCode ? " (Code: {$clothCode})" : "";

        return OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'items',
            'new_value' => $clothId,
            'change_type' => 'item_added',
            'description' => "Item added to order #{$order->id}: Cloth #{$clothId}{$clothInfo}",
            'changed_by' => $user->id ?? null,
        ]);
    }

    /**
     * Log item removal
     */
    public function logItemRemoved(Order $order, $clothId, $clothCode = null, $user = null)
    {
        $user = $user ?? Auth::user();
        $clothInfo = $clothCode ? " (Code: {$clothCode})" : "";

        return OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'items',
            'old_value' => $clothId,
            'change_type' => 'item_removed',
            'description' => "Item removed from order #{$order->id}: Cloth #{$clothId}{$clothInfo}",
            'changed_by' => $user->id ?? null,
        ]);
    }

    /**
     * Log item update
     */
    public function logItemUpdated(Order $order, $clothId, $field = null, $oldValue = null, $newValue = null, $user = null)
    {
        $user = $user ?? Auth::user();

        $description = "Item updated in order #{$order->id}: Cloth #{$clothId}";
        if ($field) {
            $description .= " - Field '{$field}' changed from '{$oldValue}' to '{$newValue}'";
        }

        return OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => $field ? "items.{$field}" : 'items',
            'old_value' => $oldValue !== null ? (string)$oldValue : null,
            'new_value' => $newValue !== null ? (string)$newValue : null,
            'change_type' => 'item_updated',
            'description' => $description,
            'changed_by' => $user->id ?? null,
        ]);
    }

    /**
     * Log status change
     */
    public function logStatusChanged(Order $order, $oldStatus, $newStatus, $user = null)
    {
        $user = $user ?? Auth::user();

        return OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'status',
            'old_value' => $oldStatus,
            'new_value' => $newStatus,
            'change_type' => 'status_changed',
            'description' => "Order #{$order->id} status changed from '{$oldStatus}' to '{$newStatus}'",
            'changed_by' => $user->id ?? null,
        ]);
    }

    /**
     * Log payment addition
     */
    public function logPaymentAdded(Order $order, $paymentId, $amount, $paymentType = 'normal', $user = null)
    {
        $user = $user ?? Auth::user();

        return OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'payments',
            'new_value' => $paymentId,
            'change_type' => 'payment_added',
            'description' => "Payment #{$paymentId} added to order #{$order->id}: {$amount} ({$paymentType})",
            'changed_by' => $user->id ?? null,
        ]);
    }

    /**
     * Log payment update
     */
    public function logPaymentUpdated(Order $order, $paymentId, $field = null, $oldValue = null, $newValue = null, $user = null)
    {
        $user = $user ?? Auth::user();

        $description = "Payment #{$paymentId} updated for order #{$order->id}";
        if ($field) {
            $description .= " - Field '{$field}' changed from '{$oldValue}' to '{$newValue}'";
        }

        return OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => $field ? "payments.{$field}" : 'payments',
            'old_value' => $oldValue !== null ? (string)$oldValue : null,
            'new_value' => $newValue !== null ? (string)$newValue : null,
            'change_type' => 'payment_updated',
            'description' => $description,
            'changed_by' => $user->id ?? null,
        ]);
    }

    /**
     * Log payment cancellation
     */
    public function logPaymentCanceled(Order $order, $paymentId, $user = null)
    {
        $user = $user ?? Auth::user();

        return OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'payments',
            'old_value' => $paymentId,
            'change_type' => 'payment_canceled',
            'description' => "Payment #{$paymentId} canceled for order #{$order->id}",
            'changed_by' => $user->id ?? null,
        ]);
    }

    /**
     * Log order delivery
     */
    public function logDelivered(Order $order, $user = null)
    {
        $user = $user ?? Auth::user();

        return OrderHistory::create([
            'order_id' => $order->id,
            'change_type' => 'delivered',
            'description' => "Order #{$order->id} marked as delivered",
            'changed_by' => $user->id ?? null,
        ]);
    }

    /**
     * Log item return
     */
    public function logItemReturned(Order $order, $clothId, $status, $user = null)
    {
        $user = $user ?? Auth::user();

        return OrderHistory::create([
            'order_id' => $order->id,
            'field_changed' => 'items',
            'new_value' => $clothId,
            'change_type' => 'item_returned',
            'description' => "Item #{$clothId} returned for order #{$order->id} with status '{$status}'",
            'changed_by' => $user->id ?? null,
        ]);
    }

    /**
     * Log order finishing
     */
    public function logFinished(Order $order, $user = null)
    {
        $user = $user ?? Auth::user();

        return OrderHistory::create([
            'order_id' => $order->id,
            'change_type' => 'finished',
            'description' => "Order #{$order->id} marked as finished",
            'changed_by' => $user->id ?? null,
        ]);
    }

    /**
     * Log order cancellation
     */
    public function logCanceled(Order $order, $user = null)
    {
        $user = $user ?? Auth::user();

        return OrderHistory::create([
            'order_id' => $order->id,
            'change_type' => 'canceled',
            'description' => "Order #{$order->id} canceled",
            'changed_by' => $user->id ?? null,
        ]);
    }
}






























