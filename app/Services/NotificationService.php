<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Rent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Custody;
use App\Models\Receivable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * NotificationService
 * 
 * Service for creating and managing notifications.
 */
class NotificationService
{
    /**
     * Create a notification
     */
    public function create(
        ?User $user,
        string $type,
        string $title,
        string $message,
        array $options = []
    ): Notification {
        return Notification::create([
            'user_id' => $user?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'reference_type' => $options['reference_type'] ?? null,
            'reference_id' => $options['reference_id'] ?? null,
            'priority' => $options['priority'] ?? Notification::PRIORITY_NORMAL,
            'action_url' => $options['action_url'] ?? null,
            'metadata' => $options['metadata'] ?? null,
            'scheduled_for' => $options['scheduled_for'] ?? null,
            'sent_at' => $options['send_immediately'] ?? true ? now() : null,
        ]);
    }

    /**
     * Create notification for all users with a specific role
     */
    public function createForRole(
        string $roleName,
        string $type,
        string $title,
        string $message,
        array $options = []
    ): int {
        $users = User::whereHas('roles', function ($q) use ($roleName) {
            $q->where('name', $roleName);
        })->get();

        $count = 0;
        foreach ($users as $user) {
            $this->create($user, $type, $title, $message, $options);
            $count++;
        }

        return $count;
    }

    /**
     * Send notification to users with a specific role (alias for createForRole)
     */
    public function sendToRole(
        string $roleName,
        string $type,
        string $title,
        string $message,
        ?string $referenceType = null,
        ?int $referenceId = null,
        string $priority = Notification::PRIORITY_NORMAL,
        ?string $actionUrl = null
    ): int {
        return $this->createForRole($roleName, $type, $title, $message, [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'priority' => $priority,
            'action_url' => $actionUrl,
        ]);
    }

