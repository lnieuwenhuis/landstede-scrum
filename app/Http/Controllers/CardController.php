<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Card;
use App\Models\Column;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CardController extends Controller
{
    public function addCardToColumn(Request $request, $columnId)
    {
        $user = parent::checkUserLogin();

        $column = Column::find($columnId);

        $title = $request->input('title');
        $description = $request->input('description');
        $points = $request->input('points');
        $categoryId = $request->input('categoryId');

        if (!$column) {
            return response()->json(['error' => 'Column not found']);
        }
        if ($column->status === 'locked') {
            return response()->json(['error' => 'Column is locked']);
        }

        $card = Card::factory()->create([
            'title' => $title,
            'description' => $description,
            'points' => $points,
            'category_id' => $categoryId,
            'user_id' => $user->id,
            'column_id' => $column->id,
        ]);

        $card->save();

        return response()->json(['message' => 'Card added to column', 'card' => $card]);
    }

    public function updateCard(Request $request, $cardId)
    {
        $user = parent::checkUserLogin();

        $card = Card::find($cardId);

        if (!$card) {
            return response()->json(['error' => 'Card not found']);
        }

        if ($card->column->status === 'locked') {
            return response()->json(['error' => 'Card is locked']);
        }

        // Explicitly update fields instead of using $request->all()
        $card->title = $request->input('title');
        $card->description = $request->input('description');
        $card->points = $request->input('points');
        $card->category_id = $request->input('categoryId');
        $card->save();

        // Return fresh instance from database
        return response()->json([
            'message' => 'Card updated',
            'card' => Card::find($cardId)
        ]);
    }

    public function deleteCard($cardId)
    {
        $user = parent::checkUserLogin();

        $card = Card::find($cardId);

        if (!$card) {
            return response()->json(['error' => 'Card not found']);
        }
        if ($card->column->status === 'locked') {
            return response()->json(['error' => 'Card is locked']);
        }

        $card->column()->dissociate();
        $card->delete();

        return response()->json(['message' => 'Card deleted successfully']);
    }

    public function moveCardToColumn(Request $request, $cardId)
    {
        $user = parent::checkUserLogin();

        $card = Card::find($cardId);

        if (!$card) {
            return response()->json(['error' => 'Card not found']);
        }

        $card->column()->dissociate();
        $card->column()->associate(Column::find($request->column_id));
        $card->status_updated_at = \Carbon\Carbon::now();
        $card->save();

        return response()->json(['message' => 'Card moved to column', 'card' => $card]);
    }

    public function assignUser(Request $request, Card $card): \Illuminate\Http\JsonResponse
    {
        $user = parent::checkUserLogin();

        // Add admin bypass check
        if ($user->role !== 'admin') {
            $board_users = $card->column->board->users;
            if (!$board_users->contains($user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to assign users to this card'
                ]);
            }
        }

        if ($card->column->status === 'locked') {
            return response()->json(['error' => 'Card is locked']);
        }
        
        $card->user_id = $request->user_id;
        $card->save();
        
        return response()->json([
            'success' => true,
            'message' => $request->user_id ? 'User assigned successfully' : 'User unassigned successfully',
            'card' => $card
        ]);
    }
}
