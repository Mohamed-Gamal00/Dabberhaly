<?php

namespace App\Http\Controllers\dashboard;

use App\Helper\Helper;
use App\Http\Controllers\Controller;
use App\Models\StoreFatuer;
use App\Repositories\store_featuers\StoreRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;

class StoreFatuerController extends Controller
{
  protected $storeRepository;

  public function __construct(StoreRepository $storeRepository)
  {
    $this->storeRepository = $storeRepository;
  }

  public function index()
  {
    Gate::authorize('store_feature.view');
    $featuers = $this->storeRepository->getfeatuers();
    return view('dashboard.store_featuers.index', compact('featuers'));
  }

  public function create()
  {
    Gate::authorize('store_feature.create');
    return view('dashboard.store_featuers.create');
  }

  public function store(Request $request)
  {
    Gate::authorize('store_feature.create');
    $data = $request->validate([
      'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
      'title' => 'required|string|max:255',
      'title_en' => 'nullable|string|max:255',
      'description' => 'nullable|string|max:255',
    ]);
    $data['request'] = $request; // Pass request object for image upload

    $this->storeRepository->store($data);
    return to_route('store_featuers.index')->with('success', 'تم اضافة ميزة ب نجاح');
  }

  public function edit(string $id)
  {
    Gate::authorize('store_feature.edit');
    $featuer = $this->storeRepository->getById($id);
    return view('dashboard.store_featuers.edit', compact('featuer'));
  }

  public function update(Request $request, string $id)
  {
    Gate::authorize('store_feature.edit');
    $data = $request->validate([
      'title' => 'required|string|max:255',
      'title_en' => 'nullable|string|max:255',
      'description' => 'nullable|string|max:255',
    ]);
    $data['request'] = $request; // Pass request object for image upload

    $this->storeRepository->update($id, $data);
    return to_route('store_featuers.index')->with('success', 'تم التعديل بنجاح');
  }

  public function destroy(string $id)
  {
    Gate::authorize('store_feature.delete');
    $this->storeRepository->delete($id);
    return back()->with('dark', 'تم الحذف ينجاح');
  }
}
