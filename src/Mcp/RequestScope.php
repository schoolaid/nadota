<?php

namespace SchoolAid\Nadota\Mcp;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;
use SchoolAid\Nadota\Resource;

/**
 * Runs Nadota code outside its HTTP route, with a NadotaRequest of the MCP user and the session
 * of the MCP request (the app's scopes read their context from it: a school, a branch office…).
 * While it runs, that request is `app('request')`, because fields and scopes read `request()`.
 * Binding it makes the container reassign the guard's user resolver, so the MCP user is set
 * afterwards. The MCP request goes back to the container in the `finally`, even if Nadota throws.
 */
class RequestScope
{
    /**
     * @template TResult
     *
     * @param  array<string, mixed>  $input
     * @param  Closure(NadotaRequest): TResult  $callback
     * @return TResult
     */
    public function run(Authenticatable $user, Resource $resource, array $input, Closure $callback): mixed
    {
        $request = NadotaRequest::create('/'.config('nadota.api.prefix', 'nadota-api').'/'.$resource::getKey().'/resource', 'GET', $input);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setLaravelSession(app('session.store'));
        $request->setResource($resource);

        $original = app('request');
        $this->bind($request);
        $request->setUserResolver(fn (): Authenticatable => $user);

        try {
            return $callback($request);
        } finally {
            $this->bind($original);
        }
    }

    private function bind(Request $request): void
    {
        app()->instance('request', $request);
        Facade::clearResolvedInstance('request');
    }
}
