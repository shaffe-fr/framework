<?php

namespace Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers;

use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Contracts\SolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Solution;
use Throwable;

class SQLiteDatabaseNotFoundSolutionProvider implements SolutionProvider
{
    public function canSolve(Throwable $throwable): bool
    {
        return $throwable instanceof SQLiteDatabaseDoesNotExistException;
    }

    public function getSolutions(Throwable $throwable): array
    {
        return [
            new Solution(
                title: 'Create the SQLite database file',
                description: "The database file does not exist at the configured path.\n"
                    ."Create it with: touch database/database.sqlite\n"
                    .'Or check DB_DATABASE in your .env file.',
            ),
        ];
    }
}
