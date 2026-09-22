<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search'));
        $vendors = Vendor::query()
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('gst_number', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('vendors.index', compact('vendors', 'search'));
    }

    public function create()
    {
        return view('vendors.create');
    }

    public function store(Request $request)
    {
        Vendor::create($this->validatedData($request));

        return redirect()->route('vendors.index')->with('success', 'Vendor created successfully.');
    }

    public function edit(Vendor $vendor)
    {
        return view('vendors.edit', compact('vendor'));
    }

    public function update(Request $request, Vendor $vendor)
    {
        $vendor->update($this->validatedData($request));

        return redirect()->route('vendors.index')->with('success', 'Vendor updated successfully.');
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();

        return redirect()->route('vendors.index')->with('success', 'Vendor deleted successfully.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'mobile' => ['required', 'regex:/^[0-9+() -]{7,15}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'gst_number' => ['nullable', 'regex:/^[0-9A-Z]{15}$/'],
        ]);

        $data['gst_number'] = $data['gst_number'] ? strtoupper($data['gst_number']) : null;

        return $data;
    }
}
