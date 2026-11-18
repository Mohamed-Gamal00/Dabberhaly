<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\HeaderText;
use http\Header;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HeaderTextController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('header_text.view');
        $headerText = HeaderText::all();
        return view('dashboard.front_settings.header.index', \compact('headerText'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('header_text.create');
        return view('dashboard.front_settings.header.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('header_text.create');
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable'
        ]);

        HeaderText::create($request->all());

        return to_route('header_text.index')->with('success', 'تم اضافة النص بنجاح');


    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        Gate::authorize('header_text.view');
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        Gate::authorize('header_text.edit');
        $headerText = HeaderText::findOrFail($id);
        return view('dashboard.front_settings.header.edit', \compact('headerText'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        Gate::authorize('header_text.edit');
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable'
        ]);

        $headerText = HeaderText::findOrFail($id);
        $headerText->update($request->all());

        if ($headerText->wasChanged()) {
            return to_route('header_text.index')->with('success', 'تم اضافة النص بنجاح');
        }
        return back()->with('danger', 'لم يتم تحديث النص لعد وجود أي تغيير');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Gate::authorize('header_text.delete');
        $headerText = HeaderText::findOrFail($id);
        $headerText->delete();
        return back()->with('dark', 'تم حذف النص بنجاح');


    }
}
