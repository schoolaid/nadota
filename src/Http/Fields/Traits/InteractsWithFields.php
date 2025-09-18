<?php

namespace SchoolAid\Nadota\Http\Fields\Traits;

/**
 * Main trait for field interactions.
 * Delegates to specialized traits for better organization.
 */
trait InteractsWithFields
{
    use ManagesFieldSelection;
    use ManagesRelationLoading;
    use ManagesFieldVisibility;
    use TransformsFieldData;
}
