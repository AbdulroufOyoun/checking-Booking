<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Client;

class Reservation extends Model
{
    use HasFactory;

    /** Guest physically in room (used by RoomOccupancyService and UI). */
    public const LOGEDIN_IN_HOUSE = 1;

    /** Guest not checked in yet (or checked out). */
    public const LOGEDIN_NOT_IN_HOUSE = 0;

    /** Draft / unconfirmed. */
    public const STATUS_UNCONFIRMED = 0;

    /** Confirmed active reservation. */
    public const STATUS_CONFIRMED = 1;

    /** Awaiting payment — still holds calendar visibility but excluded from accrual. */
    public const STATUS_PENDING_PAYMENT = 2;

    /** Cancelled or voided. */
    public const STATUS_CANCELLED = 3;

    public static function isCancelled(int $status): bool
    {
        return $status === self::STATUS_CANCELLED;
    }

    /** Statuses excluded from inventory overlap checks (pending + cancelled). */
    public static function nonBlockingInventoryStatuses(): array
    {
        return [self::STATUS_PENDING_PAYMENT, self::STATUS_CANCELLED];
    }

    /** Statuses included in cash / payment reports. */
    public static function cashReportStatuses(): array
    {
        return [self::STATUS_CONFIRMED, self::STATUS_PENDING_PAYMENT];
    }

    /** Statuses shown on operational reports (excludes pending + cancelled). */
    public static function operationalReportStatuses(): array
    {
        return [self::STATUS_UNCONFIRMED, self::STATUS_CONFIRMED];
    }

    protected $fillable = [
        'client_id',
        'start_date',
        'nights',
        'expire_date',
        'reservation_type',
        'reservation_status',
        'stay_reason_id',
        'reservation_source_id',
        'rent_type',
        'price_calculation_mode',
        'base_price',
        'discount',
        'extras',
        'penalties',
        'subtotal',
        'taxes',
        'total',
        'logedin',
        'login_time',
        'user_id',
    ];

    public $timestamps = true;

    /**
     * Get the rooms associated with this reservation (from reservation_rooms table)
     */
    public function reservationRooms(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    /**
     * Get the first room of this reservation (for backward compatibility)
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    function stay_reason(): BelongsTo
    {
        return $this->belongsTo(Stay_reason::class);
    }

    function reservation_source(): BelongsTo
    {
        return $this->belongsTo(Reservation_source::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReservationPay::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(ReservationExtend::class);
    }

    public function paidNetAmount(): float
    {
        if (!$this->relationLoaded('payments')) {
            $this->load('payments');
        }

        $paid = (float) $this->payments
            ->where('type', ReservationPay::TYPE_PAYMENT)
            ->sum('pay');
        $refunded = (float) $this->payments
            ->where('type', ReservationPay::TYPE_REFUND)
            ->sum('pay');

        return round($paid - $refunded, 2);
    }

    public function balanceDue(?float $total = null): float
    {
        $totalAmount = $total ?? (float) $this->total;

        return round(max(0, $totalAmount - $this->paidNetAmount()), 2);
    }
}
