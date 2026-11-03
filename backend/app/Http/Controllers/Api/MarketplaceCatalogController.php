<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MktProduct;
use App\Models\MktCategory;
use Illuminate\Http\Request;

class MarketplaceCatalogController extends Controller
{
    public function categories()
    {
        $categories = MktCategory::with('children')->whereNull('parent_id')->orderBy('sort_order')->get();
        return response()->json($categories);
    }

    public function index(Request $request)
    {
        $request->validate([
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', 'in:price_asc,price_desc'],
        ]);

        $marketplaceId = $request->marketplace->id;

        $query = MktProduct::whereHas('marketplaces', function($q) use ($marketplaceId) {
            $q->where('marketplaces.id', $marketplaceId)
              ->where('marketplace_product.is_visible', true);
        })->where('active', true)->with('categories');

        if ($request->has('category_id')) {
            $categoryId = $request->category_id;
            $query->where(function($q) use ($categoryId) {
                // Compatible with old and new structure temporarily
                $q->where('category_id', $categoryId)
                  ->orWhereHas('categories', function($subQ) use ($categoryId) {
                      $subQ->where('mkt_categories.id', $categoryId);
                  });
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        if ($request->input('sort') === 'price_asc') {
            $query->orderBy('price')->orderBy('id');
        } elseif ($request->input('sort') === 'price_desc') {
            $query->orderByDesc('price')->orderBy('id');
        }

        return response()->json($query->paginate(24));
    }

    public function show(Request $request, $id)
    {
        $marketplaceId = $request->marketplace->id;
        
        $product = MktProduct::whereHas('marketplaces', function($q) use ($marketplaceId) {
            $q->where('marketplaces.id', $marketplaceId)
              ->where('marketplace_product.is_visible', true);
        })->with('categories')->findOrFail($id);

        return response()->json($product);
    }
}
