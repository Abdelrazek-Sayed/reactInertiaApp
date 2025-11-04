<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderItemPolicy
{
    use HandlesAuthorization;

    public function addItem(User $user, Order $order)
    {
        // Only allow adding items to pending orders
        return $user->id === $order->user_id && $order->status === 'pending';
    }

    public function updateItem(User $user, Order $order, OrderItem $item)
    {
        // Only allow updating items in pending orders
        return $user->id === $order->user_id &&
               $order->status === 'pending' &&
               $item->order_id === $order->id;
    }

    public function removeItem(User $user, Order $order, OrderItem $item)
    {
        // Only allow removing items from pending orders
        return $user->id === $order->user_id &&
               $order->status === 'pending' &&
               $item->order_id === $order->id;
    }
}
