<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToCartRequest;
use App\Models\CartItem;
use App\Http\Traits\ApiResponse;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ApiResponse;

    public function __construct(private CartService $cart) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->cart->getCart(
            auth('sanctum')->id(),
            $request->header('X-Session-Id', '')
        );

        return $this->success($items);
    }

    public function add(AddToCartRequest $request): JsonResponse
    {
        $item = $this->cart->addItem(
            $request->validated('pet_id'),
            auth('sanctum')->id(),
            $request->header('X-Session-Id', '')
        );

        return $this->created($item->load('pet.thumbnail'));
    }

    public function remove(Request $request, string $id): JsonResponse
    {
        $this->cart->removeItemById(
            $id,
            auth('sanctum')->id(),
            $request->header('X-Session-Id', '')
        );

        return $this->success(null, 'Item removed from cart.');
    }

    public function sync(Request $request): JsonResponse
    {
        $request->validate(['session_id' => ['required', 'string']]);

        $this->cart->mergeGuestCart(
            $request->input('session_id'),
            $request->user()->id
        );

        $items = $this->cart->getCart($request->user()->id, '');

        return $this->success($items);
    }
}
