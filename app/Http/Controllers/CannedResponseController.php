<?php

namespace App\Http\Controllers;

use App\Http\Requests\CannedResponseRequest;
use App\Models\CannedResponse;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CannedResponseController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CannedResponse::class);

        return view('canned.index', [
            'responses' => CannedResponse::availableTo($request->user())->with(['user', 'department'])->orderBy('title')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CannedResponse::class);

        return view('canned.form', ['response' => new CannedResponse, 'departments' => Department::orderBy('name')->get()]);
    }

    public function store(CannedResponseRequest $request): RedirectResponse
    {
        CannedResponse::create([
            ...$request->safe()->only(['title', 'body', 'department_id']),
            'user_id' => $this->ownerId($request),
        ]);

        return redirect()->route('canned-responses.index')->with('success', 'Canned response saved.');
    }

    public function edit(CannedResponse $cannedResponse): View
    {
        $this->authorize('update', $cannedResponse);

        return view('canned.form', ['response' => $cannedResponse, 'departments' => Department::orderBy('name')->get()]);
    }

    public function update(CannedResponseRequest $request, CannedResponse $cannedResponse): RedirectResponse
    {
        $cannedResponse->update([
            ...$request->safe()->only(['title', 'body', 'department_id']),
            'user_id' => $this->ownerId($request),
        ]);

        return redirect()->route('canned-responses.index')->with('success', 'Canned response updated.');
    }

    public function destroy(CannedResponse $cannedResponse): RedirectResponse
    {
        $this->authorize('delete', $cannedResponse);
        $cannedResponse->delete();

        return redirect()->route('canned-responses.index')->with('success', 'Canned response deleted.');
    }

    /** Shared (null owner) only for users allowed to manage shared responses. */
    private function ownerId(CannedResponseRequest $request): ?int
    {
        return $request->boolean('shared') && $request->user()->can('canned.manage-shared') ? null : $request->user()->id;
    }
}
