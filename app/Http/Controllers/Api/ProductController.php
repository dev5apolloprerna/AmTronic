<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = $data['search'] ?? null;
        $products = Product::query()
            ->select(['id', 'name', 'code', 'description', 'unit', 'hsn_code', 'status'])
            ->where('status', 'active')
            ->when($search, fn ($query) => $query->where(fn ($product) => $product
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $products]);
    }
}
