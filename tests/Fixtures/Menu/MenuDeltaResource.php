<?php

namespace SchoolAid\Nadota\Tests\Fixtures\Menu;

use SchoolAid\Nadota\Tests\Fixtures\Models\Label;

/** Route key: "menu-delta". Menu options come from AbstractMenuFixture::configureMenu(). */
class MenuDeltaResource extends AbstractMenuFixture
{
    public string $model = Label::class;
}
