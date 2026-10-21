<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    /** Clock in or out. The server decides the time; the next punch must alternate in → out → in. */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'type' => ['required', Rule::in([Attendance::IN, Attendance::OUT])],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'image', 'max:8192'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'latitude.required' => 'Your location is needed to mark attendance. Turn on Location / GPS and try again.',
        ]);

        if ($data['type'] !== Attendance::nextTypeFor($user)) {
            throw ValidationException::withMessages(['type' => $data['type'] === Attendance::IN
                ? 'You are already clocked in. Clock out first.'
                : 'You are not clocked in yet. Clock in first.']);
        }

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'notes' => $data['notes'] ?? null,
            'photo_path' => $request->file('photo')?->store('attendance', 'public'),
        ]);

        $message = ($attendance->type === Attendance::IN ? 'Clocked in' : 'Clocked out').' at '.local_time($attendance->created_at, 'h:i A').'.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'type' => $attendance->type]);
        }

        return back()->with('status', $message);
    }

    /** The signed-in user's own attendance for the last 30 days. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $from = now(config('app.display_timezone'))->subDays(29)->toDateString();

        $records = Attendance::where('user_id', $user->id)
            ->betweenLocalDates($from, null)
            ->with('user:id,name')
            ->get();

        return view('attendance.index', [
            'days' => Attendance::dailySummaries($records),
        ]);
    }

    /** Managers see the people reporting to them; super admins see everyone. */
    public function team(Request $request): View
    {
        $user = $request->user();
        $tz = config('app.display_timezone');

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'employee' => ['nullable', 'integer'],
            'department' => ['nullable', 'integer'],
        ]) + [
            'from' => now($tz)->subDays(6)->toDateString(),
            'to' => now($tz)->toDateString(),
        ];

        $records = Attendance::visibleTo($user)
            ->betweenLocalDates($filters['from'], $filters['to'])
            ->when($filters['employee'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($user->isSuperAdmin() ? ($filters['department'] ?? null) : null,
                fn ($q, $v) => $q->whereHas('user', fn ($u) => $u->where('department_id', $v)))
            ->with('user:id,name,department_id,position_id', 'user.position', 'user.department:id,name')
            ->limit(5000)
            ->get();

        $summaries = Attendance::dailySummaries($records);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $days = new LengthAwarePaginator($summaries->forPage($page, 30)->values(), $summaries->count(), 30, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $employees = $user->isSuperAdmin()
            ? User::role([User::EMPLOYEE, User::MANAGER])->orderBy('name')->get(['id', 'name'])
            : $user->teamMembers()->orderBy('name')->get(['id', 'name'])->prepend($user);

        $departments = $user->isSuperAdmin() ? Department::orderBy('name')->get(['id', 'name']) : collect();

        $clockedInNow = Attendance::visibleTo($user)
            ->whereIn('id', Attendance::visibleTo($user)->selectRaw('max(id)')->groupBy('user_id'))
            ->where('type', Attendance::IN)
            ->where('created_at', '>=', now($tz)->startOfDay()->utc())
            ->count();

        return view('attendance.team', compact('days', 'filters', 'employees', 'departments', 'clockedInNow'));
    }

    public function photo(Request $request, Attendance $attendance): StreamedResponse
    {
        $ids = $request->user()->visibleCreatorIds();
        abort_unless($ids === null || in_array($attendance->user_id, $ids, true), 403);
        abort_unless($attendance->photo_path && Storage::disk('public')->exists($attendance->photo_path), 404);

        return Storage::disk('public')->response($attendance->photo_path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
