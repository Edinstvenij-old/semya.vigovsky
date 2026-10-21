<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShoppingItem;
use App\Support\FamilyContext;
use App\Support\Live;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** One shared shopping list per family: whoever is in the shop ticks items off for everyone. */
class ShoppingController extends Controller
{
    private const WITH = ['adder:id,name,avatar_color', 'doer:id,name'];

    public function __construct(private FamilyContext $context) {}

    private function changed(): void
    {
        Live::emit($this->context->id(), 'shopping');
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'open' => ShoppingItem::with(self::WITH)->where('is_done', false)->latest('id')->get(),
            // Recently bought stays visible for a week, so "did anyone buy milk?" has an answer.
            'done' => ShoppingItem::with(self::WITH)->where('is_done', true)
                ->where('done_at', '>=', now()->subDays(7))->latest('done_at')->limit(50)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:200'],
            'quantity' => ['nullable', 'string', 'max:40'],
        ]);

        $item = ShoppingItem::create($data + ['added_by' => $request->user()->id]);
        $this->changed();

        return response()->json($item->load(self::WITH), 201);
    }

    public function update(Request $request, ShoppingItem $item): JsonResponse
    {
        $data = $request->validate([
            'text' => ['sometimes', 'string', 'max:200'],
            'quantity' => ['sometimes', 'nullable', 'string', 'max:40'],
            'is_done' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_done', $data) && $data['is_done'] !== $item->is_done) {
            $data += $data['is_done']
                ? ['done_by' => $request->user()->id, 'done_at' => now()]
                : ['done_by' => null, 'done_at' => null];
        }

        $item->update($data);
        $this->changed();

        return response()->json($item->load(self::WITH));
    }

    public function destroy(ShoppingItem $item): JsonResponse
    {
        $item->delete();
        $this->changed();

        return response()->json(['ok' => true]);
    }

    public function clearDone(): JsonResponse
    {
        ShoppingItem::where('is_done', true)->delete();
        $this->changed();

        return response()->json(['ok' => true]);
    }
}
