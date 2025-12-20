<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Tests\Fixtures;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class SampleController extends Controller
{
    /**
     * Display a listing of users.
     *
     * Returns a paginated list of all users in the system.
     */
    public function index(): JsonResponse
    {
        return response()->json(['users' => []]);
    }

    /**
     * Store a newly created user.
     */
    public function store(SampleFormRequest $request): JsonResponse
    {
        return response()->json(['user' => $request->validated()]);
    }

    /**
     * Display the specified user.
     */
    public function show(int $id): JsonResponse
    {
        return response()->json(['user' => ['id' => $id]]);
    }

    /**
     * Update the specified user.
     */
    public function update(SampleFormRequest $request, int $id): JsonResponse
    {
        return response()->json(['user' => $request->validated()]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(int $id): JsonResponse
    {
        return response()->json(['deleted' => true]);
    }
}
