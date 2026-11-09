<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Menu;

use SchoolAid\Nadota\Tests\Fixtures\Models\Course;

/** Route key: "menu-gamma". Menu options come from AbstractMenuFixture::configureMenu(). */
class MenuGammaResource extends AbstractMenuFixture
{
    public string $model = Course::class;
}
