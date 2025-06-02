<?php

namespace App\Http\Controllers;
use App\Models\Category;
use Inertia\Inertia;

use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Categories', ['categories' => Category::all()]);
    }

    public function createCategory(Request $request)
    {
        $category = new Category();
        $category->name = $request->name;
        $category->description = $request->description;
        $category->color = $request->color;
        $category->save();

        return response()->json($category);
    }

    public function deleteCategory(Request $request)    {
        $category = Category::find($request->id);
        $category->delete();
        
        return response()->json($category);
    }

    public function updateCategory(Request $request)
    {
        $category = Category::find($request->id);
        $category->name = $request->name;
        $category->description = $request->description;
        $category->color = $request->color;
        $category->save();

        return response()->json($category);
    }
}
