<?php

namespace Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers;

use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Contracts\SolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Solution;
use Throwable;

class RelationNotFoundSolutionProvider implements SolutionProvider
{
    public function canSolve(Throwable $throwable): bool
    {
        return $throwable instanceof RelationNotFoundException;
    }

    public function getSolutions(Throwable $throwable): array
    {
        return [
            new Solution(
                title: "Define the [{$throwable->relation}] relationship",
                description: "Add a {$throwable->relation}() method on the {$throwable->model} model that returns a relationship.",
            ),
        ];
    }
}
