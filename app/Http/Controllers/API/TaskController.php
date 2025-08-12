<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $boardId = $request->query('board_id');

        if (!$boardId) {
            return response()->json([
                'message' => 'Board ID required'
            ]);
        }

        $board = Board::where('id', $boardId)->where('user_id', $request->user()->id)->first();

        if (!$board) {
            return response()->json([
                'message' => 'Board not found'
            ], 404);
        }

        $tasks = Task::where('board_id', $boardId)->orderBy('status')->orderBy('position')->get();

        $groupedTasks = [
            'todo' => $tasks->where('status', 'todo')->values(),
            'in_progress' => $tasks->where('status', 'in_progress')->values(),
            'done' => $tasks->where('status', 'done')->values(),
        ];

        return response()->json([
            'tasks' => $groupedTasks
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'board_id' => 'required|exists:boards,id',
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'done'])],
        ]);

        $board = Board::where('id', $request->board_id)->where('user_id', $request->user()->id)->first();

        if (!$board) {
            return response()->json([
                'message' => 'Board not found'
            ], 404);
        }

        $status = $request->status ?? 'todo';
        $maxPosition = Task::where('board_id', $request->board_id)->where('status', $status)->max('position') ?? -1;

        $task = Task::create([
            'title' => $request->title,
            'description' => $request->description,
            'board_id' => $request->board_id,
            'status' => $status,
            'position' => $maxPosition + 1,
        ]);

        return response()->json([
            'task' => $task,
            'message' => 'Task created'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
