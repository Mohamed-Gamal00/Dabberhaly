<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\RepresentativesOrder;
use App\Repositories\representatives_orders\RepresentativesOrdersRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RepresentativesOrderController extends Controller
{
  protected $representativesOrders;

  public function __construct(RepresentativesOrdersRepository $representativesOrders)
  {
    $this->representativesOrders = $representativesOrders;
  }

  /**
   * Display a listing of the resource.
   */
  public function index()
  {
    Gate::authorize('representative_order.view');
    $orders = $this->representativesOrders->getAll();
    return view('dashboard.representatives_orders.index', compact('orders'));
  }

  /**
   * Show the form for creating a new resource.
   */
  public function create()
  {
    Gate::authorize('representative_order.create');
    return view('dashboard.representatives_orders.create');
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {
    Gate::authorize('representative_order.create');
    $request->validate([
      'name' => 'required|string|max:255',
      'phone' => 'required|string|max:255',
      'description' => 'required|string',
    ]);

    $this->representativesOrders->store($request->all());

    return redirect()->route('representatives_orders.index')
    ->with('success', 'Order created successfully.');
  }

  /**
   * Display the specified resource.
   */
  public function show(string $id)
  {
    Gate::authorize('representative_order.view');
    $data = $this->representativesOrders->show($id);
    return view('dashboard.representatives_orders.show', compact('data'));
  }

  /**
   * Show the form for editing the specified resource.
   */
  public function edit($id)
  {
    Gate::authorize('representative_order.edit');
    $order = $this->representativesOrders->getAll()->find($id);
    return view('dashboard.representatives_orders.edit', compact('order'));
  }



  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, $id)
  {
    Gate::authorize('representative_order.edit');
    $request->validate([
      'name' => 'required|string|max:255',
      'phone' => 'required|string|max:255',
      'description' => 'required|string',
    ]);

    $this->representativesOrders->update($request->all(), $id);

    return redirect()->route('representatives_orders.index')
    ->with('success', 'Order updated successfully.');
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy($id)
  {
    Gate::authorize('representative_order.delete');
    $this->representativesOrders->delete($id);

    return redirect()->route('representatives_orders.index')
    ->with('success', 'Order deleted successfully.');
  }
}
