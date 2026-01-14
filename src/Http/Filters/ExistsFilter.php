<?php

namespace SchoolAid\Nadota\Http\Filters;

use Illuminate\Database\Eloquent\Builder;
use SchoolAid\Nadota\Http\Requests\NadotaRequest;

/**
 * Filter for Exists fields that checks relation existence using whereHas.
 *
 * Unlike BooleanFilter which applies WHERE on a column, this filter
 * uses whereHas/whereDoesntHave to check if a relation exists.
 */
class ExistsFilter extends Filter
{
    /**
     * The relation name to check.
     */
    protected string $relation;

    /**
     * Optional constraint callback for the exists query.
     */
    protected ?\Closure $constraint = null;

    /**
     * Create a new ExistsFilter.
     *
     * @param string $label Display label for the filter
     * @param string $attribute Filter key (e.g., 'filled_form_exists')
     * @param string $relation Relation name to check (e.g., 'filledForm')
     * @param \Closure|null $constraint Optional constraint for the relation query
     */
    public function __construct(
        string $label,
        string $attribute,
        string $relation,
        ?\Closure $constraint = null
    ) {
        parent::__construct($label, $attribute, 'exists');

        $this->relation = $relation;
        $this->constraint = $constraint;

        // Set component for frontend
        $this->component = 'FilterBoolean';
    }

    /**
     * Apply the filter to the query.
     *
     * @param NadotaRequest $request
     * @param Builder $query
     * @param mixed $value Filter value (true/false, 1/0, "true"/"false")
     * @return Builder
     */
    public function apply(NadotaRequest $request, $query, $value)
    {
        // Normalize value to boolean
        $exists = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if ($exists) {
            // Filter where relation exists
            return $this->constraint
                ? $query->whereHas($this->relation, $this->constraint)
                : $query->whereHas($this->relation);
        } else {
            // Filter where relation does not exist
            return $this->constraint
                ? $query->whereDoesntHave($this->relation, $this->constraint)
                : $query->whereDoesntHave($this->relation);
        }
    }

    /**
     * Get the relation name.
     *
     * @return string
     */
    public function getRelation(): string
    {
        return $this->relation;
    }

    /**
     * Get the constraint callback.
     *
     * @return \Closure|null
     */
    public function getConstraint(): ?\Closure
    {
        return $this->constraint;
    }
}
