<?php

namespace GetCandy\Http\Controllers\Api\Search;

use GetCandy\Api\Categories\Models\Category;
use GetCandy\Api\Products\Models\Product;
use GetCandy\Http\Controllers\Api\BaseController;
use GetCandy\Http\Requests\Api\Search\SearchRequest;
use GetCandy\Search\SearchContract;
use Illuminate\Http\Request;

class SearchController extends BaseController
{
    protected $types = [
        'product' => Product::class,
        'category' => Category::class
    ];

    /**
     * Performs a search against a type
     *
     * @param Request $request
     * @param SearchContract $client
     * 
     * @return Array
     */
    public function search(SearchRequest $request, SearchContract $client)
    {
        if (empty($this->types[$request->type])) {
            return $this->errorWrongArgs('Invalid type');
        }

        try {
            $results = $client
                ->language(app()->getLocale())
                ->against($this->types[$request->type])
                ->search($request->keywords, $request->filters);
        } catch (\Elastica\Exception\Connection\HttpException $e) {
            return $this->errorInternalError($e->getMessage());
        }

        $results = app('api')->search()->getResults(
            $results, $request->type, $request->page, $request->per_page ?: 50, $request->includes
        );
        return response($results, 200);
    }
}
