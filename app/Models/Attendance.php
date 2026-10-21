<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

#[Fillable(['user_id', 'type', 'latitude', 'longitude', 'photo_path', 'notes'])]
class Attendance extends Model
{
    public const IN = 'in';
    public const OUT = 'out';

    protected $table = 'attendance';

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Attendance of the people whose records this user may see (same reach as onboardings, plus HR sees everyone). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->visibleAttendanceUserIds();

        return $ids === null ? $query : $query->whereIn('user_id', $ids);
    }

    /** Records whose local (display timezone) date falls between $from and $to, inclusive. */
    public function scopeBetweenLocalDates(Builder $query, ?string $from, ?string $to): Builder
    {
        $tz = config('app.display_timezone');

        return $query
            ->when($from, fn ($q, $v) => $q->where('created_at', '>=', Carbon::parse($v, $tz)->startOfDay()->utc()))
            ->when($to, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v, $tz)->endOfDay()->utc()));
    }

    /** The type the user's next punch must be: out while a clock-in is open, otherwise in. */
    public static function nextTypeFor(User $user): string
    {
        $last = static::where('user_id', $user->id)->latest('id')->value('type');

        return $last === self::IN ? self::OUT : self::IN;
    }

    public function label(): string
    {
        return $this->type === self::IN ? 'Clock in' : 'Clock out';
    }

    public function mapUrl(): string
    {
        return 'https://www.google.com/maps?q='.$this->latitude.','.$this->longitude;
    }

    public function localDate(): string
    {
        return local_time($this->created_at, 'Y-m-d');
    }

    /**
     * Group punches (oldest first) into one summary per user per local day:
     * first clock-in, last clock-out and the time worked between paired in/out punches.
     *
     * @param  Collection<int, Attendance>  $records
     * @return Collection<int, array>
     */
    public static function dailySummaries(Collection $records): Collection
    {
        return $records
            ->sortBy('created_at')
            ->groupBy(fn (Attendance $a) => $a->user_id.'|'.$a->localDate())
            ->map(function (Collection $punches) {
                $minutes = 0;
                $openIn = null;
                foreach ($punches as $p) {
                    if ($p->type === self::IN) {
                        $openIn = $p->created_at;
                    } elseif ($openIn) {
                        $minutes += $openIn->diffInMinutes($p->created_at);
                        $openIn = null;
                    }
                }

                $first = $punches->first();

                return [
                    'day' => $first->localDate(),
                    'name' => $first->user?->name,
                    'date' => Carbon::parse($first->localDate()),
                    'user' => $first->user,
                    'punches' => $punches->values(),
                    'first_in' => $punches->firstWhere('type', self::IN),
                    'last_out' => $punches->where('type', self::OUT)->last(),
                    'minutes' => $minutes,
                    'open' => $punches->last()->type === self::IN,
                ];
            })
            ->sortBy([['day', 'desc'], ['name', 'asc']])
            ->values();
    }
}
