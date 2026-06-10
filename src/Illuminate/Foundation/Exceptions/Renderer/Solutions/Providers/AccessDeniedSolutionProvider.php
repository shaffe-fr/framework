<?php

namespace Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Contracts\SolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Solution;
use Throwable;

class AccessDeniedSolutionProvider implements SolutionProvider
{
    public function canSolve(Throwable $throwable): bool
    {
        if (! $throwable instanceof QueryException) {
            return false;
        }

        // SQLSTATE 28000: invalid authorization specification (access denied).
        if ((string) $throwable->getCode() === '28000') {
            return true;
        }

        return str_contains($throwable->getMessage(), 'Access denied for user');
    }

    public function getSolutions(Throwable $throwable): array
    {
        return [
            new Solution(
                title: 'Database access denied',
                description: "The database credentials are incorrect.\nCheck DB_USERNAME and DB_PASSWORD in your .env file.",
            ),
        ];
    }
}
