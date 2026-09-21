<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DesignationController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $designations = Designation::withCount('employees')
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('designations.index', compact('designations', 'search'));
    }

    public function create()
    {
        return view('designations.create');
    }

    public function store(Request $request)
    {
        Designation::create($this->validateData($request));

        return redirect()->route('designations.index')->with('success', 'Designation created successfully.');
    }

    public function edit(Designation $designation)
    {
        return view('designations.edit', compact('designation'));
    }

    public function update(Request $request, Designation $designation)
    {
        $designation->update($this->validateData($request, $designation->id));

        return redirect()->route('designations.index')->with('success', 'Designation updated successfully.');
    }

    public function destroy(Designation $designation)
    {
        if ($designation->employees()->exists()) {
            return back()->with('error', 'Cannot delete a designation assigned to employees. Mark it inactive instead.');
        }

        $designation->delete();

        return redirect()->route('designations.index')->with('success', 'Designation deleted successfully.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('designations', 'name')->ignore($ignoreId)],
            'status' => ['required', 'in:active,inactive'],
        ]);

        // Unchecked checkboxes are not submitted, so read it as a boolean.
        $data['can_login'] = $request->boolean('can_login');

        return $data;
    }
}
