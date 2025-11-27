<?php

namespace STS\Http\Controllers\Api\Admin;

use STS\Http\Controllers\Controller;
use STS\Models\DeleteAccountRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get a list of all delete account requests, sorted by date (newest first).
     */
    public function accountDeleteList(): JsonResponse
    {
        $requests = DeleteAccountRequest::with('user:id,name,email')
            ->orderBy('date_requested', 'desc')
            ->get();

        return response()->json(['data' => $requests]);
    }

    /**
     * Update the status of a delete account request.
     * Sets action_taken and action_taken_date to current date.
     */
    public function accountDeleteUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:delete_account_requests,id',
            'action_taken' => [
                'required',
                'integer',
                Rule::in([
                    DeleteAccountRequest::ACTION_REQUESTED,
                    DeleteAccountRequest::ACTION_DELETED,
                    DeleteAccountRequest::ACTION_REJECTED
                ])
            ],
        ]);

        $deleteRequest = DeleteAccountRequest::findOrFail($validated['id']);
        
        $deleteRequest->action_taken = $validated['action_taken'];
        $deleteRequest->action_taken_date = now();
        $deleteRequest->save();

        $deleteRequest->load('user:id,name,email');

        return response()->json(['data' => $deleteRequest]);
    }
}

