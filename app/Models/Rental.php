<?php

namespace App\Models;

use App\Enums\RentalStatus;
use Carbon\CarbonInterface;
use Database\Factories\RentalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $car_id
 * @property CarbonInterface $start_date
 * @property CarbonInterface $end_date
 * @property int $days
 * @property string $daily_rate
 * @property string $total
 * @property RentalStatus $status
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User $user
 * @property-read Car $car
 */
#[Fillable(['user_id', 'car_id', 'start_date', 'end_date', 'daily_rate', 'status'])]
class Rental extends Model
{
    /** @use HasFactory<RentalFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'integer',
            'daily_rate' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => RentalStatus::class,
        ];
    }

    /**
     * Register the model events.
     */
    protected static function booted(): void
    {
        static::saving(function (self $rental): void {
            $days = self::calculateDays($rental->start_date, $rental->end_date);

            $rental->days = $days;
            $rental->total = round($days * (float) $rental->daily_rate, 2);
        });
    }

    /**
     * Get the renter.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the rented car.
     *
     * @return BelongsTo<Car, $this>
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    /**
     * Count the inclusive number of days between two dates.
     *
     * A rental from Friday to Friday is 1 day, not 0 — an exclusive count would
     * read as "free on the first day". `diffInDays` is a signed, microsecond
     * interpolated float, so both ends are normalized to a plain calendar day
     * and the result is taken as an absolute UTC day count.
     */
    public static function calculateDays(CarbonInterface $start, CarbonInterface $end): int
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        return (int) $start->diffInDays($end, absolute: true, utc: true) + 1;
    }

    /**
     * Scope the query to rentals whose active period overlaps the given range
     * for the given car.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlapping(
        Builder $query,
        int $carId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $ignoreId = null,
    ): Builder {
        return $query
            ->where('car_id', $carId)
            ->whereIn('status', RentalStatus::activeValues())
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->when($ignoreId !== null, fn (Builder $q) => $q->where('id', '!=', $ignoreId));
    }
}
