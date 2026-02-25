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

    public function index(Request $request): JsonResponse
    {
        $items = $this->cart->getCart(
            auth('sanctum')->id(),
            $request->header('X-Session-Id', '')
        );

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function add(AddToCartRequest $request): JsonResponse
    {
        $item = $this->cart->addItem(
            $request->validated('pet_id'),
            auth('sanctum')->id(),
            $request->header('X-Session-Id', '')
        );

        return response()->json(['success' => true, 'data' => $item->load('pet.thumbnail')], 201);
    }

    public function remove(Request $request, string $id): JsonResponse
    {
        $this->cart->removeItemById(
            $id,
            auth('sanctum')->id(),
            $request->header('X-Session-Id', '')
        );

        return response()->json(['success' => true, 'message' => 'Item removed from cart.']);
    }

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
