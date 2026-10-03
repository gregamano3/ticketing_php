<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Lookups;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Generic CRUD for the lookup tables described in App\Support\Lookups. */
class LookupController extends Controller
{
    public function index(string $type): View
    {
        $def = Lookups::get($type);

        $items = $def['model']::query()
            ->with($def['with'] ?? [])
            ->withCount($def['counts'] ?? [])
            ->orderBy($def['order'] ?? 'name')
            ->get();

        return view('admin.lookups.index', compact('type', 'def', 'items'));
    }

    public function create(string $type): View
    {
        $def = Lookups::get($type);
        $item = new $def['model'];
        foreach ($def['fields'] as $name => $field) {
            if (array_key_exists('default', $field)) {
                $item->{$name} = $field['default'];
            }
        }

        return view('admin.lookups.form', compact('type', 'def', 'item'));
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $def = Lookups::get($type);
        $item = new $def['model'];
        $this->save($request, $def, $item);

        return redirect()->route('admin.lookups.index', $type)->with('success', ucfirst($def['singular']).' created.');
    }

    public function edit(string $type, int $id): View
    {
        $def = Lookups::get($type);
        $item = $def['model']::findOrFail($id);

        return view('admin.lookups.form', compact('type', 'def', 'item'));
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $def = Lookups::get($type);
        $this->save($request, $def, $def['model']::findOrFail($id));

        return redirect()->route('admin.lookups.index', $type)->with('success', ucfirst($def['singular']).' updated.');
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $def = Lookups::get($type);

        $item = $def['model']::findOrFail($id);

        try {
            // Savepoint, so a foreign key violation does not poison an outer transaction.
            DB::transaction(fn () => $item->delete());
        } catch (QueryException) {
            return back()->with('error', 'This '.$def['singular'].' is still in use and cannot be deleted.');
        }

        return back()->with('success', ucfirst($def['singular']).' deleted.');
    }

    private function save(Request $request, array $def, Model $item): void
    {
        $rules = collect($def['fields'])->mapWithKeys(fn ($f, $name) => [$name => ($f['rules'])($item->getKey())])->all();
        $data = $request->validate($rules);

        foreach ($def['fields'] as $name => $field) {
            if ($field['type'] === 'checkbox') {
                $data[$name] = $request->boolean($name);
            }
        }

        DB::transaction(function () use ($def, $item, $data) {
            // Only one row may carry a "default" style flag.
            foreach ($def['fields'] as $name => $field) {
                if (! empty($field['unique_flag']) && $data[$name]) {
                    $def['model']::query()->whereKeyNot($item->getKey() ?? 0)->update([$name => false]);
                }
            }

            $item->forceFill($data)->save();
        });
    }
}
