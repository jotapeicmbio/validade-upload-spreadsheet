<?php
namespace Jotapegue\Phpxform\helpers;

use InvalidArgumentException;

// if (!function_exists('slugify')) {
//     function slugify($string) : string {
//         return preg_replace('/\W+/', '_', trim(strtolower($string)));
//     }
// }

if (! function_exists('dd')) {
    function dd(): never
    {
        var_dump(...func_get_args());
        die();
    }
}

if (! function_exists('app_base')) {
    function app_base(?string $target = null): string
    {
        $pathToTarget = implode(DIRECTORY_SEPARATOR, [getcwd(), $target]);

        if (! file_exists($pathToTarget) && ! is_dir($pathToTarget)) {
            throw new InvalidArgumentException('Path or file does not exist');
        }

        return is_null($target)
            ? getcwd()
            : $pathToTarget;

    }
}
