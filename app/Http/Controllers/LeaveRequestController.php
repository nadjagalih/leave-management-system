<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveRequestController extends Controller
{
    /**
     * Store a newly created leave request in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
            'attachment' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $user = Auth::user();

        // Check for existing pending requests
        $existingPendingRequest = LeaveRequest::where('user_id', $user->id)
                                              ->where('status', 'pending')
                                              ->exists();

        if ($existingPendingRequest) {
            return response()->json(['message' => 'You already have a pending leave request. Please wait for approval.'], 422);
        }

        $startDate = Carbon::parse($validatedData['start_date']);
        $endDate = Carbon::parse($validatedData['end_date']);
        $daysRequested = abs($startDate->diffInDays($endDate)) + 1;

        if ($user->leave_quota < $daysRequested) {
            return response()->json(['message' => 'Insufficient leave quota.'], 422);
        }

        $attachmentPath = $request->file('attachment')->store('attachments', 'public');
        
        $leaveRequest = null;

        try {
            DB::transaction(function () use ($user, $validatedData, $attachmentPath, $daysRequested, &$leaveRequest) {
                $initialQuota = $user->leave_quota;
                
                // Decrement leave quota immediately
                $user->leave_quota -= $daysRequested;
                $user->save();

                Log::debug('Leave Request Store', [
                    'user_id' => $user->id,
                    'initial_quota' => $initialQuota,
                    'days_requested' => $daysRequested,
                    'final_quota' => $user->leave_quota,
                ]);

                // Create the leave request
                $leaveRequest = LeaveRequest::create([
                    'user_id' => $user->id,
                    'start_date' => $validatedData['start_date'],
                    'end_date' => $validatedData['end_date'],
                    'reason' => $validatedData['reason'],
                    'attachment' => $attachmentPath,
                    'status' => 'pending',
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Leave Request Update Status Failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update leave request status due to a server error.'], 500);
        }

        return response()->json($leaveRequest, 201);
    }

    /**
     * Display a listing of the resource for the authenticated employee.
     */
    public function indexForEmployee()
    {
        $user = Auth::user();
        $leaveRequests = LeaveRequest::where('user_id', $user->id)->with('user:id,name')->get();
        return response()->json($leaveRequests);
    }

    /**
     * Display a listing of the resource for the admin.
     */
    public function indexForAdmin()
    {
        $leaveRequests = LeaveRequest::with('user:id,name')->latest()->get();
        return response()->json($leaveRequests);
    }

    /**
     * Display the specified resource for the admin.
     */
    public function showForAdmin(LeaveRequest $leaveRequest)
    {
        // Load relation to user model
        return response()->json($leaveRequest->load('user:id,name,email'));
    }

    public function showForEmployee(LeaveRequest $leaveRequest)
    {
        // Load relation to user model
        return response()->json($leaveRequest->load('user:id,name,email'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateStatus(Request $request, LeaveRequest $leaveRequest)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'This leave request has already been processed or withdrawn.'], 422);
        }

        try {
            DB::transaction(function () use ($request, $leaveRequest) {
                $leaveRequest->status = $request->status;

                if ($request->status == 'rejected') {
                    $user = $leaveRequest->user;
                    $initialQuota = $user->leave_quota;

                    $startDate = Carbon::parse($leaveRequest->start_date);
                    $endDate = Carbon::parse($leaveRequest->end_date);
                    $daysRequested = abs($startDate->diffInDays($endDate)) + 1;

                    // Restore the leave quota
                    $user->leave_quota += $daysRequested;
                    $user->save();

                    Log::debug('Leave Request Rejected', [
                        'user_id' => $user->id,
                        'leave_request_id' => $leaveRequest->id,
                        'initial_quota' => $initialQuota,
                        'days_restored' => $daysRequested,
                        'final_quota' => $user->leave_quota,
                    ]);
                }

                $leaveRequest->save();
            });
        } catch (\Exception $e) {
            Log::error('Leave Request Store Failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create leave request due to a server error.'], 500);
        }

        return response()->json($leaveRequest);
    }

    /**
     * Withdraw a pending leave request.
     */
    public function withdraw(LeaveRequest $leaveRequest)
    {
        // Authorization: Ensure the authenticated user is the owner of the leave request.
        if (Auth::id() !== $leaveRequest->user_id) {
            return response()->json(['message' => 'You are not authorized to perform this action.'], 403);
        }

        // Validation: Ensure the leave request is still pending.
        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'This leave request cannot be withdrawn as it has already been processed.'], 422);
        }

        try {
            DB::transaction(function () use ($leaveRequest) {
                $user = $leaveRequest->user;
                $initialQuota = $user->leave_quota;

                $startDate = Carbon::parse($leaveRequest->start_date);
                $endDate = Carbon::parse($leaveRequest->end_date);
                $daysToRestore = abs($startDate->diffInDays($endDate)) + 1;

                // Restore the leave quota
                $user->leave_quota += $daysToRestore;
                $user->save();

                // Update the leave request status to 'withdrawn'
                $leaveRequest->status = 'withdrawn';
                $leaveRequest->save();

                Log::debug('Leave Request Withdrawn', [
                    'user_id' => $user->id,
                    'leave_request_id' => $leaveRequest->id,
                    'initial_quota' => $initialQuota,
                    'days_restored' => $daysToRestore,
                    'final_quota' => $user->leave_quota,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Leave Request Withdraw Failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to withdraw leave request due to a server error.'], 500);
        }

        return response()->json([
            'message' => 'Your leave request has been successfully withdrawn.',
            'leave_request' => $leaveRequest
        ]);
    }
}