    /**
     * Notify factory users about new order
     */
    public function notifyFactoryOrderNew(Order $order, \App\Models\Factory $factory): int
    {
        $factoryUsers = \App\Models\FactoryUser::where('factory_id', $factory->id)
            ->where('is_active', true)
            ->with('user')
            ->get();

        $count = 0;
        foreach ($factoryUsers as $factoryUser) {
            $this->create(
                $factoryUser->user,
                Notification::TYPE_FACTORY_ORDER_NEW,
                'New Order Received',
                "A new tailoring order (Order #{$order->id}) has been assigned to your factory.",
                [
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'priority' => Notification::PRIORITY_HIGH,
                    'action_url' => "/factory/orders/{$order->id}",
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Notify factory users about order modification
     */
    public function notifyFactoryOrderModified(Order $order, \App\Models\Factory $factory): int
    {
        $factoryUsers = \App\Models\FactoryUser::where('factory_id', $factory->id)
            ->where('is_active', true)
            ->with('user')
            ->get();

        $count = 0;
        foreach ($factoryUsers as $factoryUser) {
            $this->create(
                $factoryUser->user,
                Notification::TYPE_FACTORY_ORDER_MODIFIED,
                'Order Modified',
                "Tailoring order #{$order->id} has been modified.",
                [
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'priority' => Notification::PRIORITY_NORMAL,
                    'action_url' => "/factory/orders/{$order->id}",
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Send delivery reminder to factory users (2 days before expected delivery)
     */
    public function notifyFactoryDeliveryReminder(Order $order, \App\Models\Factory $factory): int
    {
        $factoryUsers = \App\Models\FactoryUser::where('factory_id', $factory->id)
            ->where('is_active', true)
            ->with('user')
            ->get();

        $count = 0;
        foreach ($factoryUsers as $factoryUser) {
            $this->create(
                $factoryUser->user,
                Notification::TYPE_FACTORY_DELIVERY_REMINDER,
                'Delivery Reminder',
                "Order #{$order->id} is due for delivery in 2 days.",
                [
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'priority' => Notification::PRIORITY_HIGH,
                    'action_url' => "/factory/orders/{$order->id}",
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Create appointment reminder
     */
    public function createAppointmentReminder(Rent $appointment, int $hoursBeforeDefault = 24): ?Notification
    {
        if (!$appointment->delivery_date) {
            return null;
        }

        $appointmentTime = Carbon::parse($appointment->delivery_date);
        if ($appointment->appointment_time) {
            $timeParts = explode(':', $appointment->appointment_time);
            $appointmentTime->setTime($timeParts[0] ?? 0, $timeParts[1] ?? 0);
        }

        // Schedule reminder for X hours before
        $reminderTime = $appointmentTime->copy()->subHours($hoursBeforeDefault);

        // Don't create if reminder time is in the past
        if ($reminderTime->isPast()) {
            return null;
        }

        $client = $appointment->client;
        $clientName = $client ? $client->first_name . ' ' . $client->last_name : 'Unknown Client';

        // Create for relevant users (reception, branch managers)
        $users = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['general_manager', 'reception_employee']);
        })->get();

        $notification = null;
        foreach ($users as $user) {
            $notification = $this->create(
                $user,
                Notification::TYPE_APPOINTMENT_REMINDER,
                'Upcoming Appointment',
                "Appointment with {$clientName} scheduled for {$appointmentTime->format('M d, Y H:i')}",
                [
                    'reference_type' => Rent::class,
                    'reference_id' => $appointment->id,
                    'priority' => Notification::PRIORITY_HIGH,
                    'action_url' => "/appointments/{$appointment->id}",
                    'scheduled_for' => $reminderTime,
                    'send_immediately' => false,
                    'metadata' => [
                        'client_id' => $client?->id,
                        'client_name' => $clientName,
                        'appointment_type' => $appointment->appointment_type,
                    ],
                ]
            );
        }

        return $notification;
    }

    /**
     * Create overdue return notification
     */
    public function createOverdueReturnNotification(Rent $rent): Notification
    {
        $client = $rent->client;
        $clientName = $client ? $client->first_name . ' ' . $client->last_name : 'Unknown Client';
        $daysOverdue = Carbon::parse($rent->return_date)->diffInDays(now());

        // Notify all managers
        $users = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['general_manager', 'reception_employee']);
        })->get();

        $notification = null;
        foreach ($users as $user) {
            $notification = $this->create(
                $user,
                Notification::TYPE_OVERDUE_RETURN,
                'Overdue Return Alert',
                "Rental by {$clientName} is {$daysOverdue} days overdue",
                [
                    'reference_type' => Rent::class,
                    'reference_id' => $rent->id,
                    'priority' => $daysOverdue > 7 ? Notification::PRIORITY_URGENT : Notification::PRIORITY_HIGH,
                    'action_url' => "/appointments/{$rent->id}",
                    'metadata' => [
                        'client_id' => $client?->id,
                        'days_overdue' => $daysOverdue,
                        'return_date' => $rent->return_date,
                    ],
                ]
            );
        }

        return $notification;
    }

    /**
     * Create payment due notification
     */
    public function createPaymentDueNotification(Receivable $receivable): Notification
    {
        $client = $receivable->client;
        $clientName = $client ? $client->first_name . ' ' . $client->last_name : 'Unknown Client';
        $daysUntilDue = Carbon::parse($receivable->due_date)->diffInDays(now(), false);

        $priority = Notification::PRIORITY_NORMAL;
        if ($daysUntilDue < 0) {
            // Overdue
            $priority = Notification::PRIORITY_HIGH;
        } elseif ($daysUntilDue <= 3) {
            $priority = Notification::PRIORITY_HIGH;
        }

        // Notify accountants
        $users = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['general_manager', 'accountant']);
        })->get();

        $notification = null;
        foreach ($users as $user) {
            $notification = $this->create(
                $user,
                Notification::TYPE_PAYMENT_DUE,
                'Payment Due Reminder',
                "Payment of {$receivable->remaining_amount} from {$clientName} is due",
                [
                    'reference_type' => Receivable::class,
                    'reference_id' => $receivable->id,
                    'priority' => $priority,
                    'action_url' => "/receivables/{$receivable->id}",
                    'metadata' => [
                        'client_id' => $client?->id,
                        'amount' => $receivable->remaining_amount,
                        'due_date' => $receivable->due_date,
                    ],
                ]
            );
        }

        return $notification;
    }

    /**
     * Create order status change notification
     */
    public function createOrderStatusNotification(Order $order, string $oldStatus, string $newStatus): Notification
    {
        $client = $order->client;
        $clientName = $client ? $client->first_name . ' ' . $client->last_name : 'Unknown Client';

        // Notify relevant users
        $users = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['general_manager', 'reception_employee', 'sales_employee']);
        })->get();

        $notification = null;
        foreach ($users as $user) {
            $notification = $this->create(
                $user,
                Notification::TYPE_ORDER_STATUS,
                'Order Status Updated',
                "Order #{$order->id} for {$clientName} changed from {$oldStatus} to {$newStatus}",
                [
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'priority' => Notification::PRIORITY_NORMAL,
                    'action_url' => "/orders/{$order->id}",
                    'metadata' => [
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'client_id' => $client?->id,
                    ],
                ]
            );
        }

        return $notification;
    }

