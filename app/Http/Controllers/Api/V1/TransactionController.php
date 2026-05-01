<?php

namespace App\Http\Controllers\Api\V1;

use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetTransactionsRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\TransactionResource;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(GetTransactionsRequest $request)
    {
        $transactions = Transaction::with('customer', 'items.product')
            ->search($request->search)
            ->latest()
            ->paginate($request->limit ?? 10);

        return ApiResponse::success(
            new PaginatedResource($transactions, TransactionResource::class),
            'Transaction List'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request)
    {
        try {
            $transaction = DB::transaction(function () use ($request) {
                $subtotal = 0;
                $itemsData = [];

                foreach ($request->items as $item) {
                    $product = Product::lockForUpdate()->find($item['product_id']);

                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("Insufficient stock for product: {$product->name}. Available: {$product->stock}, Requested: {$item['quantity']}");
                    }

                    $itemSubtotal = $product->price * $item['quantity'];
                    $subtotal += $itemSubtotal;

                    $itemsData[] = [
                        'product_id' => $product->id,
                        'price' => $product->price,
                        'quantity' => $item['quantity'],
                        'subtotal' => $itemSubtotal,
                    ];

                    $product->decrement('stock', $item['quantity']);
                }

                $tax = $subtotal * 0.11;
                $total = $subtotal + $tax;

                $transaction = Transaction::create([
                    'code' => $this->generateCode(),
                    'customer_id' => $request->customer_id,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                ]);

                $transaction->items()->createMany($itemsData);

                return $transaction;
            });

            return ApiResponse::success(
                new TransactionResource($transaction->load('customer', 'items.product')),
                'Transaction successfully created',
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $transaction = Transaction::with('customer', 'items.product')->find($id);

        if (!$transaction) {
            return ApiResponse::error(
                'Transaction not found',
                Response::HTTP_NOT_FOUND
            );
        }

        return ApiResponse::success(
            new TransactionResource($transaction),
            'Transaction Details'
        );
    }

    /**
     * Generate unique transaction code.
     */
    private function generateCode(): string
    {
        $prefix = 'TRX-' . strtoupper(Str::random(10));
    // Menghasilkan 6 karakter acak kombinasi huruf dan angka
    $random = strtoupper(Str::random(6)); 

    return "{$prefix}-{$random}";
    }
}
