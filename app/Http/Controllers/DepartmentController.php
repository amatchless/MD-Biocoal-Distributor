<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\View\View;

/** Departments and who is in them, for the Super Admin. */
class DepartmentController extends Controller
{
    public function index(): View
    {
        $departments = Department::withCount('users')
            ->with(['users' => fn ($q) => $q->with(['roles', 'position'])->orderBy('name')])
            ->orderBy('name')
            ->get();

        $unassigned = User::whereNull('department_id')->count();

        return view('departments.index', compact('departments', 'unassigned'));
    }
}
