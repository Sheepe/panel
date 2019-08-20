<?php

namespace App\Http\Controllers\Base;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\ApiKey;
use Prologue\Alerts\AlertsMessageBag;
use App\Http\Controllers\Controller;
use App\Services\Api\KeyCreationService;
use App\Http\Requests\Base\StoreAccountKeyRequest;
use App\Contracts\Repository\ApiKeyRepositoryInterface;

class AccountKeyController extends Controller
{
    /**
     * @var \Prologue\Alerts\AlertsMessageBag
     */
    protected $alert;

    /**
     * @var \App\Services\Api\KeyCreationService
     */
    protected $keyService;

    /**
     * @var \App\Contracts\Repository\ApiKeyRepositoryInterface
     */
    protected $repository;

    /**
     * APIController constructor.
     *
     * @param \Prologue\Alerts\AlertsMessageBag                           $alert
     * @param \App\Contracts\Repository\ApiKeyRepositoryInterface $repository
     * @param \App\Services\Api\KeyCreationService                $keyService
     */
    public function __construct(
        AlertsMessageBag $alert,
        ApiKeyRepositoryInterface $repository,
        KeyCreationService $keyService
    ) {
        $this->alert = $alert;
        $this->keyService = $keyService;
        $this->repository = $repository;
    }

    /**
     * Display a listing of all account API keys.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request): View
    {
        return view('base.api.index', [
            'keys' => $this->repository->getAccountKeys($request->user()),
        ]);
    }

    /**
     * Display account API key creation page.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\View\View
     */
    public function create(Request $request): View
    {
        return view('base.api.new');
    }

    /**
     * Handle saving new account API key.
     *
     * @param \App\Http\Requests\Base\StoreAccountKeyRequest $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \App\Exceptions\Model\DataValidationException
     */
    public function store(StoreAccountKeyRequest $request)
    {
        $this->keyService->setKeyType(ApiKey::TYPE_ACCOUNT)->handle([
            'user_id' => $request->user()->id,
            'allowed_ips' => $request->input('allowed_ips'),
            'memo' => $request->input('memo'),
        ]);

        $this->alert->success(trans('base.api.index.keypair_created'))->flash();

        return redirect()->route('account.api');
    }

    /**
     * Delete an account API key from the Panel via an AJAX request.
     *
     * @param \Illuminate\Http\Request $request
     * @param string                   $identifier
     * @return \Illuminate\Http\Response
     */
    public function revoke(Request $request, string $identifier): Response
    {
        $this->repository->deleteAccountKey($request->user(), $identifier);

        return response('', 204);
    }
}
