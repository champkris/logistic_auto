<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <!-- LINE Account Connection -->
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <section>
                        <header>
                            <h2 class="text-lg font-medium text-gray-900">
                                📱 LINE Account Connection
                            </h2>
                            <p class="mt-1 text-sm text-gray-600">
                                Connect your LINE account to receive shipment notifications and updates directly on LINE.
                            </p>
                        </header>

                        <div class="mt-6 space-y-6">
                            @if(auth()->user()->hasLineAccount())
                                <!-- Connected State -->
                                <div class="flex items-center space-x-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                                    @if(auth()->user()->line_picture_url)
                                        <img src="{{ auth()->user()->line_picture_url }}"
                                             alt="LINE Profile"
                                             class="w-12 h-12 rounded-full border-2 border-green-300">
                                    @else
                                        <div class="w-12 h-12 bg-green-300 rounded-full flex items-center justify-center">
                                            <span class="text-green-800 font-semibold text-lg">📱</span>
                                        </div>
                                    @endif

                                    <div class="flex-1">
                                        <p class="font-medium text-green-900">Connected to LINE</p>
                                        <p class="text-sm text-green-700">
                                            {{ auth()->user()->line_display_name }}
                                        </p>
                                        <p class="text-xs text-green-600">
                                            Connected on {{ auth()->user()->line_connected_at?->format('M d, Y \a\t g:i A') }}
                                        </p>
                                    </div>

                                    <div class="flex space-x-2">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            ✅ Connected
                                        </span>
                                    </div>
                                </div>

                                <!-- Test and Disconnect Actions -->
                                <div class="flex space-x-3">
                                    <form method="POST" action="{{ route('line.test-message') }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                            🧪 Send Test Message
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('line.disconnect') }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                                onclick="return confirm('Are you sure you want to disconnect your LINE account? You will no longer receive LINE notifications.')"
                                                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                            🔌 Disconnect LINE
                                        </button>
                                    </form>
                                </div>
                            @else
                                <!-- Not Connected State -->
                                <div class="flex items-center space-x-4 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                    <div class="w-12 h-12 bg-gray-300 rounded-full flex items-center justify-center">
                                        <span class="text-gray-600 font-semibold text-lg">📱</span>
                                    </div>

                                    <div class="flex-1">
                                        <p class="font-medium text-gray-900">LINE Account Not Connected</p>
                                        <p class="text-sm text-gray-600">
                                            Connect your LINE account to receive shipment notifications
                                        </p>
                                    </div>

                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        ❌ Not Connected
                                    </span>
                                </div>

                                <!-- Connect Button -->
                                <a href="{{ route('line.connect') }}"
                                   class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    📱 Connect to LINE
                                </a>

                                <!-- Benefits List -->
                                <div class="mt-4 bg-blue-50 p-4 rounded-lg">
                                    <h4 class="font-medium text-blue-900 mb-2">🌟 Benefits of connecting LINE:</h4>
                                    <ul class="text-sm text-blue-800 space-y-1">
                                        <li>• Get instant notifications when your shipments arrive</li>
                                        <li>• Receive vessel tracking updates in real-time</li>
                                        <li>• Get alerted about document requirements and deadlines</li>
                                        <li>• Quick access to shipment status via LINE messages</li>
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
