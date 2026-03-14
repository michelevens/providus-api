<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\Followup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    /**
     * All valid application statuses with display metadata.
     */
    const STATUS_META = [
        'not_started'  => ['label' => 'Not Started',  'color' => '#64748b'],
        'submitted'    => ['label' => 'Submitted',     'color' => '#1d4ed8'],
        'in_review'    => ['label' => 'In Review',     'color' => '#92400e'],
        'pending_info' => ['label' => 'Pending Info',  'color' => '#6b21a8'],
        'approved'     => ['label' => 'Approved',      'color' => '#166534'],
        'denied'       => ['label' => 'Denied',        'color' => '#991b1b'],
        'withdrawn'    => ['label' => 'Withdrawn',     'color' => '#78716c'],
    ];

    /**
     * Auto-followup rules keyed by the status that triggers them.
     * type: the followup type to create
     * days: how many days from now to set the due date
     * max:  maximum open (uncompleted) followups before skipping
     */
    const AUTO_FOLLOWUP_RULES = [
        'submitted'    => ['type' => 'status_check',        'days' => 14, 'max' => 3],
        'approved'     => ['type' => 'document_collection', 'days' => 7,  'max' => 2],
        'credentialed' => ['type' => 'renewal_check',       'days' => 30, 'max' => 1],
    ];

    /**
     * Escalation thresholds.
     */
    const ESCALATION_DAYS_WITHOUT_RESPONSE = 60;
    const ESCALATION_MAX_FOLLOWUPS = 5;

    /**
     * Statuses that are considered "in-progress" for aging/escalation.
     */
    const IN_PROGRESS_STATUSES = ['submitted', 'in_review', 'pending_info'];

    // ── Transitions ──────────────────────────────────────────────

    /**
     * Validate and perform a status transition.
     *
     * @return array{success: bool, error?: string, application?: Application, activity_log?: ActivityLog, followup?: Followup|null}
     */
    public function transition(Application $app, string $newStatus, ?int $userId = null): array
    {
        if (!$app->canTransitionTo($newStatus)) {
            $allowed = implode(', ', $this->getAvailableTransitions($app));

            return [
                'success' => false,
                'error'   => "Cannot transition from \"{$app->status}\" to \"{$newStatus}\". Allowed: {$allowed}",
            ];
        }

        return DB::transaction(function () use ($app, $newStatus, $userId) {
            $oldStatus = $app->status;
            $now = Carbon::today();

            // Update application status
            $app->status = $newStatus;

            // Auto-fill milestone dates
            if ($newStatus === 'submitted' && !$app->submitted_date) {
                $app->submitted_date = $now;
            }
            if ($newStatus === 'approved' && !$app->effective_date) {
                $app->effective_date = $now;
            }

            $app->save();

            // Create activity log entry
            $activityLog = ActivityLog::create([
                'agency_id'      => $app->agency_id,
                'application_id' => $app->id,
                'type'           => 'status_change',
                'logged_date'    => $now,
                'status_from'    => $oldStatus,
                'status_to'      => $newStatus,
                'outcome'        => "Status changed from {$oldStatus} to {$newStatus}",
                'created_by'     => $userId,
            ]);

            // Auto-schedule followup based on new status
            $followup = $this->autoScheduleFollowup($app, $newStatus);

            return [
                'success'      => true,
                'application'  => $app->fresh(),
                'activity_log' => $activityLog,
                'followup'     => $followup,
            ];
        });
    }

    /**
     * Return valid next statuses for the given application.
     *
     * @return string[]
     */
    public function getAvailableTransitions(Application $app): array
    {
        return Application::VALID_TRANSITIONS[$app->status] ?? [];
    }

    // ── Follow-ups ───────────────────────────────────────────────

    /**
     * Create a scheduled followup record.
     */
    public function scheduleFollowup(Application $app, string $type, int $daysFromNow): Followup
    {
        return Followup::create([
            'agency_id'      => $app->agency_id,
            'application_id' => $app->id,
            'type'           => $type,
            'due_date'       => Carbon::today()->addDays($daysFromNow),
            'method'         => 'phone',
        ]);
    }

    /**
     * Automatically schedule a followup if rules dictate one for the given status.
     * Returns null when no followup is needed or the cap has been reached.
     */
    public function autoScheduleFollowup(Application $app, string $status): ?Followup
    {
        $rule = self::AUTO_FOLLOWUP_RULES[$status] ?? null;
        if (!$rule) {
            return null;
        }

        // Check open followup cap
        $openCount = $app->followups()->pending()->count();
        if ($openCount >= $rule['max']) {
            return null;
        }

        return $this->scheduleFollowup($app, $rule['type'], $rule['days']);
    }

    // ── Aging & Escalation ───────────────────────────────────────

    /**
     * Get the age in days since the application was submitted.
     */
    public function getApplicationAge(Application $app): ?int
    {
        if (!$app->submitted_date) {
            return null;
        }

        return (int) $app->submitted_date->diffInDays(Carbon::today());
    }

    /**
     * Return in-progress applications older than the given number of days.
     *
     * @return \Illuminate\Database\Eloquent\Collection<Application>
     */
    public function getAgedApplications(int $agencyId, int $minDays = 90)
    {
        return Application::where('agency_id', $agencyId)
            ->whereIn('status', self::IN_PROGRESS_STATUSES)
            ->whereNotNull('submitted_date')
            ->where('submitted_date', '<=', Carbon::today()->subDays($minDays))
            ->orderBy('submitted_date')
            ->get();
    }

    /**
     * Return applications that should be escalated based on age or followup count.
     */
    public function getEscalationCandidates(int $agencyId): array
    {
        $apps = Application::where('agency_id', $agencyId)
            ->whereIn('status', self::IN_PROGRESS_STATUSES)
            ->with('followups')
            ->get();

        $candidates = [];

        foreach ($apps as $app) {
            $age = $this->getApplicationAge($app);
            $completedFollowups = $app->followups->filter(fn ($f) => $f->completed_date !== null)->count();

            $shouldEscalate =
                ($age !== null && $age >= self::ESCALATION_DAYS_WITHOUT_RESPONSE) ||
                $completedFollowups >= self::ESCALATION_MAX_FOLLOWUPS;

            if ($shouldEscalate) {
                $reason = ($age !== null && $age >= self::ESCALATION_DAYS_WITHOUT_RESPONSE)
                    ? "{$age} days since submission"
                    : "{$completedFollowups} follow-ups without resolution";

                $candidates[] = [
                    'application'    => $app,
                    'age_days'       => $age,
                    'followup_count' => $completedFollowups,
                    'reason'         => $reason,
                ];
            }
        }

        return $candidates;
    }
}
