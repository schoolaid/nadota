<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Menu;

use SchoolAid\Nadota\Tests\Fixtures\Models\Grade;

/** Route key: "menu-beta". Menu options come from AbstractMenuFixture::configureMenu(). */
class MenuBetaResource extends AbstractMenuFixture
{
    public string $model = Grade::class;
}
