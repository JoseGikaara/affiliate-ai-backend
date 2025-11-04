<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Buy Credits') }}
            </h2>
            <a href="{{ route('topups.index') }}" class="inline-flex items-center rounded-md bg-gray-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-500">
                {{ __('My Requests') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-200">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Payment Instructions -->
            <div class="mb-6 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-200 mb-3">Payment Instructions</h3>
                <div class="space-y-2 text-sm text-blue-800 dark:text-blue-200">
                    <p><strong>Paybill Number:</strong> <span class="font-mono">123456</span></p>
                    <p><strong>Account Name:</strong> AffiliateAI</p>
                    <p class="mt-3">1. Go to M-PESA menu on your phone</p>
                    <p>2. Select "Lipa na M-PESA"</p>
                    <p>3. Select "Pay Bill"</p>
                    <p>4. Enter Business Number: <span class="font-mono font-bold">123456</span></p>
                    <p>5. Enter Account Number: <span class="font-mono font-bold">AffiliateAI</span></p>
                    <p>6. Enter the amount you wish to top up</p>
                    <p>7. Enter your M-PESA PIN and confirm</p>
                    <p>8. Once you receive the confirmation SMS, enter the transaction code below</p>
                </div>
            </div>

            <!-- Top-up Form -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('topups.store') }}" class="space-y-6">
                        @csrf

                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Amount (Credits)</label>
                            <input 
                                type="number" 
                                name="amount" 
                                id="amount" 
                                value="{{ old('amount') }}" 
                                min="1"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                                placeholder="Enter amount in credits"
                            />
                            @error('amount')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="transaction_code" class="block text-sm font-medium text-gray-700 dark:text-gray-200">M-PESA Transaction Code</label>
                            <input 
                                type="text" 
                                name="transaction_code" 
                                id="transaction_code" 
                                value="{{ old('transaction_code') }}" 
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono" 
                                placeholder="Enter M-PESA transaction code (e.g., QN1234567890)"
                            />
                            @error('transaction_code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Enter the transaction code from your M-PESA confirmation SMS</p>
                        </div>

                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Notes (Optional)</label>
                            <textarea 
                                name="notes" 
                                id="notes" 
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                                placeholder="Any additional information about this payment..."
                            >{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between pt-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Your request will be reviewed by an admin. Credits will be added once approved.
                            </p>
                            <x-primary-button>{{ __('Submit Request') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

