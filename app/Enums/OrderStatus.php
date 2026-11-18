<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending    = 'pending';
    case Confirmed  = 'confirmed';
    case Processing = 'processing';
    case Shipped    = 'shipped';
    case Delivered  = 'delivered';
    case Cancelled  = 'cancelled';

    /** Arabic label */
    public function labelAr(): string
    {
        return match($this) {
            self::Pending    => 'قيد الانتظار',
            self::Confirmed  => 'تم التأكيد',
            self::Processing => 'قيد التجهيز',
            self::Shipped    => 'تم الشحن',
            self::Delivered  => 'تم التسليم',
            self::Cancelled  => 'ملغي',
        };
    }

    /** English label */
    public function labelEn(): string
    {
        return match($this) {
            self::Pending    => 'Pending',
            self::Confirmed  => 'Confirmed',
            self::Processing => 'Processing',
            self::Shipped    => 'Shipped',
            self::Delivered  => 'Delivered',
            self::Cancelled  => 'Cancelled',
        };
    }

    /** Locale-aware label */
    public function label(): string
    {
        return app()->getLocale() === 'ar' ? $this->labelAr() : $this->labelEn();
    }

    /** Bootstrap badge colour for admin views */
    public function badgeColor(): string
    {
        return match($this) {
            self::Pending    => 'warning',
            self::Confirmed  => 'primary',
            self::Processing => 'info',
            self::Shipped    => 'secondary',
            self::Delivered  => 'success',
            self::Cancelled  => 'danger',
        };
    }

    /** Whether the customer can still request a return */
    public function canReturn(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Forward-only lifecycle: pending → confirmed → processing →
     * shipped → delivered, with cancel allowed from any state except
     * delivered. Delivered and cancelled are terminal — an order must
     * never move backwards (e.g. delivered → pending) or come back
     * from cancelled.
     */
    /**
     * Is moving from this status to $to allowed? Rules:
     * - delivered and cancelled are terminal — nothing moves out of them.
     * - cancel is allowed from any non-terminal state.
     * - any forward move along the lifecycle is allowed (admins may
     *   skip steps, e.g. mark a pending order delivered directly).
     * - moving backwards (e.g. shipped -> confirmed) is not allowed:
     *   it would corrupt the order timeline and the paid flag.
     */
    public function canTransitionTo(self $to): bool
    {
        if ($this === $to) {
            return true;
        }

        if ($this === self::Delivered || $this === self::Cancelled) {
            return false;
        }

        if ($to === self::Cancelled) {
            return true;
        }

        $order = [
            self::Pending->value => 0,
            self::Confirmed->value => 1,
            self::Processing->value => 2,
            self::Shipped->value => 3,
            self::Delivered->value => 4,
        ];

        return $order[$to->value] > $order[$this->value];
    }

    /** All cases as a select-box array [value => label] */
    public static function options(): array
    {
        return array_column(
            array_map(fn(self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases()),
            'label',
            'value'
        );
    }
}
