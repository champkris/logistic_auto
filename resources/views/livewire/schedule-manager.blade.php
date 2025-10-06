<div class="p-6 space-y-6">
    <!-- Header Section -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">⏰ ETA Check Schedules</h1>
            <p class="mt-2 text-sm text-gray-700">Configure automatic ETA checking times</p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-3">
            <button wire:click="runAllEtaCheck"
                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                🚀 Check All ETAs Now
            </button>
            <button wire:click="openModal"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                ➕ New Schedule
            </button>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if (session()->has('success'))
        <div class="bg-green-50 border border-green-200 rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-50 border border-red-200 rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Schedules Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            @if($schedules->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Schedule</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Run</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Next Run</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($schedules as $schedule)
                                <tr class="hover:bg-gray-50">
                                    <!-- Schedule Name & Description -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ $schedule->name }}</div>
                                            @if($schedule->description)
                                                <div class="text-sm text-gray-500">{{ $schedule->description }}</div>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Type -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($schedule->schedule_type === 'vessel_scrape')
                                                bg-purple-100 text-purple-800
                                            @else
                                                bg-teal-100 text-teal-800
                                            @endif">
                                            @if($schedule->schedule_type === 'vessel_scrape')
                                                🚢 Vessel Scrape
                                            @else
                                                📊 ETA Check
                                            @endif
                                        </span>
                                    </td>

                                    <!-- Time -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-mono text-gray-900">{{ $schedule->check_time->format('H:i') }}</span>
                                    </td>

                                    <!-- Days -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-900">{{ $schedule->schedule_description }}</span>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($schedule->status_color === 'green')
                                                bg-green-100 text-green-800
                                            @elseif($schedule->status_color === 'blue')
                                                bg-blue-100 text-blue-800
                                            @else
                                                bg-gray-100 text-gray-800
                                            @endif">
                                            @if($schedule->is_active)
                                                @if($schedule->last_run_at && $schedule->last_run_at->isToday())
                                                    ✅ Ran Today
                                                @else
                                                    🔵 Active
                                                @endif
                                            @else
                                                ⚫ Inactive
                                            @endif
                                        </span>
                                    </td>

                                    <!-- Last Run -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if($schedule->last_run_at)
                                            {{ $schedule->last_run_at->format('M j, H:i') }}
                                        @else
                                            Never
                                        @endif
                                    </td>

                                    <!-- Next Run -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if($schedule->next_run_at && $schedule->is_active)
                                            {{ $schedule->next_run_at->format('M j, H:i') }}
                                        @else
                                            -
                                        @endif
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <!-- Toggle Active -->
                                        <button wire:click="toggleActive({{ $schedule->id }})"
                                                class="text-indigo-600 hover:text-indigo-900"
                                                title="{{ $schedule->is_active ? 'Deactivate' : 'Activate' }}">
                                            @if($schedule->is_active)
                                                ⏸️
                                            @else
                                                ▶️
                                            @endif
                                        </button>

                                        <!-- Run Now -->
                                        @if($schedule->is_active)
                                            <button wire:click="runNow({{ $schedule->id }})"
                                                    class="text-green-600 hover:text-green-900"
                                                    title="Run ETA check now">
                                                🚀
                                            </button>
                                        @endif

                                        <!-- Edit -->
                                        <button wire:click="edit({{ $schedule->id }})"
                                                class="text-blue-600 hover:text-blue-900"
                                                title="Edit schedule">
                                            ✏️
                                        </button>

                                        <!-- Delete -->
                                        <button wire:click="delete({{ $schedule->id }})"
                                                onclick="return confirm('Are you sure you want to delete this schedule?')"
                                                class="text-red-600 hover:text-red-900"
                                                title="Delete schedule">
                                            🗑️
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    {{ $schedules->links() }}
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No schedules found</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating your first ETA check schedule.</p>
                    <div class="mt-6">
                        <button wire:click="openModal"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                            ➕ Add Schedule
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Schedule Form Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-4 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 max-h-[calc(100vh-2rem)] shadow-lg rounded-md bg-white flex flex-col">
                <div class="flex-shrink-0">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between pb-4 border-b">
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ $editingSchedule ? 'Edit Schedule' : 'Create New Schedule' }}
                        </h3>
                        <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Quick Presets -->
                    @if(!$editingSchedule)
                        <div class="mt-4 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
                            <h4 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                                ⚡ Quick Setup
                                <span class="ml-2 text-sm font-normal text-gray-600">(Click to auto-fill)</span>
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <button wire:click="setQuickSchedule('business_hours')"
                                        class="p-4 bg-white border-2 border-blue-200 rounded-lg hover:border-blue-400 hover:shadow-md transition-all duration-200 text-left group">
                                    <div class="flex items-center mb-2">
                                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3 group-hover:bg-blue-200">
                                            🏢
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">Business Hours</div>
                                            <div class="text-sm text-gray-500">9AM, Weekdays only</div>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-400">Perfect for office hours checking</div>
                                </button>

                                <button wire:click="setQuickSchedule('twice_daily')"
                                        class="p-4 bg-white border-2 border-green-200 rounded-lg hover:border-green-400 hover:shadow-md transition-all duration-200 text-left group">
                                    <div class="flex items-center mb-2">
                                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3 group-hover:bg-green-200">
                                            🌅
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">Morning Check</div>
                                            <div class="text-sm text-gray-500">8AM, Every day</div>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-400">Daily morning updates</div>
                                </button>

                                <button wire:click="setQuickSchedule('evening')"
                                        class="p-4 bg-white border-2 border-purple-200 rounded-lg hover:border-purple-400 hover:shadow-md transition-all duration-200 text-left group">
                                    <div class="flex items-center mb-2">
                                        <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3 group-hover:bg-purple-200">
                                            🌇
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">Evening Check</div>
                                            <div class="text-sm text-gray-500">6PM, Every day</div>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-400">End of day summary</div>
                                </button>
                            </div>
                        </div>
                    @endif

                    <!-- Scrollable Form Content -->
                    <div class="flex-1 overflow-y-auto pr-2 -mr-2">
                        <!-- Form -->
                        <form wire:submit.prevent="save" class="mt-8 space-y-6" id="schedule-form">
                        <!-- Schedule Type -->
                        <div class="space-y-2">
                            <label class="block text-base font-semibold text-gray-900 flex items-center">
                                🎯 Schedule Type
                                <span class="ml-2 text-sm font-normal text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="flex items-center p-4 bg-white border-2 rounded-xl cursor-pointer transition-all duration-200 hover:border-purple-300
                                    {{ $schedule_type === 'vessel_scrape' ? 'border-purple-500 bg-purple-50' : 'border-gray-200' }}">
                                    <input type="radio" wire:model="schedule_type" value="vessel_scrape"
                                           class="text-purple-600 focus:ring-purple-500">
                                    <div class="ml-3">
                                        <div class="font-medium text-gray-900">🚢 Vessel Scrape</div>
                                        <div class="text-xs text-gray-500">Daily terminal scraping</div>
                                    </div>
                                </label>
                                <label class="flex items-center p-4 bg-white border-2 rounded-xl cursor-pointer transition-all duration-200 hover:border-teal-300
                                    {{ $schedule_type === 'eta_check' ? 'border-teal-500 bg-teal-50' : 'border-gray-200' }}">
                                    <input type="radio" wire:model="schedule_type" value="eta_check"
                                           class="text-teal-600 focus:ring-teal-500">
                                    <div class="ml-3">
                                        <div class="font-medium text-gray-900">📊 ETA Check</div>
                                        <div class="text-xs text-gray-500">Check shipment ETAs</div>
                                    </div>
                                </label>
                            </div>
                            @error('schedule_type') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Schedule Name -->
                        <div class="space-y-2">
                            <label class="block text-base font-semibold text-gray-900 flex items-center">
                                📝 Schedule Name
                                <span class="ml-2 text-sm font-normal text-red-500">*</span>
                            </label>
                            <input type="text" wire:model="name"
                                   class="block w-full px-4 py-3 text-lg border-2 border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                                   placeholder="e.g., Morning ETA Check">
                            @error('name') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Check Time -->
                        <div class="space-y-2">
                            <label class="block text-base font-semibold text-gray-900 flex items-center">
                                ⏰ What time should we check?
                                <span class="ml-2 text-sm font-normal text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="time" wire:model="check_time"
                                       class="block w-full px-4 py-3 text-lg border-2 border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            @error('check_time') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Days of Week -->
                        <div class="space-y-3">
                            <label class="block text-base font-semibold text-gray-900 flex items-center">
                                📅 Which days?
                                <span class="ml-2 text-sm font-normal text-gray-600">(Leave empty to run every day)</span>
                            </label>
                            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    @foreach($dayOptions as $dayValue => $dayName)
                                        <label class="flex items-center p-3 bg-white rounded-lg border border-gray-200 hover:border-blue-300 cursor-pointer transition-all duration-200 group">
                                            <input type="checkbox" wire:model="days_of_week" value="{{ $dayValue }}"
                                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                            <span class="ml-3 text-sm font-medium text-gray-700 group-hover:text-blue-600">{{ $dayName }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="mt-3 text-sm text-gray-500 flex items-center">
                                    💡 <span class="ml-1">Tip: Select no days to run every day, or choose specific days for custom schedules</span>
                                </div>
                            </div>
                            @error('days_of_week') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Description -->
                        <div class="space-y-2">
                            <label class="block text-base font-semibold text-gray-900 flex items-center">
                                📄 Description
                                <span class="ml-2 text-sm font-normal text-gray-600">(Optional)</span>
                            </label>
                            <textarea wire:model="description" rows="3"
                                      class="block w-full px-4 py-3 text-base border-2 border-gray-200 rounded-xl shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 resize-none"
                                      placeholder="Add any notes about this schedule (e.g., 'For urgent shipments only')"></textarea>
                            @error('description') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Active Status -->
                        <div class="bg-green-50 p-4 rounded-xl border border-green-200">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="is_active" id="is_active"
                                       class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50 w-5 h-5">
                                <div class="ml-3">
                                    <div class="text-base font-semibold text-gray-900 flex items-center">
                                        ✅ Enable this schedule
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        When enabled, ETA checks will run automatically at the scheduled time
                                    </div>
                                </div>
                            </label>
                        </div>
                        </form>
                    </div>

                    <!-- Fixed Form Actions -->
                    <div class="flex-shrink-0 flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 pt-6 border-t border-gray-200 bg-white">
                        <button type="button" wire:click="closeModal"
                                class="px-6 py-3 border-2 border-gray-300 rounded-xl text-base font-medium text-gray-700 hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancel
                        </button>
                        <button type="submit" form="schedule-form"
                                class="px-8 py-3 bg-blue-600 border border-transparent rounded-xl text-base font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 shadow-lg flex items-center justify-center">
                            @if($editingSchedule)
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Update Schedule
                            @else
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Create Schedule
                            @endif
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>