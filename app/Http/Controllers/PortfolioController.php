<?php

namespace App\Http\Controllers;

use App\Models\AccountMaster;
use App\Models\InvestmentTypeMaster;
use App\Models\Portfolio;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function index()
    {
        $portfolios = Portfolio::with(['account.user', 'investmentType'])->latest()->paginate(20);
        return view('portfolio.index', compact('portfolios'));
    }

    public function create()
    {
        $accounts = AccountMaster::with('user')->orderBy('acc_name')->get();
        $investmentTypes = InvestmentTypeMaster::where('status', '1')->orderBy('name')->get();
        return view('portfolio.create', compact('accounts', 'investmentTypes'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        Portfolio::create($data);
        return redirect()->route('portfolio.index')->with('success', 'Portfolio entry created successfully.');
    }

    public function edit(Portfolio $portfolio)
    {
        $accounts = AccountMaster::with('user')->orderBy('acc_name')->get();
        $investmentTypes = InvestmentTypeMaster::orderBy('name')->get();
        return view('portfolio.edit', compact('portfolio', 'accounts', 'investmentTypes'));
    }

    public function update(Request $request, Portfolio $portfolio)
    {
        $portfolio->update($this->validatedData($request));
        return redirect()->route('portfolio.index')->with('success', 'Portfolio entry updated successfully.');
    }

    public function destroy(Portfolio $portfolio)
    {
        $portfolio->delete();
        return redirect()->route('portfolio.index')->with('success', 'Portfolio entry deleted successfully.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'account_master_id' => 'required|exists:account_master,id',
            'investment_type_master_id' => 'required|exists:investment_type_master,id',
            'symbol' => 'nullable|string|max:255',
            'qty' => 'required|numeric|min:0',
            'investement_price' => 'required|numeric|min:0',
            'current_price' => 'required|numeric|min:0',
            'total_investment' => 'required|numeric|min:0',
            'total_PL' => 'required|numeric',
            'status' => 'required|in:0,1',
        ]);
    }
}
