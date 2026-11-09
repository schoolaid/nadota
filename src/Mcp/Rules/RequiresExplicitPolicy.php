<?php

namespace SchoolAid\Nadota\Mcp\Rules;

use Illuminate\Support\Facades\Gate;
use SchoolAid\Nadota\Mcp\Contracts\ExposureRule;
use SchoolAid\Nadota\Resource;

/**
 * Always applied, before the app's rules. Nadota authorizes open: with no policy, or a policy
 * without the method, ResourceAuthorizationService answers true. The MCP tools fail closed
 * instead: the model needs a policy that defines both `viewAny` and `view` (inherited methods
 * count), and the Gate then decides per user.
 */
class RequiresExplicitPolicy implements ExposureRule
{
    public function check(Resource $resource, array $entry): ?string
    {
        $policy = Gate::getPolicyFor($resource->model);

        if ($policy === null) {
            return 'its model has no policy';
        }

        if (! method_exists($policy, 'viewAny') || ! method_exists($policy, 'view')) {
            return 'its policy does not define both viewAny and view';
        }

        return null;
    }
}
