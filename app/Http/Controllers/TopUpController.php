<?php

namespace App\Http\Controllers;

use App\Models\TopUpRequest;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TopUpController extends Controller
{
	public function index(Request $request): View
	{
		$topUps = TopUpRequest::where('user_id', $request->user()->id)
			->latest()
			->paginate(10);

		return view('dashboard.topups.index', [
			'topUps' => $topUps,
		]);
	}

	public function create(): View
	{
		return view('dashboard.topups.create');
	}

	public function store(Request $request): RedirectResponse
	{
		$validated = $request->validate([
			'amount' => ['required', 'integer', 'min:1'],
			'transaction_code' => ['required', 'string', 'max:255'],
			'notes' => ['nullable', 'string', 'max:2000'],
		]);

		TopUpRequest::create([
			'user_id' => $request->user()->id,
			'amount' => (int) $validated['amount'],
			'transaction_code' => $validated['transaction_code'],
			'status' => 'pending',
			'notes' => $validated['notes'] ?? null,
		]);

		return redirect()
			->route('topups.index')
			->with('status', 'Awaiting confirmation.');
	}

	public function adminIndex(Request $request): View
	{
		$this->authorizeAdmin($request);

		$pending = TopUpRequest::where('status', 'pending')
			->latest()
			->paginate(20);

		return view('dashboard.topups.admin', [
			'pending' => $pending,
		]);
	}

	public function approve(Request $request, TopUpRequest $topup, CreditService $creditService): RedirectResponse
	{
		$this->authorizeAdmin($request);

		if ($topup->status !== 'pending') {
			return back()->with('status', 'This request is not pending.');
		}

		$creditService->addCredits($topup->user, (int) $topup->amount, 'M-PESA manual top-up: '.$topup->transaction_code);
		$topup->update(['status' => 'approved']);

		return back()->with('status', 'Top-up approved and credits added.');
	}

	public function reject(Request $request, TopUpRequest $topup): RedirectResponse
	{
		$this->authorizeAdmin($request);

		if ($topup->status !== 'pending') {
			return back()->with('status', 'This request is not pending.');
		}

		$topup->update(['status' => 'rejected']);

		return back()->with('status', 'Top-up rejected.');
	}

	private function authorizeAdmin(Request $request): void
	{
		$user = $request->user();
		if (!$user || !(bool) ($user->is_admin ?? false)) {
			abort(403);
		}
	}
}


