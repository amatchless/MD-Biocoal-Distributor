<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $base = fn () => Dealer::visibleTo($user);

        $stats = [
            'total' => $base()->count(),
            'this_month' => $base()->where('created_at', '>=', now()->startOfMonth())->count(),
            'last_7_days' => $base()->where('created_at', '>=', now()->subDays(7))->count(),
            'dealers' => $base()->where('partner_type', 'dealer')->count(),
            'distributors' => $base()->where('partner_type', 'distributor')->count(),
            'by_status' => $base()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
        ];

        $recent = $base()->with('creator:id,name')->latest()->limit(8)->get();

        $needsAttention = $user->isEmployee()
            ? $base()->where('status', 'needs_info')->latest('reviewed_at')->get()
            : $base()->whereIn('status', ['submitted', 'under_review'])->with('creator:id,name')->oldest()->limit(10)->get();

        $expiring = $base()
            ->where('status', '!=', 'rejected')
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('has_pesticide_licence', true)->whereDate('pesticide_licence_expiry', '<=', now()->addDays(60)))
                ->orWhere(fn ($w) => $w->where('has_fertilizer_licence', true)->whereDate('fertilizer_licence_expiry', '<=', now()->addDays(60))))
            ->get();

        $team = collect();
        if (! $user->isEmployee()) {
            $members = $user->isSuperAdmin()
                ? User::role([User::EMPLOYEE, User::MANAGER])->with(['manager:id,name', 'position'])->orderBy('name')->get()
                : $user->teamMembers()->with('position')->orderBy('name')->get();

            $counts = Dealer::query()
                ->whereIn('created_by', $members->pluck('id'))
                ->selectRaw("created_by,
                    count(*) as total,
                    sum(case when status = 'approved' then 1 else 0 end) as approved,
                    sum(case when status in ('submitted','under_review') then 1 else 0 end) as pending,
                    sum(case when status = 'needs_info' then 1 else 0 end) as needs_info,
                    sum(case when status = 'rejected' then 1 else 0 end) as rejected,
                    sum(case when created_at >= ? then 1 else 0 end) as this_month,
                    max(created_at) as last_at", [now()->startOfMonth()])
                ->groupBy('created_by')
                ->get()
                ->keyBy('created_by');

            $team = $members
                ->map(function (User $m) use ($counts) {
                    $c = $counts->get($m->id);
                    $m->stats = [
                        'total' => (int) ($c->total ?? 0),
                        'approved' => (int) ($c->approved ?? 0),
                        'pending' => (int) ($c->pending ?? 0),
                        'needs_info' => (int) ($c->needs_info ?? 0),
                        'rejected' => (int) ($c->rejected ?? 0),
                        'this_month' => (int) ($c->this_month ?? 0),
                        'last_at' => isset($c->last_at) ? \Illuminate\Support\Carbon::parse($c->last_at) : null,
                    ];

                    return $m;
                })
                ->filter(fn (User $m) => $m->isEmployee() || $m->stats['total'] > 0)
                ->sortByDesc(fn (User $m) => $m->stats['total'])
                ->values();
        }

        $mapPoints = $base()->latest()->limit(200)
            ->get(['id', 'ref_no', 'business_name', 'status', 'latitude', 'longitude'])
            ->map(fn (Dealer $d) => [
                'lat' => $d->latitude,
                'lng' => $d->longitude,
                'name' => $d->business_name,
                'ref' => $d->ref_no,
                'status' => $d->statusLabel(),
                'url' => route('dealers.show', $d),
            ]);

        return view('dashboard', compact('stats', 'recent', 'needsAttention', 'expiring', 'team', 'mapPoints'));
    }
}
