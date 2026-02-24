<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToCartRequest;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    /**
     * GET /api/v1/cart — view cart contents (guest or authenticated).
     */
    public function index(Request $request): JsonResponse
    {
        $items = $this->cart->getCart(
            $request->user()?->id,
            $request->header('X-Session-Id', '')
        );

        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * POST /api/v1/cart/items — add a pet (locks for 15 min).
     */
    public function add(AddToCartRequest $request): JsonResponse
    {
        $item = $this->cart->addItem(
            $request->validated('pet_id'),
            $request->user()?->id,
            $request->header('X-Session-Id', '')
        );

        return response()->json(['success' => true, 'data' => $item->load('pet.thumbnail')], 201);
    }

    /**
     * DELETE /api/v1/cart/items/{id} — remove a CartItem by its own UUID.
     */
    public function remove(Request $request, string $id): JsonResponse
    {
        $this->cart->removeItemById(
            $id,
            $request->user()?->id,
            $request->header('X-Session-Id', '')
        );

        return response()->json(['success' => true, 'message' => 'Item removed from cart.']);
    }

    /**
     * PUT /api/v1/cart — merge guest session cart into the authenticated user's cart.
     * Call this immediately after login to carry over guest selections.
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate(['session_id' => ['required', 'string']]);

        $this->cart->mergeGuestCart(
            $request->input('session_id'),
            $request->user()->id
        );

        $items = $this->cart->getCart($request->user()->id, '');

        return response()->json(['success' => true, 'data' => $items]);
    }
}
