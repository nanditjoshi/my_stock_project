<?php

namespace App\Http\Controllers;

use App\Models\InvestmentTypeMaster;
use Illuminate\Http\Request;

class InvestmentTypeMasterController extends Controller
{
    public function index()
    {
        $investmentTypes = InvestmentTypeMaster::withCount('portfolios')->orderBy('name')->paginate(20);
        return view('investment-types.index', compact('investmentTypes'));
    }

    public function create()
    {
        return view('investment-types.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:investment_type_master,name',
            'status' => 'required|in:0,1',
        ]);
        InvestmentTypeMaster::create($data);
        return redirect()->route('investment-types.index')->with('success', 'Investment type created successfully.');
    }

    public function edit(InvestmentTypeMaster $investmentType)
    {
        return view('investment-types.edit', compact('investmentType'));
    }

    public function update(Request $request, InvestmentTypeMaster $investmentType)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:investment_type_master,name,' . $investmentType->id,
            'status' => 'required|in:0,1',
        ]);
        $investmentType->update($data);
        return redirect()->route('investment-types.index')->with('success', 'Investment type updated successfully.');
    }

    public function destroy(InvestmentTypeMaster $investmentType)
    {
        if ($investmentType->portfolios()->exists()) {
            return redirect()->route('investment-types.index')->with('error', 'This type is used by portfolio entries. Remove those entries before deleting it.');
        }
        $investmentType->delete();
        return redirect()->route('investment-types.index')->with('success', 'Investment type deleted successfully.');
    }
}
