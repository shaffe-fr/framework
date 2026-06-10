<?php

namespace Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers;

use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Contracts\SolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Solution;
use Throwable;

class ClassMorphViolationSolutionProvider implements SolutionProvider
{
    public function canSolve(Throwable $throwable): bool
    {
        return $throwable instanceof ClassMorphViolationException;
    }

    public function getSolutions(Throwable $throwable): array
    {
        return [
            new Solution(
                title: "Add [{$throwable->model}] to the morph map",
                description: 'Register this model in Relation::morphMap() in your AppServiceProvider boot method.',
            ),
        ];
    }
}
