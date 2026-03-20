<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendTaskReminders extends Command
{
    protected $signature = 'tasks:send-reminders';
    protected $description = 'Send notifications for overdue and due-today tasks';

    public function handle(): int
    {
        $today = now()->toDateString();

        // Overdue tasks
        $overdue = Task::where('is_completed', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->get();

        $overdueCount = 0;
        foreach ($overdue as $task) {
            NotificationService::send($task->agency_id, 'task_due', "Overdue task: {$task->title}", [
                'body' => "Due: {$task->due_date->format('M j, Y')}",
                'link' => '#tasks',
                'linkable_type' => 'task',
                'linkable_id' => $task->id,
            ]);
            $overdueCount++;
        }

        // Tasks due today
        $dueToday = Task::where('is_completed', false)
            ->where('due_date', $today)
            ->get();

        $todayCount = 0;
        foreach ($dueToday as $task) {
            NotificationService::send($task->agency_id, 'task_due', "Task due today: {$task->title}", [
                'body' => $task->description ? \Illuminate\Support\Str::limit($task->description, 80) : 'No description',
                'link' => '#tasks',
                'linkable_type' => 'task',
                'linkable_id' => $task->id,
            ]);
            $todayCount++;
        }

        $this->info("Sent {$overdueCount} overdue + {$todayCount} due-today task reminders.");
        return Command::SUCCESS;
    }
}
