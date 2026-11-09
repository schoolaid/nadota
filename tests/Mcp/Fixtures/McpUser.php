<?php

namespace SchoolAid\Nadota\Tests\Mcp\Fixtures;

use Illuminate\Foundation\Auth\User;

/**
 * A user without a table: the fixture policies read its `grants` (resource keys it may read).
 */
class McpUser extends User
{
    protected $guarded = [];

    protected $casts = ['grants' => 'array'];

    /**
     * @param  array<int, string>  $grants
     */
    public static function granted(array $grants, int $id = 1): self
    {
        $user = new self(['id' => $id, 'grants' => $grants]);
        $user->exists = true;

        return $user;
    }

    public function grants(string $resource): bool
    {
        return in_array($resource, $this->grants ?? [], true);
    }
}
