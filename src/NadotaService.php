<?php
namespace SchoolAid\Nadota;

class NadotaService
{
    public static mixed $prepareMenuUsing = null;
    public static mixed $addMenuItems = null;
    public static function prepareMenuUsing(callable $callback): void
    {
        static::$prepareMenuUsing = $callback;
    }
    public static function addMenuItems(callable $callback): void
    {
        static::$addMenuItems = $callback;
    }
}
