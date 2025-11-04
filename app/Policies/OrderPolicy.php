<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->can('view orders');
    }

    public function view(User $user, Order $order)
    {
        // Users can view their own orders, admins can view all
        return $user->id === $order->user_id || $user->can('view all orders');
    }

    public function create(User $user)
    {
        return $user->can('create orders');
    }

    public function updateStatus(User $user, Order $order)
    {
        // Only admin can update order status
        return $user->can('update order status');
    }

    public function delete(User $user, Order $order)
    {
        // Users can cancel their own orders, admins can delete any
        return ($user->id === $order->user_id && $order->status === 'pending') ||
               $user->can('delete orders');
    }

    public function restore(User $user, Order $order)
    {
        return $user->can('restore orders');
    }

    public function forceDelete(User $user, Order $order)
    {
        return $user->can('force delete orders');
    }
}
