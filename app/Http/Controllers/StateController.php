<?php

namespace App\Http\Controllers;

use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StateController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $states = State::withCount(['customers', 'quotations', 'invoices'])
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('states.index', compact('states', 'search'));
    }

    public function create()
    {
        return view('states.create');
    }

    public function store(Request $request)
    {
        State::create($this->validateData($request));

        return redirect()->route('states.index')->with('success', 'State created successfully.');
    }

    public function edit(State $state)
    {
        return view('states.edit', [
            'state' => $state,
            'lockReason' => $state->lockReason(),
        ]);
    }

    public function update(Request $request, State $state)
    {
        $data = $this->validateData($request, $state->id);

        // Customers and documents store the state name as text, so a state
        // that is in use (or the GST home state) must keep its name and status.
        if ($reason = $state->lockReason()) {
            if ($data['name'] !== $state->name || $data['status'] !== $state->status) {
                return back()->withInput()->with('error', $reason);
            }
        }

        $state->update($data);

        return redirect()->route('states.index')->with('success', 'State updated successfully.');
    }

    public function destroy(State $state)
    {
        if ($reason = $state->lockReason()) {
            return back()->with('error', $reason);
        }

        $state->delete();

        return redirect()->route('states.index')->with('success', 'State deleted successfully.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('states', 'name')->ignore($ignoreId)],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
