<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BoardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $boards = $request->user()->boards()->with('tasks')->latest()->get();

        return response()->json(['boards' => $boards]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $board = $request->user()->boards()->create(['name' => $request->name]);

        $board->load('tasks');

        return response()->json([
            'board' => $board,
            'message' => 'Board created'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Board $board)
    {
        if ($board->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Board not found'], 404);
        }

        $board->load(['tasks' => function($query) {
            $query->orderBy('position');
        }]);

        return response()->json([
            'board' => $board
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Board $board)
    {
        if ($board->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Board not found'
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $board->update([
            'name' => $request->name,
        ]);

        $board->load('tasks');

        return response()->json([
            'board' => $board,
            'message' => 'Board updated'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Board $board)
    {
        if ($board->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Board not found'
            ], 404);
        }

        $board->delete();

        return response()->json([
            'message' => 'Board deleted'
        ]);
    }
}
