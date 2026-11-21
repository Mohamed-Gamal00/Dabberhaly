<?php

namespace App\Http\Controllers\Inertia;

use App\Rules\EgyptianMobileNumber;

use App\Http\Controllers\Controller;
use App\Models\RepresentativesOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RepresentativeOrderController extends Controller
{
    public function index()
    {
        return Inertia::render('RepresentativeOrder/Index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'phone'       => ['required', 'string', new EgyptianMobileNumber, 'max:255'],
            'description' => 'required|string',
        ]);

        RepresentativesOrder::create($request->all());

        return redirect()->back()->with('success', 'تم إرسال طلبك بنجاح.');
    }
}
