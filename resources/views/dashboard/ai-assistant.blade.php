<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('AI Assistant') }}
            </h2>
            <a href="{{ route('topups.create') }}" class="inline-flex items-center rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                {{ __('Buy Credits') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 grid gap-6">
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-200">
                    {{ session('error') }}
                </div>
            @endif
            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-200">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('ai-assistant.generate') }}" class="grid gap-4">
                        @csrf

                        <div>
                            <label for="affiliate_network" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Affiliate Network</label>
                            <select name="affiliate_network" id="affiliate_network" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @php
                                    $categories = \App\Models\LandingPage::getNetworksByCategory();
                                @endphp
                                @foreach ($categories as $category => $networkList)
                                    <optgroup label="{{ $category }}">
                                        @foreach ($networkList as $network)
                                            <option value="{{ $network }}" @selected(old('affiliate_network')===$network)>{{ $network }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('affiliate_network')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="niche" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Niche / Product Type</label>
                            <input type="text" name="niche" id="niche" value="{{ old('niche') }}" placeholder="e.g., AI tools for freelancers" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            @error('niche')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="affiliate_link" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Affiliate Link</label>
                            <input type="url" name="affiliate_link" id="affiliate_link" value="{{ old('affiliate_link') }}" placeholder="https://example.com/your-affiliate-link" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            @error('affiliate_link')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Cost: 2 credits per plan</p>
                            <x-primary-button>{{ __('Generate Marketing Plan') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            @if (session('generated_plan'))
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100 prose dark:prose-invert max-w-none">
                        {!! nl2br(e(session('generated_plan'))) !!}
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>


