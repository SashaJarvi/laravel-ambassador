<?php

namespace App\Http\Controllers;

use App\Events\ProductUpdatedEvent;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{

    public function index()
    {
        return Product::all();
    }

    public function store(Request $request)
    {
        $product = Product::create($request->only('title', 'description', 'image', 'price'));

        event(new ProductUpdatedEvent);

        return response($product, Response::HTTP_CREATED);
    }

    public function show(Product $product)
    {
        return $product;
    }

    public function update(Request $request, Product $product)
    {
        $product->update($request->only('title', 'description', 'image', 'price'));

        event(new ProductUpdatedEvent);

        return response($product, Response::HTTP_ACCEPTED);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        event(new ProductUpdatedEvent);

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function frontend()
    {
        if ($products = \Cache::get('products_frontend')) {
            return $products;
        }

        $products = \Cache::remember('products_frontend', 30 * 60, fn() => Product::all());
        return $products;
    }

    public function backend(Request $request)
    {
        $page = $request->input('page', 1);

        /** @var Collection $products */
        $products = \Cache::remember('products_backend', 30 * 60, fn () =>  Product::all());
        $perPage = 9;

        if ($s = Str::lower($request->input('s'))) {
            $products = $products
                ->filter(fn (Product $product) => Str::contains(Str::lower($product->title), $s)
                    || Str::contains(Str::lower($product->description), $s));
        }

        $total = $products->count();

        if ($sort = $request->input('sort')) {
            $sortBy = $request->input('sortBy');
            $productFields = array_keys((Product::first())->getAttributes());

            if (!$sortBy || !in_array($sortBy, $productFields)) {
                $sortBy = 'price';
            }

            if ($sort === 'asc') {
                $products = $products->sortBy([
                    fn($a, $b) => $a[$sortBy] <=> $b[$sortBy]
                ]);
            } else if ($sort === 'desc') {
                $products = $products->sortBy([
                    fn($a, $b) => $b[$sortBy] <=> $a[$sortBy]
                ]);
            }
        }

        return [
            'data' => $products->forPage($page, $perPage)->values(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'last_page' => ceil($total / $perPage),
            ],
        ];
    }
}
