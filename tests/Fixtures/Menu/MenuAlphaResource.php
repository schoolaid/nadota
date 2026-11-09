<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Menu;

use SchoolAid\Nadota\Tests\Fixtures\Models\School;

/** Route key: "menu-alpha". Menu options come from AbstractMenuFixture::configureMenu(). */
class MenuAlphaResource extends AbstractMenuFixture
{
    public string $model = School::class;
}
