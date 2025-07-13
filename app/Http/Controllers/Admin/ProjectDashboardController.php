<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ProjectDashboardController extends Controller
{
    private $projectDocsPath;

    public function __construct()
    {
        $this->projectDocsPath = base_path('docs/project-planning/config');
    }

    /**
     * Display the project dashboard
     */
    public function index()
    {
        try {
            $projectData = $this->loadProjectData();
            $projectSummary = $this->calculateProjectSummary($projectData);
            
            return view('admin.project-dashboard', compact('projectData', 'projectSummary'));
        } catch (\Exception $e) {
            return back()->with('error', 'Error loading project data: ' . $e->getMessage());
        }
    }

    /**
     * Update task status and progress
     */
    public function updateTask(Request $request, $taskId)
    {
        $request->validate([
            'status' => 'required|in:planned,in_progress,completed,at_risk,blocked',
            'progress' => 'integer|min:0|max:100',
            'notes' => 'nullable|string|max:1000'
        ]);

        try {
            $tasks = $this->loadTasksData();
            $taskUpdated = false;

            foreach ($tasks['tasks'] as &$task) {
                if ($task['id'] === $taskId) {
                    $oldStatus = $task['status'];
                    $task['status'] = $request->status;
                    $task['progress'] = $request->progress ?? $task['progress'] ?? 0;
                    $task['notes'] = $request->notes ?? $task['notes'] ?? '';
                    $task['updated_at'] = now()->toISOString();
                    $taskUpdated = true;

                    // Log the change
                    $this->logChange(
                        'Task Update', 
                        "Updated {$taskId} ({$task['name']}) status from {$oldStatus} to {$request->status}",
                        $request->user()->name ?? 'System'
                    );
                    
                    break;
                }
            }

            if ($taskUpdated) {
                $this->saveTasksData($tasks);
                return response()->json([
                    'success' => true, 
                    'message' => "Task {$taskId} updated successfully"
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Task not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    /**
     * Get project statistics for API
     */
    public function getProjectStats()
    {
        try {
            $projectData = $this->loadProjectData();
            $stats = $this->calculateProjectSummary($projectData);
            
            return response()->json($stats);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Load all project data from JSON files
     */
    private function loadProjectData()
    {
        $tasksPath = "{$this->projectDocsPath}/tasks.json";
        
        if (!File::exists($tasksPath)) {
            throw new \Exception("Project data file not found: {$tasksPath}");
        }

        $tasksData = json_decode(File::get($tasksPath), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Error parsing project data: " . json_last_error_msg());
        }

        return $tasksData;
    }

    /**
     * Load tasks data specifically
     */
    private function loadTasksData()
    {
        return $this->loadProjectData();
    }

    /**
     * Save tasks data back to JSON file
     */
    private function saveTasksData($data)
    {
        $tasksPath = "{$this->projectDocsPath}/tasks.json";
        
        File::put($tasksPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Calculate project summary statistics
     */
    private function calculateProjectSummary($projectData)
    {
        $tasks = $projectData['tasks'];
        $totalTasks = count($tasks);
        $completedTasks = 0;
        $inProgressTasks = 0;
        $totalBudget = $projectData['project']['total_budget'];
        $spentBudget = 0;

        foreach ($tasks as $task) {
            if ($task['status'] === 'completed') {
                $completedTasks++;
                $spentBudget += $task['cost'];
            } elseif ($task['status'] === 'in_progress') {
                $inProgressTasks++;
                // Calculate partial cost based on progress
                $progress = $task['progress'] ?? 0;
                $spentBudget += ($task['cost'] * $progress / 100);
            }
        }

        $completionPercentage = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;
        $budgetUtilization = $totalBudget > 0 ? ($spentBudget / $totalBudget) * 100 : 0;

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'in_progress_tasks' => $inProgressTasks,
            'completion_percentage' => round($completionPercentage, 1),
            'total_budget' => $totalBudget,
            'spent_budget' => $spentBudget,
            'budget_utilization' => round($budgetUtilization, 1),
            'project_start' => $projectData['project']['start_date'],
            'project_end' => $projectData['project']['end_date'],
        ];
    }
    /**
     * Log changes to the changelog file
     */
    private function logChange($type, $description, $user = 'System')
    {
        $changelogPath = base_path('docs/project-planning/changelog.md');
        $date = now()->format('Y-m-d H:i:s');
        
        $entry = "\n## [{$date}] - {$type}\n### Changed\n- {$description}\n- Updated by: {$user}\n- Updated via: Laravel admin dashboard\n\n";
        
        File::append($changelogPath, $entry);
    }

    /**
     * Get tasks by phase
     */
    public function getTasksByPhase()
    {
        try {
            $projectData = $this->loadProjectData();
            $tasksByPhase = [];

            foreach ($projectData['phases'] as $phase) {
                $phaseTasks = array_filter($projectData['tasks'], function($task) use ($phase) {
                    return $task['phase'] === $phase['id'];
                });

                $tasksByPhase[$phase['id']] = [
                    'phase' => $phase,
                    'tasks' => array_values($phaseTasks)
                ];
            }

            return response()->json($tasksByPhase);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get upcoming milestones
     */
    public function getUpcomingMilestones()
    {
        try {
            $projectData = $this->loadProjectData();
            $milestones = $projectData['milestones'];
            
            // Filter milestones that are not yet completed
            $upcomingMilestones = array_filter($milestones, function($milestone) {
                return $milestone['status'] !== 'completed' && 
                       Carbon::parse($milestone['date'])->isFuture();
            });

            // Sort by date
            usort($upcomingMilestones, function($a, $b) {
                return Carbon::parse($a['date'])->timestamp - Carbon::parse($b['date'])->timestamp;
            });

            return response()->json(array_values($upcomingMilestones));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export project data as Excel
     */
    public function exportProjectData()
    {
        try {
            $projectData = $this->loadProjectData();
            
            // This would require a package like maatwebsite/excel
            // For now, return JSON data that can be imported into Excel
            $exportData = [
                'project_info' => $projectData['project'],
                'tasks' => $projectData['tasks'],
                'milestones' => $projectData['milestones'],
                'export_date' => now()->toISOString()
            ];

            return response()->json($exportData)
                ->header('Content-Disposition', 'attachment; filename="project-data-' . now()->format('Y-m-d') . '.json"');
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}