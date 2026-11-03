<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Marketplace;
use App\Http\Requests\StoreMarketplaceRequest;
use Illuminate\Http\Request;

class MarketplaceAdminController extends Controller
{
    public function index()
    {
        $marketplaces = Marketplace::withCount([
            'products',
            'users',
            'quotes',
            'quotes as pending_quotes_count' => function ($query) {
                $query->whereIn('status', ['pending', 'unread']);
            }
        ])
        ->addSelect([
            'latest_quote_at' => \App\Models\MktQuote::select('created_at')
                ->whereColumn('marketplace_id', 'marketplaces.id')
                ->latest()
                ->limit(1)
        ])
        ->orderBy('name')
        ->get();

        return response()->json($marketplaces);
    }

    public function store(StoreMarketplaceRequest $request)
    {
        $data = $request->validated();
        if (empty($data['dealer_email']) && !empty($data['quote_email'])) {
            $data['dealer_email'] = $data['quote_email'];
        }
        $marketplace = Marketplace::create($data);
        return response()->json($marketplace, 201);
    }

    public function show($id)
    {
        $marketplace = Marketplace::with('products')->findOrFail($id);
        return response()->json($marketplace);
    }

    public function update(StoreMarketplaceRequest $request, $id)
    {
        $marketplace = Marketplace::findOrFail($id);
        $data = $request->validated();
        
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if (empty($data['dealer_email']) && !empty($data['quote_email'])) {
            $data['dealer_email'] = $data['quote_email'];
        }
        
        $marketplace->update($data);
        return response()->json($marketplace);
    }

    public function destroy($id)
    {
        $marketplace = Marketplace::findOrFail($id);
        $marketplace->update(['active' => !$marketplace->active]); // Toggle instead of delete
        return response()->json($marketplace);
    }

    public function syncProducts(Request $request, $id)
    {
        $request->validate([
            'product_ids' => 'array',
            'product_ids.*' => 'exists:mkt_products,id'
        ]);

        $marketplace = Marketplace::findOrFail($id);
        
        // Prepare pivot data (all visible)
        $syncData = [];
        $productIds = $request->input('product_ids', []);
        
        foreach ($productIds as $pid) {
            $syncData[$pid] = ['is_visible' => true];
        }

        $marketplace->products()->sync($syncData);

        return response()->json(['message' => 'Productos sincronizados exitosamente']);
    }

    public function uploadAsset(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp,svg|max:5120'
        ]);

        $file = $request->file('image');
        $fileName = \Illuminate\Support\Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('marketplace/assets', $fileName, 'public');

        return response()->json([
            'url' => asset("storage/{$path}")
        ]);
    }
}

