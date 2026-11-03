<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MktCategory;
use Illuminate\Http\Request;

class MktCategoryAdminController extends Controller
{
    public function index()
    {
        $categories = MktCategory::with('children')->whereNull('parent_id')->orderBy('sort_order')->get();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:mkt_categories',
            'parent_id' => 'nullable|exists:mkt_categories,id',
            'sort_order' => 'integer'
        ]);

        $category = MktCategory::create($data);
        return response()->json($category, 201);
    }

    public function show($id)
    {
        $category = MktCategory::findOrFail($id);
        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        $category = MktCategory::findOrFail($id);
        $data = $request->validate([
            'name' => 'string|max:255',
            'slug' => 'string|max:255|unique:mkt_categories,slug,' . $category->id,
            'parent_id' => 'nullable|exists:mkt_categories,id',
            'sort_order' => 'integer'
        ]);

        $category->update($data);
        return response()->json($category);
    }

    public function destroy($id)
    {
        $category = MktCategory::findOrFail($id);
        $category->delete();
        return response()->json(null, 204);
    }
}

