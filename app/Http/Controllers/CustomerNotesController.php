<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreCustomerNoteRequest;
use App\Http\Requests\Customer\UpdateCustomerNoteRequest;
use App\Models\Customer;
use App\Models\CustomerNote;
use Illuminate\Http\RedirectResponse;

class CustomerNotesController extends Controller
{
    public function store(StoreCustomerNoteRequest $request, Customer $customer): RedirectResponse
    {
        $customer->notes()->create([...$request->validated(), 'status' => 'pending']);

        return back()->with('success', 'Catatan berhasil ditambahkan.');
    }

    public function update(UpdateCustomerNoteRequest $request, Customer $customer, CustomerNote $note): RedirectResponse
    {
        $note->update($request->validated());

        return back()->with('success', 'Catatan berhasil diperbarui.');
    }

    public function destroy(Customer $customer, CustomerNote $note): RedirectResponse
    {
        $note->delete();

        return back()->with('success', 'Catatan berhasil dihapus.');
    }
}
