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
    public function show(Request $request, Task $task)
    {
        if ($task->board->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Task not found'
            ], 404);
        }
        
        return response()->json([
            'task' => $task->load('board')
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Task $task)
    {
        if ($task->board->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Task not found'
            ], 404);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'done'])],
        ]);

        $task->update($request->only(['title', 'description', 'status']));

        return response()->json([
            'task' => $task,
            'message' => 'Task updated'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Task $task)
    {
        if ($task->board->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Task not found'
            ], 404);
        }

        Task::where('board_id', $task->board_id)
            ->where('status', $task->status)
            ->where('position', '>', $task->position)
            ->decrement('position');

        $task->delete();

        return response()->json([
            'message' => 'Task deleted'
        ]);
    }

    public function move(Request $request, Task $task)
    {
        if ($task->board->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Task not found'
            ], 404);
        }

        $request->validate([
            'status' => ['required', Rule::in(['todo', 'in_progress', 'done'])],
            'position' => 'required|integer|min:0',
        ]);

        $newStatus = $request->status;
        $newPosition = $request->position;
        $oldStatus = $task->status;
        $oldPosition = $task->position;

        if ($oldStatus === $newStatus && $oldPosition === $newPosition) {
            return response()->json([
                'task' => $task,
                'message' => 'Task position unchanged'
            ]);
        }

        // Start database transaction for data consistency
        \DB::transaction(function () use ($task, $newStatus, $newPosition, $oldStatus, $oldPosition) {
            if ($oldStatus === $newStatus) {
                if ($newPosition < $oldPosition) {
                    Task::where('board_id', $task->board_id)
                        ->where('status', $newStatus)
                        ->where('position', '>=', $newPosition)
                        ->where('position', '<', $oldPosition)
                        ->increment('position');
                } else {
                    Task::where('board_id', $task->board_id)
                        ->where('status', $newStatus)
                        ->where('position', '>', $oldPosition)
                        ->where('position', '<=', $newPosition)
                        ->decrement('position');
                }
            } else {
                Task::where('board_id', $task->board_id)
                    ->where('status', $oldStatus)
                    ->where('position', '>', $oldPosition)
                    ->decrement('position');

                Task::where('board_id', $task->board_id)
                    ->where('status', $newStatus)
                    ->where('position', '>=', $newPosition)
                    ->increment('position');
            }

            $task->update([
                'status' => $newStatus,
                'position' => $newPosition,
            ]);
        });

        return response()->json([
            'task' => $task->fresh(),
            'message' => 'Task moved successfully'
        ]);
    }
}