    /**
     * Create tailoring stage change notification
     */
    public function createTailoringStageNotification(Order $order, ?string $fromStage, string $toStage): Notification
    {
        $client = $order->client;
        $clientName = $client ? $client->first_name . ' ' . $client->last_name : 'Unknown Client';
        
        $stages = Order::getTailoringStages();
        $fromLabel = $fromStage ? ($stages[$fromStage] ?? $fromStage) : 'None';
        $toLabel = $stages[$toStage] ?? $toStage;

        $priority = $toStage === Order::STAGE_READY_FOR_CUSTOMER 
            ? Notification::PRIORITY_HIGH 
            : Notification::PRIORITY_NORMAL;

        // Notify factory managers and reception
        $users = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['general_manager', 'factory_manager', 'reception_employee']);
        })->get();

        $notification = null;
        foreach ($users as $user) {
            $notification = $this->create(
                $user,
                Notification::TYPE_TAILORING_STATUS,
                'Tailoring Stage Updated',
                "Order #{$order->id} for {$clientName}: {$fromLabel} → {$toLabel}",
                [
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'priority' => $priority,
                    'action_url' => "/orders/{$order->id}",
                    'metadata' => [
                        'from_stage' => $fromStage,
                        'to_stage' => $toStage,
                        'client_id' => $client?->id,
                    ],
                ]
            );
        }

        return $notification;
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::forUser($user->id)->unread()->undismissed()->count();
    }

    /**
     * Mark all as read for user
     */
    public function markAllAsRead(User $user): int
    {
        return Notification::forUser($user->id)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Dismiss all for user
     */
    public function dismissAll(User $user): int
    {
        return Notification::forUser($user->id)
            ->undismissed()
            ->update(['dismissed_at' => now()]);
    }

    /**
     * Process scheduled notifications (for cron job)
     */
    public function processScheduledNotifications(): int
    {
        $notifications = Notification::readyToSend()->whereNull('sent_at')->get();

        foreach ($notifications as $notification) {
            $notification->markAsSent();
            // Here you could also trigger push notifications, emails, etc.
        }

        return $notifications->count();
    }

    /**
     * Generate daily overdue notifications (for cron job)
     */
    public function generateDailyOverdueNotifications(): int
    {
        $count = 0;

        // Overdue rentals
        $overdueRents = Rent::whereIn('status', ['scheduled', 'confirmed', 'in_progress'])
            ->whereIn('appointment_type', ['rental_return', 'rental_delivery'])
            ->where('return_date', '<', now())
            ->get();

        foreach ($overdueRents as $rent) {
            // Check if notification already sent today
            $existingToday = Notification::ofType(Notification::TYPE_OVERDUE_RETURN)
                ->forReference(Rent::class, $rent->id)
                ->whereDate('created_at', today())
                ->exists();

            if (!$existingToday) {
                $this->createOverdueReturnNotification($rent);
                $count++;
            }
        }

        // Overdue receivables
        $overdueReceivables = Receivable::where('status', 'overdue')
            ->orWhere(function ($q) {
                $q->whereIn('status', ['pending', 'partial'])
                  ->where('due_date', '<', now());
            })
            ->get();

        foreach ($overdueReceivables as $receivable) {
            $existingToday = Notification::ofType(Notification::TYPE_PAYMENT_DUE)
                ->forReference(Receivable::class, $receivable->id)
                ->whereDate('created_at', today())
                ->exists();

            if (!$existingToday) {
                $this->createPaymentDueNotification($receivable);
                $count++;
            }
        }

        return $count;
    }
}





