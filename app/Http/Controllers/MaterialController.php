<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Rules\HsnCode;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $materials = Material::when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('hsn_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('materials.index', compact('materials', 'search'));
    }

    public function create()
    {
        return view('materials.create');
    }

    public function store(Request $request)
    {
        Material::create($this->validateData($request));

        return redirect()->route('materials.index')->with('success', 'Material created successfully.');
    }

    public function edit(Material $material)
    {
        return view('materials.edit', compact('material'));
    }

    public function update(Request $request, Material $material)
    {
        $material->update($this->validateData($request, $material->id));

        return redirect()->route('materials.index')->with('success', 'Material updated successfully.');
    }

    public function destroy(Material $material)
    {
        // Quotation lines can use a material, so it cannot be deleted while one does.
        // (When Purchase Orders are added, block that case here too.)
        if ($material->quotationItems()->exists()) {
            return back()->with('error', 'Cannot delete a material used in quotations. Mark it inactive instead.');
        }

        $material->delete();

        return redirect()->route('materials.index')->with('success', 'Material deleted successfully.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', 'unique:materials,code' . ($ignoreId ? ",{$ignoreId}" : '')],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit' => ['required', 'string', 'max:20'],
            'hsn_code' => ['required', new HsnCode],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
