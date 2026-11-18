<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Repositories\Order\ReturnOrderRepository;

class ReturnOrderController extends Controller
{
    protected $returnOrderRepository;

    public function __construct(ReturnOrderRepository $repo)
    {
        $this->returnOrderRepository = $repo;
    }

    public function index()
    {
        $orders = $this->returnOrderRepository->getAll();

        return view('dashboard.return_order.index', compact('orders'));
    }

    public function show(string $id)
    {
        $order = $this->returnOrderRepository->show($id);

        return view('dashboard.return_order.show', compact('order'));
    }

    public function destroy(string $id)
    {
        $this->returnOrderRepository->delete($id);

        return redirect()->back()->with('dark', __('messages.RETURN_ORDER_DELETE'));
    }
}
