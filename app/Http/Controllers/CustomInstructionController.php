<?php

namespace App\Http\Controllers;

use App\Models\CustomInstruction;
use Illuminate\Http\Request;

class CustomInstructionController extends Controller
{
  public function index()
  {
    return CustomInstruction::where('user_id', auth()->id())
      ->orderBy('priority', 'desc')
      ->get();
  }

  public function store(Request $request)
  {
    $validated = $request->validate([
      'title' => 'required|string|max:255',
      'content' => 'required|string',
      'type' => 'required|in:general,tone,format,command',
      'priority' => 'required|integer|min:0',
      'is_active' => 'required|boolean',
    ]);

    $instruction = CustomInstruction::create([
      ...$validated,
      'user_id' => auth()->id(),
    ]);

    return response()->json($instruction, 201);
  }

  public function update(Request $request, $id)
  {
    $customInstruction = CustomInstruction::where('user_id', auth()->id())
      ->findOrFail($id);

    $validated = $request->validate([
      'title' => 'required|string|max:255',
      'content' => 'required|string',
      'type' => 'required|in:general,tone,format,command',
      'priority' => 'required|integer|min:0',
      'is_active' => 'required|boolean',
    ]);

    $customInstruction->update($validated);

    return response()->json($customInstruction->fresh());
  }

  public function destroy($id)
  {
    $customInstruction = CustomInstruction::where('user_id', auth()->id())
      ->findOrFail($id);

    $customInstruction->delete();
    return response()->noContent();
  }
}
