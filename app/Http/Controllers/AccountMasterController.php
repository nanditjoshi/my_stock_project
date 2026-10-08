<?php

namespace App\Http\Controllers;

use App\Models\AccountMaster;
use App\Models\User;
use Illuminate\Http\Request;

class AccountMasterController extends Controller
{
    public function index()
    {
        $accounts = AccountMaster::with('user')->latest()->paginate(20);
        return view('accounts.index', compact('accounts'));
    }

    public function create()
    {
        $users = User::orderBy('name')->get();
        return view('accounts.create', compact('users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'acc_name' => 'required|string|max:255',
            'acc_number' => 'nullable|string|max:255',
            'status' => 'required|in:0,1',
        ]);
        AccountMaster::create($data);
        return redirect()->route('accounts.index')->with('success', 'Account created successfully.');
    }

    public function edit(AccountMaster $account)
    {
        $users = User::orderBy('name')->get();
        return view('accounts.edit', compact('account', 'users'));
    }

    public function update(Request $request, AccountMaster $account)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'acc_name' => 'required|string|max:255',
            'acc_number' => 'nullable|string|max:255',
            'status' => 'required|in:0,1',
        ]);
        $account->update($data);
        return redirect()->route('accounts.index')->with('success', 'Account updated successfully.');
    }

    public function destroy(AccountMaster $account)
    {
        $account->delete();
        return redirect()->route('accounts.index')->with('success', 'Account and its portfolio entries deleted.');
    }
}
