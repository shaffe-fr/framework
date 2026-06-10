<?php

namespace Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers;

use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Contracts\SolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Solution;
use Throwable;

class MassAssignmentSolutionProvider implements SolutionProvider
{
    public function canSolve(Throwable $throwable): bool
    {
        return $throwable instanceof MassAssignmentException;
    }

    public function getSolutions(Throwable $throwable): array
    {
        return [
            new Solution(
                title: 'Allow mass assignment for this attribute',
                description: 'Add the attribute to the $fillable array on your model, or remove it from $guarded.',
            ),
        ];
    }
}
