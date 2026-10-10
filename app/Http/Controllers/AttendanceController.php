<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    private function getTenantId()
    {
        $user = Auth::user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }
        if (! $user->tenant_id) {
            abort(403, 'User does not belong to any tenant');
        }

        return $user->tenant_id;
    }

    public function index()
    {
        $user = Auth::user();
        if (! $user || ! in_array($user->role, ['super_admin', 'merchant', 'store_manager'])) {
            abort(403, 'Unauthorized access to staff attendance list.');
        }

        $tenantId = $this->getTenantId();

        $attendances = Attendance::with(['user', 'store'])
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate(15);

        $activeClockIn = Attendance::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->whereNull('clock_out')
            ->first();

        return Inertia::render('HRM/Attendance', [
            'attendances' => $attendances,
            'activeClockIn' => $activeClockIn,
        ]);
    }

    public function toggleClock(Request $request)
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId();

        $activeClock = Attendance::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->whereNull('clock_out')
            ->first();

        if ($activeClock) {
            // Clock Out
            $clockOutTime = now();
            $hours = round($clockOutTime->diffInMinutes($activeClock->clock_in) / 60, 2);

            $activeClock->update([
                'clock_out' => $clockOutTime,
                'total_hours' => $hours,
                'notes' => $request->input('notes', 'Clocked out from station'),
            ]);

            return redirect()->back()->with('success', "Clocked out successfully! Total session: {$hours} hours.");
        } else {
            // Clock In
            $storeId = $user->store_id ?? (Store::where('tenant_id', $tenantId)->first()?->id ?? 1);

            Attendance::create([
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'user_id' => $user->id,
                'clock_in' => now(),
                'ip_address' => $request->ip(),
                'notes' => $request->input('notes', 'Shift clock in'),
            ]);

            return redirect()->back()->with('success', 'Clocked in successfully! Have a productive shift.');
        }
    }
}
