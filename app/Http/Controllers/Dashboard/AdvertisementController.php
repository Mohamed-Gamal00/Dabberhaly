<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\Advertisement\AdvertisementRequest;
use App\Models\Advertisement;
use App\Repositories\Advertisement\AdvertisementRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdvertisementController extends Controller
{

  protected $advertisementRepository;

  public function __construct(AdvertisementRepository $advertisementRepository)
  {
    $this->advertisementRepository = $advertisementRepository;
  }


  public function index()
  {
    Gate::authorize('advertisement.view');
    $advertisements = $this->advertisementRepository->getMainAdvertisemnt();
    return view('dashboard.front_settings.advertisement.index', compact('advertisements'));
  }

  public function create(Advertisement $advertisement)
  {
    Gate::authorize('advertisement.create');
    return view('dashboard.front_settings.advertisement.create', compact('advertisement'));
  }

  public function store(AdvertisementRequest $request)
  {
    Gate::authorize('advertisement.create');
    $data = $request->validated();

    if (isset($data['is_active']) && $data['is_active'] === 'on') 
    {
      $data['is_active'] = true;
    }
    else {
      $data['is_active'] = false;
    }
    // dd($data);
    $this->advertisementRepository->store($data);

    return redirect()->route('advertisements.index')
      ->with('success', __('messages.ADVERTISEMENT_CREATED') );
  }

  public function edit(Advertisement $advertisement)
  {
    Gate::authorize('advertisement.edit');
    return view('dashboard.front_settings.advertisement.edit', compact('advertisement'));
  }

  public function update(AdvertisementRequest $request, Advertisement $advertisement)
  {
    Gate::authorize('advertisement.edit');
    $data = $request->validated();
    if (isset($data['is_active']) && $data['is_active'] === 'on') {
      $data['is_active'] = true;
    } else {
      $data['is_active'] = false;
    }

    $this->advertisementRepository->update($data, $advertisement->id);

    return redirect()->route('advertisements.index')
      ->with('success', __('messages.ADVERTISEMENT_UPDATED'));
  }

  public function destroy($id)
  {
    Gate::authorize('advertisement.delete');
    $this->advertisementRepository->delete($id);
    return redirect()->route('advertisements.index')
      ->with('success', __('messages.ADVERTISEMENT_DELETED'));
  }

  public function toggle(Advertisement $advertisement)
  {
    Gate::authorize('advertisement.edit');
    $advertisement->update(['is_active' => !$advertisement->is_active]);
    return redirect()->back()->with('success',
      $advertisement->is_active ? 'تم تفعيل الإعلان' : 'تم إيقاف الإعلان'
    );
  }
}
