<?php

namespace App\Domains\TemporalRouting\Controllers;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Services\SwitchAccountService;
use App\Domains\TemporalRouting\Requests\TemporalControlRequest;
use App\Domains\TemporalRouting\Resources\TemporalRuleResource;
use App\Domains\TemporalRouting\Resources\TemporalRuleSetResource;
use App\Domains\TemporalRouting\Services\TemporalOperationalControlService;
use App\Domains\TemporalRouting\Services\TemporalRoutingService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class TemporalOperationalControlController extends Controller
{
    public function rule(
        TemporalControlRequest $request,
        string $account,
        string $rule,
        SwitchAccountService $accounts,
        TemporalRoutingService $routing,
        TemporalOperationalControlService $controls,
    ): TemporalRuleResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $temporalRule = $routing->findRule($switchAccount, $rule);
        Gate::authorize('update', [$temporalRule, $switchAccount]);

        return new TemporalRuleResource($controls->controlRule(
            $switchAccount,
            $temporalRule,
            $user,
            $request->validated('action'),
            $request->ip(),
        ));
    }

    public function ruleSet(
        TemporalControlRequest $request,
        string $account,
        string $set,
        SwitchAccountService $accounts,
        TemporalRoutingService $routing,
        TemporalOperationalControlService $controls,
    ): TemporalRuleSetResource {
        /** @var User $user */
        $user = $request->user();
        $switchAccount = $accounts->findAccessible($user, $account);
        $ruleSet = $routing->findSet($switchAccount, $set);
        Gate::authorize('update', [$ruleSet, $switchAccount]);

        return new TemporalRuleSetResource($controls->controlRuleSet(
            $switchAccount,
            $ruleSet,
            $user,
            $request->validated('action'),
            $request->ip(),
        ));
    }
}
