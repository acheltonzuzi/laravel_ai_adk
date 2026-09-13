<?php

namespace App\Models;

use App\Enums\CarAvailability;
use App\Enums\CarCondition;
use Database\Factories\CarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $code
 * @property string $license_plate
 * @property string $brand
 * @property string $model
 * @property int $year
 * @property string|null $price
 * @property CarCondition $condition
 * @property string|null $location
 * @property CarAvailability $availability
 * @property string|null $image_path
 * @property string|null $image_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'license_plate', 'brand', 'model', 'year', 'price', 'condition', 'location', 'availability', 'image_path'])]
class Car extends Model
{
    /** @use HasFactory<CarFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'price' => 'decimal:2',
            'condition' => CarCondition::class,
            'availability' => CarAvailability::class,
        ];
    }

    /**
     * Register the model events.
     */
    protected static function booted(): void
    {
        static::creating(function (self $car): void {
            if (blank($car->code)) {
                $car->code = self::generateCode();
            }
        });

        static::deleting(function (self $car): void {
            $car->deleteImage();
        });
    }

    /**
     * Get the public URL of the car image, if it has one.
     *
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image_path === null
            ? null
            : Storage::disk('public')->url($this->image_path));
    }

    /**
     * Build the next sequential inventory code.
     *
     * The factory calls this directly because the seeder runs with model events
     * disabled, so the "creating" hook never fires during seeding.
     */
    public static function generateCode(): string
    {
        $next = (int) self::query()->max('id') + 1;

        do {
            $code = 'CAR-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * Normalize a license plate so differently formatted duplicates collide.
     */
    public static function normalizePlate(string $plate): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $plate));
    }

    /**
     * Delete the stored image file, if any.
     */
    public function deleteImage(): void
    {
        if ($this->image_path !== null) {
            Storage::disk('public')->delete($this->image_path);
        }
    }

    /**
     * Get the rentals booked for this car.
     *
     * @return HasMany<Rental, $this>
     */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /**
     * Scope the query to cars matching the given term.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('code', 'like', "%{$term}%")
            ->orWhere('license_plate', 'like', "%{$term}%")
            ->orWhere('brand', 'like', "%{$term}%")
            ->orWhere('model', 'like', "%{$term}%")
            ->orWhere('location', 'like', "%{$term}%")
        );
    }
}
