<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ChargeCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\MenuItemRequest;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** The menu: items and prices for food, laundry, transport and other services (FR-27). */
class MenuItemController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', MenuItem::class);

        return view('staff.menu.index', [
            'items' => MenuItem::query()->orderBy('name')->get()->groupBy(fn (MenuItem $i): string => $i->category->value),
            'categories' => ChargeCategory::cases(),
            'canEdit' => request()->user()->can('create', MenuItem::class),
        ]);
    }

    public function store(MenuItemRequest $request): RedirectResponse
    {
        $item = MenuItem::create($this->data($request));

        return redirect()->route('staff.menu.index')->with('status', "{$item->name} is on the menu.");
    }

    public function update(MenuItemRequest $request, MenuItem $item): RedirectResponse
    {
        $item->update($this->data($request));

        return redirect()->route('staff.menu.index')
            ->with('status', "{$item->name} is saved. Bills that already have it keep the old price.");
    }

    /** Hide the item for good. Old bills keep their lines (business rule 7). */
    public function destroy(Request $request, MenuItem $item): RedirectResponse
    {
        Gate::authorize('delete', $item);

        $item->delete();

        return redirect()->route('staff.menu.index')->with('status', "{$item->name} is removed from the menu.");
    }

    /** @return array<string, mixed> */
    private function data(MenuItemRequest $request): array
    {
        return [
            'name' => trim($request->validated('name')),
            'category' => $request->validated('category'),
            'price' => $request->validated('price'),
            'description' => $request->validated('description') ?: null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
