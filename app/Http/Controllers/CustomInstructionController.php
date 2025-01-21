<?php

namespace App\Http\Controllers;

use App\Models\CustomInstruction;
use Illuminate\Http\Request;

class CustomInstructionController extends Controller
{
  public function index()
  {
    $instructions = CustomInstruction::all();
    return response()->json($instructions);
  }

  public function store(Request $request)
  {
    $validated = $request->validate([
      'title' => 'required|string|max:255',
      'content' => 'required|string',
      'category' => 'required|string|max:100',
    ]);

    $instruction = CustomInstruction::create($validated);
    return response()->json($instruction, 201);
  }

  public function show($id)
  {
    $instruction = CustomInstruction::findOrFail($id);
    return response()->json($instruction);
  }

  public function update(Request $request, $id)
  {
    $instruction = CustomInstruction::findOrFail($id);

    $validated = $request->validate([
      'title' => 'string|max:255',
      'content' => 'string',
      'category' => 'string|max:100',
    ]);

    $instruction->update($validated);
    return response()->json($instruction);
  }

  public function destroy($id)
  {
    $instruction = CustomInstruction::findOrFail($id);
    $instruction->delete();
    return response()->json(null, 204);
  }
}
