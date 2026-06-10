<?php

namespace Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Contracts\SolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Solution;
use Throwable;

class ConnectionRefusedSolutionProvider implements SolutionProvider
{
    public function canSolve(Throwable $throwable): bool
    {
        if (! $throwable instanceof QueryException) {
            return false;
        }

        // The driver-specific code 2002 is only exposed in the message, and the
        // HY000 SQLSTATE is too generic to match on its own, so detection here
        // stays message-based.
        return str_contains($throwable->getMessage(), 'Connection refused')
            || str_contains($throwable->getMessage(), 'SQLSTATE[HY000] [2002]');
    }

    public function getSolutions(Throwable $throwable): array
    {
        return [
            new Solution(
                title: 'Database connection refused',
                description: "The database server is not reachable. Check that:\n"
                    ."- The database server is running\n"
                    ."- The host and port in your .env are correct\n"
                    .'- No firewall is blocking the connection',
            ),
        ];
    }
}
